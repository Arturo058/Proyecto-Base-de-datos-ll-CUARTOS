<?php
/**
 * Autenticación, control de acceso por rol y SESIÓN ÚNICA.
 * ------------------------------------------------------------------
 * Cada usuario tiene una columna `session_token` en la tabla `usuarios`.
 * - Al iniciar sesión se genera un token aleatorio nuevo y se guarda
 *   tanto en la base de datos como en $_SESSION.
 * - En cada carga de página protegida, require_login() compara ambos
 *   tokens. Si no coinciden (porque alguien volvió a iniciar sesión con
 *   la misma cuenta desde otro dispositivo, generando un token distinto),
 *   la sesión actual se considera inválida y se cierra automáticamente.
 * Este es el mecanismo estándar para lograr "una sola sesión activa por
 * usuario" sin necesidad de WebSockets ni notificaciones en tiempo real.
 * ------------------------------------------------------------------
 */

if (!isset($GLOBALS['trazabilidad_acid'])) {
    $GLOBALS['trazabilidad_acid'] = [];
}

/**
 * Función global para registrar eventos de concurrencia en tiempo real
 */
function registrar_evento_acid($tipo, $mensaje) {
    $GLOBALS['trazabilidad_acid'][] = [
        'tipo' => $tipo, // success, danger, warning, info
        'msg'  => $mensaje
    ];
}




require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Ruta relativa a la raíz del sitio, según si el script actual vive en admin/ o inquilino/. */
function ruta_base(): string
{
    $partes = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    return count($partes) > 1 ? '../' : '';
}

/**
 * Exige sesión válida (y, opcionalmente, un rol específico).
 * Devuelve el arreglo $_SESSION si todo es correcto; si no, redirige.
 */
function require_login(?string $rol_requerido = null): array
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . ruta_base() . 'login.php');
        exit;
    }

    $conn = get_conn();
    $stmt = mysqli_prepare($conn, 'SELECT session_token FROM usuarios WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['usuario_id']);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $fila = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    $token_valido = $fila && $fila['session_token'] !== null
        && hash_equals((string)$fila['session_token'], (string)($_SESSION['session_token'] ?? ''));

    if (!$token_valido) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . ruta_base() . 'login.php?motivo=otra_sesion');
        exit;
    }

    if ($rol_requerido !== null && $_SESSION['rol'] !== $rol_requerido) {
        $destino = $_SESSION['rol'] === 'admin' ? 'admin/dashboard.php' : 'inquilino/dashboard.php';
        header('Location: ' . ruta_base() . $destino);
        exit;
    }

    return $_SESSION;
}

/**
 * Verifica usuario/contraseña e inicia sesión (con rotación de token).
 * Devuelve null si el login fue exitoso, o un mensaje de error si no.
 */
/**
 * Intenta autenticar a un usuario en el sistema.
 * Versión blindada: Bloquea el acceso a inquilinos dados de baja (activo = 0).
 */
function attempt_login($username, $password) {
    $conn = get_conn();
    
    //UNIFICACIÓN DE SEGURIDAD: Traemos los datos del usuario y el estado del inquilino
    $stmt = mysqli_prepare($conn, "
        SELECT u.id, u.username, u.password_hash, u.rol, i.activo 
        FROM usuarios u
        LEFT JOIN inquilinos i ON u.id = i.usuario_id
        WHERE u.username = ?
    ");
    
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    // 1. Validar si el usuario existe y si la contraseña coincide matemáticamente
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'Usuario o contraseña incorrectos.';
    }

    // 2. EL CANDADO REALISTA: Si es inquilino y su estado es inactivo (0), se le prohíbe el paso
    if ($user['rol'] === 'inquilino' && $user['activo'] == 0) {
        return 'Esta cuenta ha sido desactivada por término de contrato o baja administrativa.';
    }

    // 3. Si pasa los filtros, se regenera el token de sesión única (El último en llegar gana)
    $token = bin2hex(random_bytes(32));
    $u_id = (int)$user['id'];
    
    $stmt = mysqli_prepare($conn, 'UPDATE usuarios SET session_token = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $token, $u_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Guardar datos limpios en la sesión global de PHP
    $_SESSION['usuario_id']   = $user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['rol']          = $user['rol'];
    $_SESSION['session_token'] = $token;

    // Si es inquilino, mapeamos también su ID de expediente de negocio
    if ($user['rol'] === 'inquilino') {
        $q_inq = mysqli_query($conn, "SELECT inquilino_id FROM inquilinos WHERE usuario_id = $u_id");
        $inq_data = mysqli_fetch_assoc($q_inq);
        $_SESSION['inquilino_id'] = $inq_data ? $inq_data['inquilino_id'] : 0;
    }

    return null; // Éxito total
}


/** Cierra sesión e invalida el token en base de datos. */
function do_logout(): void
{
    if (isset($_SESSION['usuario_id'])) {
        $conn = get_conn();
        $stmt = mysqli_prepare($conn, 'UPDATE usuarios SET session_token = NULL WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $_SESSION['usuario_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $_SESSION = [];
    session_destroy();
}
