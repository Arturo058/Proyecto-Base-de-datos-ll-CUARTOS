<?php
/**
 * Barra lateral: sus opciones cambian según $_SESSION['rol'].
 * $activo (definido por la página que incluye layout_top.php) resalta
 * el enlace correspondiente a la sección actual.
 */
$rol_actual = $_SESSION['rol'] ?? '';
$activo     = $activo ?? '';
?>
<div class="sidebar text-white d-flex flex-column p-3" style="background: linear-gradient(180deg, #0b1b2d 0%, #101f2f 100%); min-height: 100vh; border-right: 1px solid rgba(255,255,255,0.08);">
    <div class="text-center mb-4 pt-2">
        <div class="mb-3 d-flex justify-content-center">
            <img src="../assets/logo2.png" alt="Logo Cuartos Tress" style="width: 90px; height: 90px; object-fit: cover; border-radius: 18px; background: rgb(242, 237, 237); padding: 7px; box-shadow: 0 8px 18px rgba(0,0,0,0.2);">
        </div>
        <div class="fw-bold" style="font-size: 2rem; letter-spacing: -0.05em; line-height: 1.1; margin-bottom: 4px;">Cuartos Tress</div>
        <div class="small text-white-50" style="font-size: 0.95rem;"><?= $rol_actual === 'admin' ? 'Panel Administrador' : 'Panel Inquilino' ?></div>
    </div>

    <nav class="nav nav-pills flex-column gap-2 flex-grow-1 mt-2">
        <?php if ($rol_actual === 'admin'): ?>
            <a class="nav-link <?= $activo === 'dashboard' ? 'active' : '' ?>" href="dashboard.php" style="<?= $activo === 'dashboard' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">📊 Dashboard</a>
            <a class="nav-link <?= $activo === 'inquilinos' ? 'active' : '' ?>" href="inquilinos.php" style="<?= $activo === 'inquilinos' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">🧑‍🤝‍🧑 Inquilinos</a>
            <a class="nav-link <?= $activo === 'cuartos' ? 'active' : '' ?>" href="cuartos.php" style="<?= $activo === 'cuartos' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">🚪 Cuartos</a>
            <a class="nav-link <?= $activo === 'llamadas' ? 'active' : '' ?>" href="llamadas.php" style="<?= $activo === 'llamadas' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">⚠️ Llamadas de Atención</a>
            <a class="nav-link <?= $activo === 'pagos' ? 'active' : '' ?>" href="pagos.php" style="<?= $activo === 'pagos' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">💳 Historial Financiero</a>
            <a class="nav-link <?= $activo === 'respaldo' ? 'active' : '' ?>" href="respaldo.php" style="<?= $activo === 'respaldo' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">💾 Sistema / Respaldo</a>
        <?php elseif ($rol_actual === 'inquilino'): ?>
            <a class="nav-link <?= $activo === 'dashboard' ? 'active' : '' ?>" href="dashboard.php" style="<?= $activo === 'dashboard' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">🏡 Mi Estado</a>
            <a class="nav-link <?= $activo === 'reportes' ? 'active' : '' ?>" href="reportes.php" style="<?= $activo === 'reportes' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">🛠 Mis Reportes</a>
            <a class="nav-link <?= $activo === 'llamadas' ? 'active' : '' ?>" href="llamadas.php" style="<?= $activo === 'llamadas' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; font-weight: 600; border-radius: 12px; padding: 12px 14px; box-shadow: 0 10px 20px rgba(59,130,246,0.22);' : 'color: #dbeafe; border-radius: 12px; padding: 12px 14px;' ?>">⚠️ Mis Llamadas de Atención</a>
        <?php endif; ?>
    </nav>

    <a href="<?= $rol_actual ? '../' : '' ?>logout.php" class="btn btn-sm mt-3" style="background: rgba(255,255,255,0.08); color: white; border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: 11px 14px; font-weight: 600;">🚪 Cerrar sesión</a>
</div>
