<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$sesion = require_login('inquilino');
$conn = get_conn();

$mi_id = (int)($sesion['inquilino_id'] ?? 0);
$mi = null;
if ($mi_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT i.*, c.numero_cuarto, c.precio_mensual
                                    FROM inquilinos i JOIN cuartos c ON c.cuarto_id = i.cuarto_id
                                    WHERE i.inquilino_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $mi_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $mi  = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

$reportes_pendientes = 0;
$llamadas_aplicadas   = 0;
if ($mi_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) t FROM reportes_mantenimiento WHERE inquilino_id = ? AND estado = 'Pendiente'");
    mysqli_stmt_bind_param($stmt, 'i', $mi_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) $reportes_pendientes = (int)mysqli_fetch_assoc($res)['t'];
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) t FROM llamadas_atencion WHERE inquilino_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $mi_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) $llamadas_aplicadas = (int)mysqli_fetch_assoc($res)['t'];
    mysqli_stmt_close($stmt);
}

$titulo = 'Mi Estado';
$activo = 'dashboard';
require __DIR__ . '/../includes/layout_top.php';
?>

<?php if ($mi): ?>
    <div class="card card-metric p-4 mb-4">
        <span class="text-muted small">Tu contrato</span>
        <h3 class="fw-bold mb-1"><?= tiempo_transcurrido($mi['fecha_inicio_contrato']) ?> en la habitación <?= htmlspecialchars($mi['numero_cuarto']) ?></h3>
        <p class="text-muted mb-0">Contrato iniciado el <?= htmlspecialchars($mi['fecha_inicio_contrato']) ?>
            <?= $mi['fecha_fin_contrato'] ? ' · Vence el ' . htmlspecialchars($mi['fecha_fin_contrato']) : ' · Vigente' ?>
        </p>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card card-metric p-3">
                <span class="text-muted small">Cuarto asignado</span>
                <span class="fs-2 fw-bold text-primary"><?= htmlspecialchars($mi['numero_cuarto']) ?></span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-metric p-3">
                <span class="text-muted small">Reportes pendientes</span>
                <span class="fs-2 fw-bold text-warning"><?= $reportes_pendientes ?></span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-metric p-3">
                <span class="text-muted small">Llamadas de atención</span>
                <span class="fs-2 fw-bold text-danger"><?= $llamadas_aplicadas ?></span>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">No se encontró tu expediente de inquilino. Contacta al administrador.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
