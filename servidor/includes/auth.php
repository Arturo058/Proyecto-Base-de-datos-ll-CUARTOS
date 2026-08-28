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
function attempt_login(string $username, string $password): ?string
{
    $conn = get_conn();
    $stmt = mysqli_prepare($conn, 'SELECT id, username, password_hash, rol FROM usuarios WHERE username = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $res     = mysqli_stmt_get_result($stmt);
    $usuario = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        return 'Usuario o contraseña incorrectos.';
    }

    // Nuevo token: cualquier sesión anterior de este usuario queda invalidada.
    $token = bin2hex(random_bytes(32));
    $stmt  = mysqli_prepare($conn, 'UPDATE usuarios SET session_token = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $token, $usuario['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    session_regenerate_id(true);
    $_SESSION['usuario_id']    = $usuario['id'];
    $_SESSION['username']      = $usuario['username'];
    $_SESSION['rol']           = $usuario['rol'];
    $_SESSION['session_token'] = $token;

    if ($usuario['rol'] === 'inquilino') {
        $stmt = mysqli_prepare($conn, 'SELECT inquilino_id FROM inquilinos WHERE usuario_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $usuario['id']);
        mysqli_stmt_execute($stmt);
        $res  = mysqli_stmt_get_result($stmt);
        $fila = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        $_SESSION['inquilino_id'] = $fila['inquilino_id'] ?? null;
    }

    return null;
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
