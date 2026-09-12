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
    <title>Iniciar sesión - Renta Cuartos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #06141f;
            --primary: #3b82f6;
            --primary-strong: #1d4ed8;
            --primary-soft: #dbeafe;
            --muted: #64748b;
            --text: #0f172a;
            --white: #ffffff;
            --danger-bg: #fef2f2;
            --danger-text: #991b1b;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.28), transparent 30%),
                        linear-gradient(135deg, #06141f 0%, #0f172a 35%, #172554 100%);
            color: var(--text);
        }

        .login-shell {
            width: min(980px, 92vw);
            min-height: 620px;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 28px;
            overflow: hidden;
            backdrop-filter: blur(10px);
            box-shadow: 0 30px 60px rgba(2, 6, 23, 0.45);
        }

        .brand-panel {
            background: linear-gradient(160deg, rgba(17, 24, 39, 0.96), rgba(30, 64, 175, 0.88));
            color: var(--white);
            padding: 48px 38px;
            position: relative;
        }

        .brand-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0.08), transparent 40%);
            pointer-events: none;
        }

        .brand-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-badge {
            width: 120px;
            height: 120px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgb(255, 255, 255);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin-bottom: 24px;
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.3);
            overflow: hidden;
        }

        .login-logo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .brand-panel h1 {
            margin: 0;
            font-size: clamp(2rem, 3vw, 3rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.06em;
        }

        .brand-panel p {
            margin: 18px 0 0;
            font-size: 1.02rem;
            color: rgba(255, 255, 255, 0.82);
            max-width: 400px;
            line-height: 1.7;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 26px 0 0;
            display: grid;
            gap: 12px;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }

        .feature-list .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #7dd3fc;
            box-shadow: 0 0 10px rgba(125, 211, 252, 0.8);
        }

        .form-panel {
            background: rgba(255, 255, 255, 0.96);
            padding: 45px 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-wrap {
            width: 100%;
            max-width: 380px;
        }

        .mini-label {
            display: inline-block;
            background: var(--primary-soft);
            color: var(--primary-strong);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .form-panel h2 {
            margin: 0 0 8px;
            font-weight: 800;
            color: var(--text);
            font-size: 2rem;
            letter-spacing: -0.04em;
        }

        .form-panel .subtitle {
            margin: 0 0 28px;
            color: var(--muted);
            font-size: 0.98rem;
        }

        .form-label {
            color: #334155;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.6rem;
        }

        .form-control {
            height: 52px;
            border: 1px solid #dfe7f3;
            border-radius: 14px;
            background: #f8fafc;
            padding: 0.8rem 0.95rem;
            color: var(--text);
            font-size: 0.98rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: rgba(59, 130, 246, 0.7);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
            outline: none;
        }

        .btn-submit-premium {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            letter-spacing: 0.04em;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-strong) 100%);
            color: var(--white);
            box-shadow: 0 16px 28px rgba(59, 130, 246, 0.28);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 12px;
        }

        .btn-submit-premium:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 32px rgba(59, 130, 246, 0.32);
        }

        .alert {
            border-radius: 14px;
            border: 1px solid #fecaca;
            background: var(--danger-bg);
            color: var(--danger-text);
            font-size: 0.92rem;
            padding: 12px 14px;
            margin-bottom: 22px;
        }

        @media (max-width: 840px) {
            .login-shell { grid-template-columns: 1fr; }
            .brand-panel { min-height: 260px; }
            .form-panel { padding-top: 24px; padding-bottom: 36px; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <aside class="brand-panel">
            <div class="brand-content">
                <div class="brand-badge">
                    <img src="assets/logo2.png" alt="Logo Cuartos Tress" class="login-logo">
                </div>
                <h1>Cuartos Tress</h1>
                <p>Control inteligente de renta, inquilinos y mantenimiento en una sola plataforma.</p>
                <ul class="feature-list">
                    <li><span class="dot"></span> Gestión de habitaciones y pagos</li>
                    <li><span class="dot"></span> Seguimiento de reportes y mantenimiento</li>
                    <li><span class="dot"></span> Acceso seguro por rol y sesión única</li>
                </ul>
            </div>
        </aside>

        <main class="form-panel">
            <div class="form-wrap">
                <span class="mini-label">Portal interno</span>
                <h2>Bienvenido</h2>
                <p class="subtitle">Inicia sesión para continuar con tu cuenta.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" name="username" class="form-control" required autofocus placeholder="Ej. miguel_admin">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-submit-premium">INGRESAR</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
