<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$sesion = require_login('inquilino');
$conn = get_conn();
$mi_id = (int)($sesion['inquilino_id'] ?? 0);

$form_error   = '';
$form_success = '';

if ($mi_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    // ---- DAR DE ALTA: nuevo reporte de mantenimiento ----
    if ($_POST['accion'] === 'crear_reporte') {
        $titulo_reporte = trim((string)($_POST['titulo'] ?? ''));
        $descripcion    = trim((string)($_POST['descripcion'] ?? ''));
        $prioridad      = (string)($_POST['prioridad'] ?? 'Media');

        if ($titulo_reporte === '' || $descripcion === '' || !in_array($prioridad, ['Baja', 'Media', 'Alta'], true)) {
            $form_error = 'Completa el título, la descripción y una prioridad válida.';
        } else {
            $stmt = mysqli_prepare($conn, 'SELECT cuarto_id FROM inquilinos WHERE inquilino_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $mi_id);
            mysqli_stmt_execute($stmt);
            $res  = mysqli_stmt_get_result($stmt);
            $fila = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);

            if ($fila) {
                $stmt = mysqli_prepare($conn, "INSERT INTO reportes_mantenimiento (inquilino_id, cuarto_id, titulo, descripcion, prioridad, estado) VALUES (?, ?, ?, ?, ?, 'Pendiente')");
                mysqli_stmt_bind_param($stmt, 'iisss', $mi_id, $fila['cuarto_id'], $titulo_reporte, $descripcion, $prioridad);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $form_success = 'Reporte de mantenimiento enviado.';
            }
        }
    }

    // ---- BAJA: cancelar un reporte creado por error (solo si sigue Pendiente) ----
    if ($_POST['accion'] === 'cancelar_reporte') {
        $reporte_id = (int)($_POST['reporte_id'] ?? 0);
        if ($reporte_id > 0) {
            // La condición inquilino_id/estado impide cancelar reportes ajenos o ya atendidos.
            $stmt = mysqli_prepare($conn, "UPDATE reportes_mantenimiento SET estado = 'Cancelado' WHERE reporte_id = ? AND inquilino_id = ? AND estado = 'Pendiente'");
            mysqli_stmt_bind_param($stmt, 'ii', $reporte_id, $mi_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: reportes.php');
        exit;
    }
}

$mis_reportes = [];
if ($mi_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT reporte_id, titulo, descripcion, prioridad, estado, fecha_creacion FROM reportes_mantenimiento WHERE inquilino_id = ? ORDER BY fecha_creacion DESC');
    mysqli_stmt_bind_param($stmt, 'i', $mi_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { while ($f = mysqli_fetch_assoc($res)) { $mis_reportes[] = $f; } }
    mysqli_stmt_close($stmt);
}

$titulo = 'Mis Reportes de Mantenimiento';
$activo = 'reportes';
require __DIR__ . '/../includes/layout_top.php';
?>

<?php if ($form_error !== ''): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>
<?php if ($form_success !== ''): ?><div class="alert alert-success py-2"><?= htmlspecialchars($form_success) ?></div><?php endif; ?>

<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoReporte">➕ Dar de Alta</button>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-light"><tr><th>Título</th><th>Descripción</th><th>Prioridad</th><th>Estado</th><th>Fecha</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if (empty($mis_reportes)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No has enviado reportes.</td></tr>
            <?php else: foreach ($mis_reportes as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['titulo']) ?></td>
                    <td><?= htmlspecialchars($r['descripcion']) ?></td>
                    <td><span class="badge <?= claseBadgePrioridad($r['prioridad']) ?>"><?= htmlspecialchars($r['prioridad']) ?></span></td>
                    <td><span class="badge <?= claseBadgeReporte($r['estado']) ?>"><?= htmlspecialchars($r['estado']) ?></span></td>
                    <td><?= htmlspecialchars($r['fecha_creacion']) ?></td>
                    <td class="text-end">
                        <?php if ($r['estado'] === 'Pendiente'): ?>
                            <form method="post" action="reportes.php" onsubmit="return confirm('¿Cancelar este reporte?');">
                                <input type="hidden" name="accion" value="cancelar_reporte">
                                <input type="hidden" name="reporte_id" value="<?= (int)$r['reporte_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">🗑 Cancelar</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: DAR DE ALTA reporte -->
<div class="modal fade" id="modalNuevoReporte" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="reportes.php">
                <input type="hidden" name="accion" value="crear_reporte">
                <div class="modal-header">
                    <h5 class="modal-title">➕ Nuevo reporte de mantenimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ej. Fuga en el lavabo" required maxlength="150">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="4" required placeholder="Describe el problema con detalle..."></textarea>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Prioridad</label>
                        <select name="prioridad" class="form-select">
                            <option value="Baja">Baja</option>
                            <option value="Media" selected>Media</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar reporte</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
