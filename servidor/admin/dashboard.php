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

//  CONSULTA ADICIONAL: Datos extras para la gráfica de Ocupación (Dona)
$cuartos_disponibles = 0;
$cuartos_mantenimiento = 0;

$res_disp = mysqli_query($conn, "SELECT COUNT(*) t FROM cuartos WHERE estado = 'Disponible'");
if ($res_disp) $cuartos_disponibles = (int)mysqli_fetch_assoc($res_disp)['t'];

$res_mant = mysqli_query($conn, "SELECT COUNT(*) t FROM cuartos WHERE estado = 'Mantenimiento'");
if ($res_mant) $cuartos_mantenimiento = (int)mysqli_fetch_assoc($res_mant)['t'];

//  CONSULTA ADICIONAL: Datos para la gráfica de Barras (Ingresos por Mes)
$meses_labels = [];
$montos_data = [];

$res_pagos = mysqli_query($conn, "
    SELECT mes_cobertura, SUM(monto_pagado) as total 
    FROM pagos_renta 
    GROUP BY mes_cobertura 
    ORDER BY MIN(fecha_pago) ASC 
    LIMIT 6
");

if ($res_pagos && mysqli_num_rows($res_pagos) > 0) {
    while ($row = mysqli_fetch_assoc($res_pagos)) {
        $meses_labels[] = $row['mes_cobertura'];
        $montos_data[]  = (float)$row['total'];
    }
} else {
    // Datos por defecto si tu base de datos local está vacía al iniciar
    $meses_labels = ['Julio 2026', 'Agosto 2026', 'Septiembre 2026'];
    $montos_data = [0, 0, 0];
}

$res = mysqli_query($conn, "SELECT r.titulo, r.descripcion, r.prioridad, r.fecha_creacion, i.nombre_completo, c.numero_cuarto
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

<!--  CDN Oficial de Chart.js inyectado limpiamente -->
<script src="../assets/chart.js"></script>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-metric p-3 border-0 shadow-sm">
            <span class="text-muted small">Cuartos Ocupados</span>
            <span class="fs-1 fw-bold text-danger"><?= $cuartos_ocupados ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-metric p-3 border-0 shadow-sm">
            <span class="text-muted small">Reportes Pendientes</span>
            <span class="fs-1 fw-bold text-warning"><?= $reportes_pendientes ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-metric p-3 border-0 shadow-sm">
            <span class="text-muted small">Total de Inquilinos</span>
            <span class="fs-1 fw-bold text-primary"><?= $total_inquilinos ?></span>
        </div>
    </div>
</div>

<!--  CONTENEDORES PARA GRÁFICAS ANALÍTICAS -->
<div class="row g-3 mb-4">
    <!-- Gráfica de Barras (Ingresos) -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3"><strong>📈 Historial de Ingresos de Rentas (MXN)</strong></div>
            <div class="card-body d-flex align-items-center justify-content-center" style="position: relative; height: 260px;">
                <canvas id="chartIngresos"></canvas>
            </div>
        </div>
    </div>
    <!-- Gráfica de Dona (Disponibilidad) -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3"><strong>🚪 Estatus del Inventario</strong></div>
            <div class="card-body d-flex align-items-center justify-content-center" style="position: relative; height: 260px;">
                <canvas id="chartOcupacion"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><strong>Reportes pendientes más recientes</strong></div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th>Título</th>
                    <th>Descripción</th> 
                    <th>Inquilino</th>
                    <th>Cuarto</th>
                    <th>Prioridad</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($reportes_recientes)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No hay reportes pendientes. 🎉</td></tr>
            <?php else: foreach ($reportes_recientes as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['titulo']) ?></strong></td>
                    <td class="text-muted small" style="max-width: 250px; white-space: normal; word-wrap: break-word;">
                        <?= htmlspecialchars($r['descripcion']) ?>
                    </td>
                    <td><?= htmlspecialchars($r['nombre_completo']) ?></td>
                    <td><span class="badge bg-light text-dark border">🚪 <?= htmlspecialchars($r['numero_cuarto']) ?></span></td>
                    <td><span class="badge <?= claseBadgePrioridad($r['prioridad']) ?>"><?= htmlspecialchars($r['prioridad']) ?></span></td>
                    <td class="small text-secondary"><?= htmlspecialchars($r['fecha_creacion']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!--  SCRIPT JAVASCRIPT: Dibujado de Gráficas Reactivas -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Gráfica de Ingresos Mensuales (Barras Azules)
    const ctxIngresos = document.getElementById('chartIngresos').getContext('2d');
    new Chart(ctxIngresos, {
        type: 'bar',
        data: {
            labels: <?= json_encode($meses_labels) ?>,
            datasets: [{
                label: 'Total Cobrado ($)',
                data: <?= json_encode($montos_data) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.8)', // Azul que combina con tu interfaz
                borderColor: '#3b82f6',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // 2. Gráfica de Disponibilidad (Dona)
    const ctxOcupacion = document.getElementById('chartOcupacion').getContext('2d');
    new Chart(ctxOcupacion, {
        type: 'doughnut',
        data: {
            labels: ['Ocupados', 'Disponibles', 'Mantenimiento'],
            datasets: [{
                data: [<?= $cuartos_ocupados ?>, <?= $cuartos_disponibles ?>, <?= $cuartos_mantenimiento ?>],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.8)',  // Rojo para Ocupados
                    'rgba(16, 185, 129, 0.8)', // Verde para Disponibles
                    'rgba(245, 158, 11, 0.8)'  // Amarillo para Mantenimiento
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
