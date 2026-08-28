<?php
/**
 * Barra lateral: sus opciones cambian según $_SESSION['rol'].
 * $activo (definido por la página que incluye layout_top.php) resalta
 * el enlace correspondiente a la sección actual.
 */
$rol_actual = $_SESSION['rol'] ?? '';
$activo     = $activo ?? '';
?>
<div class="sidebar bg-dark text-white d-flex flex-column p-3">
    <div class="text-center mb-4">
        <div class="fs-3">🏠</div>
        <div class="fw-bold fs-5">Cuartos Tress</div>
        <div class="small text-white-50"><?= $rol_actual === 'admin' ? 'Panel Administrador' : 'Panel Inquilino' ?></div>
    </div>

    <nav class="nav nav-pills flex-column gap-1 flex-grow-1">
        <?php if ($rol_actual === 'admin'): ?>
            <a class="nav-link <?= $activo === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">📊 Dashboard</a>
            <a class="nav-link <?= $activo === 'inquilinos' ? 'active' : '' ?>" href="inquilinos.php">🧑‍🤝‍🧑 Inquilinos</a>
            <a class="nav-link <?= $activo === 'cuartos' ? 'active' : '' ?>" href="cuartos.php">🚪 Cuartos</a>
            <a class="nav-link <?= $activo === 'llamadas' ? 'active' : '' ?>" href="llamadas.php">⚠️ Llamadas de Atención</a>
            <a class="nav-link <?= $activo === 'respaldo' ? 'active' : '' ?>" href="respaldo.php">💾 Sistema / Respaldo</a>
        <?php elseif ($rol_actual === 'inquilino'): ?>
            <a class="nav-link <?= $activo === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">🏡 Mi Estado</a>
            <a class="nav-link <?= $activo === 'reportes' ? 'active' : '' ?>" href="reportes.php">🛠 Mis Reportes</a>
            <a class="nav-link <?= $activo === 'llamadas' ? 'active' : '' ?>" href="llamadas.php">⚠️ Mis Llamadas de Atención</a>
        <?php endif; ?>
    </nav>

    <a href="<?= $rol_actual ? '../' : '' ?>logout.php" class="btn btn-outline-light btn-sm mt-3">🚪 Cerrar sesión</a>
</div>
