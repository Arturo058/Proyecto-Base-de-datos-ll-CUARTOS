<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login('admin');
$conn = get_conn();

$form_error   = '';
$form_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_llamada') {
    $inquilino_id = (int)($_POST['inquilino_id'] ?? 0);
    $motivo       = trim((string)($_POST['motivo'] ?? ''));

    if ($inquilino_id <= 0 || $motivo === '') {
        $form_error = 'Selecciona un inquilino y describe el motivo de la falta.';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO llamadas_atencion (inquilino_id, motivo, estado) VALUES (?, ?, 'Aplicada')");
        mysqli_stmt_bind_param($stmt, 'is', $inquilino_id, $motivo);
        if (mysqli_stmt_execute($stmt)) {
            $form_success = 'Llamada de atención registrada.';
        } else {
            $form_error = 'No fue posible registrar la llamada de atención.';
        }
        mysqli_stmt_close($stmt);
    }
}

$inquilinos_activos = [];
$res = mysqli_query($conn, "SELECT i.inquilino_id, i.nombre_completo, c.numero_cuarto
                             FROM inquilinos i JOIN cuartos c ON c.cuarto_id = i.cuarto_id
                             WHERE i.activo = 1 ORDER BY i.nombre_completo");
if ($res) { while ($f = mysqli_fetch_assoc($res)) { $inquilinos_activos[] = $f; } }

$llamadas = [];
$res = mysqli_query($conn, "SELECT l.llamada_id, l.motivo, l.descargo, l.estado, l.fecha_creacion, l.fecha_descargo,
                                    i.nombre_completo, c.numero_cuarto
                             FROM llamadas_atencion l
                             JOIN inquilinos i ON i.inquilino_id = l.inquilino_id
                             JOIN cuartos c ON c.cuarto_id = i.cuarto_id
                             ORDER BY l.fecha_creacion DESC");
if ($res) { while ($f = mysqli_fetch_assoc($res)) { $llamadas[] = $f; } }

$titulo = 'Llamadas de Atención';
$activo = 'llamadas';
require __DIR__ . '/../includes/layout_top.php';
?>

<?php if ($form_error !== ''): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>
<?php if ($form_success !== ''): ?><div class="alert alert-success py-2"><?= htmlspecialchars($form_success) ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>➕ Dar de alta una llamada de atención</strong></div>
            <div class="card-body">
                <form method="post" action="llamadas.php">
                    <input type="hidden" name="accion" value="crear_llamada">
                    <div class="mb-2">
                        <label class="form-label">Inquilino</label>
                        <select name="inquilino_id" class="form-select" required>
                            <option value="">Selecciona un inquilino...</option>
                            <?php foreach ($inquilinos_activos as $i): ?>
                                <option value="<?= (int)$i['inquilino_id'] ?>"><?= htmlspecialchars($i['nombre_completo']) ?> (Cuarto <?= htmlspecialchars($i['numero_cuarto']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo de la falta</label>
                        <textarea name="motivo" class="form-control" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Registrar llamada de atención</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Historial y descargos de los inquilinos</strong></div>
            <div class="list-group list-group-flush">
                <?php if (empty($llamadas)): ?>
                    <div class="text-center text-muted py-4">Sin llamadas de atención registradas.</div>
                <?php else: foreach ($llamadas as $l): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= htmlspecialchars($l['nombre_completo']) ?> — Cuarto <?= htmlspecialchars($l['numero_cuarto']) ?></strong>
                            <span class="badge <?= claseBadgeLlamada($l['estado']) ?>"><?= htmlspecialchars($l['estado']) ?></span>
                        </div>
                        <p class="mb-1 mt-1"><strong>Motivo:</strong> <?= htmlspecialchars($l['motivo']) ?></p>
                        <p class="text-muted small mb-2">Aplicada el <?= htmlspecialchars($l['fecha_creacion']) ?></p>
                        <?php if (!empty($l['descargo'])): ?>
                            <div class="bg-light border rounded p-2 mt-2">
                                <p class="mb-1 small text-muted">Descargo / apelación del inquilino (<?= htmlspecialchars($l['fecha_descargo'] ?? '') ?>):</p>
                                <p class="mb-0"><?= htmlspecialchars($l['descargo']) ?></p>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0 fst-italic">El inquilino aún no ha enviado un descargo.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
