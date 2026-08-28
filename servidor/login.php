<?php
/**
 * Puerta de entrada única. Sin registro público: las cuentas solo las
 * crea el Administrador desde el módulo de Inquilinos.
 */
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Debes ingresar usuario y contraseña.';
    } else {
        $resultado = attempt_login($username, $password);
        if ($resultado === null) {
            header('Location: index.php');
            exit;
        }
        $error = $resultado;
    }
}

if ($error === '' && isset($_GET['motivo']) && $_GET['motivo'] === 'otra_sesion') {
    $error = 'Tu sesión se cerró porque se inició sesión con esta cuenta desde otro dispositivo.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - RentaCuartos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #101828;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-card { max-width: 400px; width: 100%; border: none; border-radius: 1rem; box-shadow: 0 8px 30px rgba(0,0,0,.25); }
        .logo-circle {
            width: 64px; height: 64px; border-radius: 50%;
            background: #0d6efd; color: #fff; font-size: 1.75rem;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem auto;
        }
    </style>
</head>
<body>
    <div class="card login-card p-4">
        <div class="card-body">
            <div class="logo-circle">🏠</div>
            <h4 class="text-center mb-1 fw-bold">RentaCuartos</h4>
            <p class="text-center text-muted mb-4">Acceso exclusivo — inicia sesión para continuar</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>
        </div>
    </div>
</body>
</html>
