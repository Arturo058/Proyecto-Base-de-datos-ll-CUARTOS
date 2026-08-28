<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login('admin');
$conn = get_conn();

$cuartos_ocupados    = 0;
$reportes_pendientes = 0;
$total_inquilinos    = 0;

$res = mysqli_query($conn, "SELECT COUNT(*) t FROM cuartos WHERE estado = 'Ocupado'");
if ($res) $cuartos_ocupados = (int)mysqli_fetch_assoc($res)['t'];

$res = mysqli_query($conn, "SELECT COUNT(*) t FROM reportes_mantenimiento WHERE estado = 'Pendiente'");
if ($res) $reportes_pendientes = (int)mysqli_fetch_assoc($res)['t'];

$res = mysqli_query($conn, "SELECT COUNT(*) t FROM inquilinos WHERE activo = 1");
if ($res) $total_inquilinos = (int)mysqli_fetch_assoc($res)['t'];

$res = mysqli_query($conn, "SELECT r.titulo, r.prioridad, r.fecha_creacion, i.nombre_completo, c.numero_cuarto
                             FROM reportes_mantenimiento r
                             JOIN inquilinos i ON i.inquilino_id = r.inquilino_id
                             JOIN cuartos c ON c.cuarto_id = r.cuarto_id
                             WHERE r.estado = 'Pendiente'
                             ORDER BY r.fecha_creacion DESC LIMIT 5");
$reportes_recientes = [];
if ($res) { while ($f = mysqli_fetch_assoc($res)) { $reportes_recientes[] = $f; } }

$titulo = 'Dashboard General';
$activo = 'dashboard';
require __DIR__ . '/../includes/layout_top.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-metric p-3">
            <span class="text-muted small">Cuartos Ocupados</span>
            <span class="fs-1 fw-bold text-danger"><?= $cuartos_ocupados ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-metric p-3">
            <span class="text-muted small">Reportes Pendientes</span>
            <span class="fs-1 fw-bold text-warning"><?= $reportes_pendientes ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-metric p-3">
            <span class="text-muted small">Total de Inquilinos</span>
            <span class="fs-1 fw-bold text-primary"><?= $total_inquilinos ?></span>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Reportes pendientes más recientes</strong></div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead class="table-light"><tr><th>Título</th><th>Inquilino</th><th>Cuarto</th><th>Prioridad</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php if (empty($reportes_recientes)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No hay reportes pendientes. 🎉</td></tr>
            <?php else: foreach ($reportes_recientes as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['titulo']) ?></td>
                    <td><?= htmlspecialchars($r['nombre_completo']) ?></td>
                    <td><?= htmlspecialchars($r['numero_cuarto']) ?></td>
                    <td><span class="badge <?= claseBadgePrioridad($r['prioridad']) ?>"><?= htmlspecialchars($r['prioridad']) ?></span></td>
                    <td><?= htmlspecialchars($r['fecha_creacion']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
