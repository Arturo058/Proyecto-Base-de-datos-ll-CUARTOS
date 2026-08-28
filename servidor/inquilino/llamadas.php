<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$sesion = require_login('inquilino');
$conn = get_conn();
$mi_id = (int)($sesion['inquilino_id'] ?? 0);

$form_error   = '';
$form_success = '';

// ---- ACTUALIZAR: redactar el descargo/apelación de una llamada propia ----
if ($mi_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'enviar_descargo') {
    $llamada_id = (int)($_POST['llamada_id'] ?? 0);
    $descargo   = trim((string)($_POST['descargo'] ?? ''));

    if ($llamada_id <= 0 || $descargo === '') {
        $form_error = 'Escribe tu mensaje de apelación antes de enviarlo.';
    } else {
        // inquilino_id en el WHERE evita que se pueda editar la llamada de alguien más.
        $stmt = mysqli_prepare($conn, "UPDATE llamadas_atencion
                                        SET descargo = ?, estado = 'En Revision', fecha_descargo = NOW()
                                        WHERE llamada_id = ? AND inquilino_id = ?");
        mysqli_stmt_bind_param($stmt, 'sii', $descargo, $llamada_id, $mi_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $form_success = 'Tu apelación fue enviada al administrador.';
    }
}

$mis_llamadas = [];
if ($mi_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT llamada_id, motivo, descargo, estado, fecha_creacion, fecha_descargo FROM llamadas_atencion WHERE inquilino_id = ? ORDER BY fecha_creacion DESC');
    mysqli_stmt_bind_param($stmt, 'i', $mi_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { while ($f = mysqli_fetch_assoc($res)) { $mis_llamadas[] = $f; } }
    mysqli_stmt_close($stmt);
}

$titulo = 'Mis Llamadas de Atención';
$activo = 'llamadas';
require __DIR__ . '/../includes/layout_top.php';
?>

<?php if ($form_error !== ''): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>
<?php if ($form_success !== ''): ?><div class="alert alert-success py-2"><?= htmlspecialchars($form_success) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="list-group list-group-flush">
        <?php if (empty($mis_llamadas)): ?>
            <div class="text-center text-muted py-5">🎉 No tienes llamadas de atención registradas.</div>
        <?php else: foreach ($mis_llamadas as $l): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="mb-1"><strong>Motivo:</strong> <?= htmlspecialchars($l['motivo']) ?></p>
                        <p class="text-muted small mb-0">Aplicada el <?= htmlspecialchars($l['fecha_creacion']) ?></p>
                    </div>
                    <span class="badge <?= claseBadgeLlamada($l['estado']) ?>"><?= htmlspecialchars($l['estado']) ?></span>
                </div>

                <?php if (!empty($l['descargo'])): ?>
                    <div class="bg-light border rounded p-2 mt-2">
                        <p class="mb-1 small text-muted">Tu descargo (enviado el <?= htmlspecialchars($l['fecha_descargo'] ?? '') ?>):</p>
                        <p class="mb-0"><?= htmlspecialchars($l['descargo']) ?></p>
                    </div>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                            onclick="abrirModalDescargo(<?= (int)$l['llamada_id'] ?>)">
                        ✏️ Actualizar (redactar apelación)
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- MODAL: ACTUALIZAR (redactar descargo/apelación) -->
<div class="modal fade" id="modalDescargo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="llamadas.php">
                <input type="hidden" name="accion" value="enviar_descargo">
                <input type="hidden" name="llamada_id" id="descargo_llamada_id">
                <div class="modal-header">
                    <h5 class="modal-title">✏️ Redactar apelación / descargo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Explica lo sucedido</label>
                    <textarea name="descargo" class="form-control" rows="5" required placeholder="Escribe tu versión de los hechos..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar apelación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalDescargo(llamadaId) {
    document.getElementById('descargo_llamada_id').value = llamadaId;
    new bootstrap.Modal(document.getElementById('modalDescargo')).show();
}
</script>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
