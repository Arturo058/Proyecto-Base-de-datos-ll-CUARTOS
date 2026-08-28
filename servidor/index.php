<?php
/**
 * Router de entrada: si no hay sesión válida, require_login() ya redirige
 * a login.php. Si la hay, mandamos a cada quien a su panel.
 */
require_once __DIR__ . '/includes/auth.php';
$sesion = require_login();
header('Location: ' . ($sesion['rol'] === 'admin' ? 'admin/dashboard.php' : 'inquilino/dashboard.php'));
exit;
