<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login('admin');
$conn = get_conn();

$form_error   = '';
$form_success = '';
$ESTADOS_CUARTO = ['Disponible', 'Ocupado', 'Mantenimiento'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'crear_cuarto') {
        $numero_cuarto  = trim((string)($_POST['numero_cuarto'] ?? ''));
        $precio_mensual = (string)($_POST['precio_mensual'] ?? '');
        $estado         = (string)($_POST['estado'] ?? 'Disponible');

        if ($numero_cuarto === '' || !is_numeric($precio_mensual) || (float)$precio_mensual <= 0) {
            $form_error = 'Verifica el número de cuarto y que el precio sea un valor numérico mayor a 0.';
        } elseif (!in_array($estado, $ESTADOS_CUARTO, true)) {
            $form_error = 'Estado inválido.';
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO cuartos (numero_cuarto, precio_mensual, estado) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sds', $numero_cuarto, $precio_mensual, $estado);
            if (mysqli_stmt_execute($stmt)) {
                $form_success = "Cuarto \"$numero_cuarto\" registrado.";
            } else {
                $form_error = (mysqli_errno($conn) === 1062) ? 'Ya existe un cuarto con ese número.' : 'No fue posible registrar el cuarto.';
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($_POST['accion'] === 'cambiar_estado') {
        $cuarto_id = (int)($_POST['cuarto_id'] ?? 0);
        $estado    = (string)($_POST['nuevo_estado'] ?? '');
        if ($cuarto_id > 0 && in_array($estado, $ESTADOS_CUARTO, true)) {
            $stmt = mysqli_prepare($conn, 'UPDATE cuartos SET estado = ? WHERE cuarto_id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $estado, $cuarto_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: cuartos.php');
        exit;
    }

    if ($_POST['accion'] === 'eliminar_cuarto') {
        $cuarto_id = (int)($_POST['cuarto_id'] ?? 0);
        if ($cuarto_id > 0) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM cuartos WHERE cuarto_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $cuarto_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: cuartos.php');
        exit;
    }
}

$cuartos = [];
$res = mysqli_query($conn, 'SELECT cuarto_id, numero_cuarto, precio_mensual, estado FROM cuartos ORDER BY numero_cuarto');
if ($res) { while ($f = mysqli_fetch_assoc($res)) { $cuartos[] = $f; } }

$titulo = 'Cuartos';
$activo = 'cuartos';
require __DIR__ . '/../includes/layout_top.php';
?>

<?php if ($form_error !== ''): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>
<?php if ($form_success !== ''): ?><div class="alert alert-success py-2"><?= htmlspecialchars($form_success) ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Nuevo cuarto</strong></div>
            <div class="card-body">
                <form method="post" action="cuartos.php">
                    <input type="hidden" name="accion" value="crear_cuarto">
                    <div class="mb-2">
                        <label class="form-label">Número de cuarto</label>
                        <input type="text" name="numero_cuarto" class="form-control" required maxlength="20">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Precio mensual (MXN)</label>
                        <input type="number" name="precio_mensual" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado inicial</label>
                        <select name="estado" class="form-select">
                            <?php foreach ($ESTADOS_CUARTO as $o): ?><option value="<?= $o ?>"><?= $o ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Guardar cuarto</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Cuartos registrados</strong></div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-light"><tr><th>Número</th><th>Precio mensual</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($cuartos as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['numero_cuarto']) ?></td>
                            <td>$<?= number_format((float)$c['precio_mensual'], 2) ?></td>
                            <td><span class="badge <?= claseBadgeEstado($c['estado']) ?>"><?= htmlspecialchars($c['estado']) ?></span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm mb-1">
                                    <?php foreach ($ESTADOS_CUARTO as $o): if ($o !== $c['estado']): ?>
                                        <form method="post" action="cuartos.php" class="d-inline">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="cuarto_id" value="<?= (int)$c['cuarto_id'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $o ?>">
                                            <button type="submit" class="btn btn-outline-secondary"><?= $o ?></button>
                                        </form>
                                    <?php endif; endforeach; ?>
                                </div>
                                <form method="post" action="cuartos.php" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar el cuarto <?= htmlspecialchars(addslashes($c['numero_cuarto'])) ?>?');">
                                    <input type="hidden" name="accion" value="eliminar_cuarto">
                                    <input type="hidden" name="cuarto_id" value="<?= (int)$c['cuarto_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
