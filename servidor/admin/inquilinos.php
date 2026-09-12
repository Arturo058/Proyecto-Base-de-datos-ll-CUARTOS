<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login('admin');
$conn = get_conn();

$form_error   = '';
$form_success = '';

// ==============================================================================
// ACCIONES (procesadas antes de renderizar cualquier HTML)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    // ---- DAR DE ALTA: nuevo inquilino (crea cuenta + expediente) ----
    if ($_POST['accion'] === 'crear_inquilino') {
        $nombre_completo = trim((string)($_POST['nombre_completo'] ?? ''));
        $telefono        = trim((string)($_POST['telefono'] ?? ''));
        $personas        = (int)($_POST['personas'] ?? 1);
        $cuarto_id       = (int)($_POST['cuarto_id'] ?? 0);
        $fecha_inicio    = (string)($_POST['fecha_inicio_contrato'] ?? '');
        $username_nuevo  = trim((string)($_POST['username_nuevo'] ?? ''));
        $password_nuevo  = (string)($_POST['password_nuevo'] ?? '');

        if ($nombre_completo === '' || $cuarto_id <= 0 || $fecha_inicio === '' || $personas <= 0
            || $username_nuevo === '' || strlen($password_nuevo) < 8) {
            $form_error = 'Completa nombre, cuarto, número de personas, fecha de inicio, usuario y una contraseña de al menos 8 caracteres.';
        } else {
            registrar_evento_acid('warning', "🔒 [LOCK] Solicitando bloqueo exclusivo del cuarto_id #$cuarto_id para registrar al inquilino.");
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, 'SELECT estado FROM cuartos WHERE cuarto_id = ? FOR UPDATE');
                mysqli_stmt_bind_param($stmt, 'i', $cuarto_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('No fue posible verificar el cuarto seleccionado.');
                }
                $res_cuarto = mysqli_stmt_get_result($stmt);
                $fila_cuarto = $res_cuarto ? mysqli_fetch_assoc($res_cuarto) : null;
                mysqli_stmt_close($stmt);
                if (!$fila_cuarto || $fila_cuarto['estado'] !== 'Disponible') {
                    throw new RuntimeException('Ese cuarto ya no está disponible.');
                }

                $hash = password_hash($password_nuevo, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (username, password_hash, rol) VALUES (?, ?, 'inquilino')");
                mysqli_stmt_bind_param($stmt, 'ss', $username_nuevo, $hash);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException(mysqli_errno($conn) === 1062 ? 'Ese nombre de usuario ya existe.' : 'No fue posible crear la cuenta.');
                }
                $nuevo_usuario_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($conn, 'INSERT INTO inquilinos (usuario_id, cuarto_id, nombre_completo, telefono, personas, fecha_inicio_contrato) VALUES (?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iissis', $nuevo_usuario_id, $cuarto_id, $nombre_completo, $telefono, $personas, $fecha_inicio);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('No fue posible crear el expediente (verifica que el cuarto exista y esté disponible).');
                }
                mysqli_stmt_close($stmt);

                $estado_ocupado = 'Ocupado';
                $stmt = mysqli_prepare($conn, 'UPDATE cuartos SET estado = ? WHERE cuarto_id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $estado_ocupado, $cuarto_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('No fue posible actualizar el estado del cuarto.');
                }
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                registrar_evento_acid('success', "🚀 [COMMIT] Inquilino \"$nombre_completo\", expediente y cuarto #$cuarto_id guardados correctamente.");
                registrar_evento_acid('info', '🔓 [UNLOCK] Bloqueos liberados al confirmar la transacción.');
                $form_success = "Inquilino \"$nombre_completo\" registrado correctamente.";
            } catch (RuntimeException $e) {
                mysqli_rollback($conn);
                registrar_evento_acid('danger', '💥 [ROLLBACK] Alta cancelada: ' . $e->getMessage());
                registrar_evento_acid('info', '🔓 [UNLOCK] Bloqueos liberados al revertir la transacción.');
                $form_error = $e->getMessage();
            }
        }
    }

    // ---- ACTUALIZAR: datos del inquilino o renovación de contrato ----
    if ($_POST['accion'] === 'actualizar_inquilino') {
        $inquilino_id    = (int)($_POST['inquilino_id'] ?? 0);
        $nombre_completo = trim((string)($_POST['nombre_completo'] ?? ''));
        $telefono        = trim((string)($_POST['telefono'] ?? ''));
        $personas        = (int)($_POST['personas'] ?? 1);
        $fecha_fin       = trim((string)($_POST['fecha_fin_contrato'] ?? ''));

        if ($inquilino_id <= 0 || $nombre_completo === '' || $personas <= 0) {
            $form_error = 'Selecciona un inquilino válido, verifica el nombre y el número de personas.';
        } else {
            registrar_evento_acid('warning', "🔒 [LOCK] Solicitando bloqueo exclusivo del inquilino_id #$inquilino_id para actualizar sus datos.");
            $fecha_fin_valor = $fecha_fin !== '' ? $fecha_fin : null;
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, 'UPDATE inquilinos SET nombre_completo = ?, telefono = ?, personas = ?, fecha_fin_contrato = ? WHERE inquilino_id = ?');
                mysqli_stmt_bind_param($stmt, 'ssisi', $nombre_completo, $telefono, $personas, $fecha_fin_valor, $inquilino_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('No fue posible actualizar los datos del inquilino.');
                }
                mysqli_stmt_close($stmt);
                mysqli_commit($conn);
                registrar_evento_acid('success', "🚀 [COMMIT] Datos del inquilino_id #$inquilino_id actualizados correctamente.");
                registrar_evento_acid('info', '🔓 [UNLOCK] Bloqueo liberado al confirmar la transacción.');
                $form_success = 'Datos del inquilino actualizados / contrato renovado.';
            } catch (RuntimeException $e) {
                mysqli_rollback($conn);
                registrar_evento_acid('danger', '💥 [ROLLBACK] Actualización cancelada: ' . $e->getMessage());
                registrar_evento_acid('info', '🔓 [UNLOCK] Bloqueo liberado al revertir la transacción.');
                $form_error = $e->getMessage();
            }
        }
    }

    // ---- BAJA: desactivar cuenta conservando historial ----
    if ($_POST['accion'] === 'baja_inquilino') {
        $inquilino_id = (int)($_POST['inquilino_id'] ?? 0);
        if ($inquilino_id > 0) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $acid_logs = [
                ['tipo' => 'warning', 'msg' => "🔒 [LOCK] Solicitando bloqueo exclusivo del inquilino_id #$inquilino_id para darlo de baja."],
                ['tipo' => 'info', 'msg' => '⚙️ [TRANSACTION] Conservando el historial y liberando el cuarto asociado.']
            ];
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, 'SELECT cuarto_id FROM inquilinos WHERE inquilino_id = ? FOR UPDATE');
                mysqli_stmt_bind_param($stmt, 'i', $inquilino_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('No fue posible localizar al inquilino.');
                }
                $res  = mysqli_stmt_get_result($stmt);
                $fila = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);

                if (!$fila) {
                    throw new RuntimeException('El inquilino seleccionado no existe.');
                }

                // No se borra el registro: se marca Inactivo para conservar el historial.
                $stmt = mysqli_prepare($conn, 'UPDATE inquilinos SET activo = 0, fecha_fin_contrato = CURDATE() WHERE inquilino_id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $inquilino_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('No fue posible dar de baja al inquilino.');
                }
                mysqli_stmt_close($stmt);

                $estado_disponible = 'Disponible';
                $stmt = mysqli_prepare($conn, 'UPDATE cuartos SET estado = ? WHERE cuarto_id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $estado_disponible, $fila['cuarto_id']);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('No fue posible liberar el cuarto asociado.');
                }
                mysqli_stmt_close($stmt);
                mysqli_commit($conn);
                $acid_logs[] = ['tipo' => 'success', 'msg' => "🚀 [COMMIT] Baja del inquilino_id #$inquilino_id y disponibilidad del cuarto confirmadas."];
                $acid_logs[] = ['tipo' => 'dark', 'msg' => '🔓 [UNLOCK] Bloqueos liberados al finalizar la transacción.'];
            } catch (RuntimeException $e) {
                mysqli_rollback($conn);
                $acid_logs[] = ['tipo' => 'danger', 'msg' => '💥 [ROLLBACK] Baja cancelada: ' . $e->getMessage()];
                $acid_logs[] = ['tipo' => 'info', 'msg' => '🔓 [UNLOCK] Bloqueos liberados al revertir la transacción.'];
            }
            $_SESSION['acid_logs'] = $acid_logs;
        }
        header('Location: inquilinos.php');
        exit;
    }
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['acid_logs']) && is_array($_SESSION['acid_logs'])) {
    foreach ($_SESSION['acid_logs'] as $log) {
        registrar_evento_acid($log['tipo'], $log['msg']);
    }
    unset($_SESSION['acid_logs']);
}

// ==============================================================================
// DATOS PARA LA VISTA
// ==============================================================================
$inquilinos = [];
$res = mysqli_query($conn, "SELECT i.inquilino_id, i.nombre_completo, i.telefono, i.correo, i.personas,
                                    i.fecha_inicio_contrato, i.fecha_fin_contrato, i.activo,
                                    c.cuarto_id, c.numero_cuarto
                             FROM inquilinos i JOIN cuartos c ON c.cuarto_id = i.cuarto_id
                             ORDER BY i.activo DESC, i.nombre_completo ASC");
if ($res) { while ($f = mysqli_fetch_assoc($res)) { $inquilinos[] = $f; } }

$cuartos_disponibles = [];
$res = mysqli_query($conn, "SELECT cuarto_id, numero_cuarto FROM cuartos WHERE estado = 'Disponible' ORDER BY numero_cuarto");
if ($res) { while ($f = mysqli_fetch_assoc($res)) { $cuartos_disponibles[] = $f; } }

$titulo = 'Módulo de Inquilinos';
$activo = 'inquilinos';
require __DIR__ . '/../includes/layout_top.php';
?>

<?php if ($form_error !== ''): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>
<?php if ($form_success !== ''): ?><div class="alert alert-success py-2"><?= htmlspecialchars($form_success) ?></div><?php endif; ?>

<!-- Barra de acción superior -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <input type="text" id="buscadorInquilinos" class="form-control" style="max-width: 320px;"
           placeholder="🔍 Consulta por nombre o número de cuarto..." autocomplete="off">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAlta">➕ Dar de Alta</button>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0" id="tablaInquilinos">
            <thead class="table-light">
                <tr><th>Nombre</th><th>Cuarto</th><th>Personas</th><th>Fecha de inicio</th><th>Estatus</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (empty($inquilinos)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay inquilinos registrados todavía.</td></tr>
            <?php else: foreach ($inquilinos as $i): ?>
                <tr data-nombre="<?= htmlspecialchars(mb_strtolower($i['nombre_completo'])) ?>"
                    data-cuarto="<?= htmlspecialchars(mb_strtolower($i['numero_cuarto'])) ?>">
                    <td><?= htmlspecialchars($i['nombre_completo']) ?></td>
                    <td><?= htmlspecialchars($i['numero_cuarto']) ?></td>
                    <td><?= (int)$i['personas'] ?></td>
                    <td><?= htmlspecialchars($i['fecha_inicio_contrato']) ?></td>
                    <td><span class="badge <?= $i['activo'] ? 'bg-success' : 'bg-secondary' ?>"><?= $i['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="text-end">
                        <?php if ($i['activo']): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="abrirModalActualizar(<?= (int)$i['inquilino_id'] ?>, '<?= htmlspecialchars(addslashes($i['nombre_completo'])) ?>', '<?= htmlspecialchars(addslashes($i['telefono'] ?? '')) ?>', <?= (int)$i['personas'] ?>, '<?= htmlspecialchars($i['fecha_fin_contrato'] ?? '') ?>')">
                                ✏️ Actualizar
                            </button>
                            <form method="post" action="inquilinos.php" class="d-inline"
                                  onsubmit="return confirm('¿Dar de baja a <?= htmlspecialchars(addslashes($i['nombre_completo'])) ?>? El cuarto quedará Disponible y su historial se conservará.');">
                                <input type="hidden" name="accion" value="baja_inquilino">
                                <input type="hidden" name="inquilino_id" value="<?= (int)$i['inquilino_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">🗑 Baja</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">Contrato finalizado</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p id="sinResultados" class="text-muted text-center mt-3 d-none">Ningún inquilino coincide con tu búsqueda.</p>

<!-- ============================== MODAL: DAR DE ALTA ============================== -->
<div class="modal fade" id="modalAlta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="inquilinos.php">
                <input type="hidden" name="accion" value="crear_inquilino">
                <div class="modal-header">
                    <h5 class="modal-title">➕ Registrar nuevo inquilino</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" name="nombre_completo" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="20">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label">Número de personas</label>
                            <input type="number" name="personas" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">Cuarto asignado</label>
                            <select name="cuarto_id" class="form-select" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($cuartos_disponibles as $c): ?>
                                    <option value="<?= (int)$c['cuarto_id'] ?>"><?= htmlspecialchars($c['numero_cuarto']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Fecha de contrato</label>
                        <input type="date" name="fecha_inicio_contrato" class="form-control" required>
                    </div>
                    <hr>
                    <p class="text-muted small mb-2">Credenciales de acceso iniciales</p>
                    <div class="mb-2">
                        <label class="form-label">Usuario</label>
                        <input type="text" name="username_nuevo" class="form-control" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Contraseña (mín. 8 caracteres)</label>
                        <input type="password" name="password_nuevo" class="form-control" minlength="8" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar inquilino</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================== MODAL: ACTUALIZAR ============================== -->
<div class="modal fade" id="modalActualizar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="inquilinos.php">
                <input type="hidden" name="accion" value="actualizar_inquilino">
                <input type="hidden" name="inquilino_id" id="act_inquilino_id">
                <div class="modal-header">
                    <h5 class="modal-title">✏️ Actualizar inquilino</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" name="nombre_completo" id="act_nombre" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="act_telefono" class="form-control" maxlength="20">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Número de personas</label>
                        <input type="number" name="personas" id="act_personas" class="form-control" min="1" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Renovar fecha de fin de contrato</label>
                        <input type="date" name="fecha_fin_contrato" id="act_fecha_fin" class="form-control">
                        <div class="form-text">Déjalo vacío si el contrato sigue vigente indefinidamente.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// CONSULTA: filtro de la tabla en tiempo real (sin recargar la página)
document.getElementById('buscadorInquilinos').addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    const filas = document.querySelectorAll('#tablaInquilinos tbody tr[data-nombre]');
    let visibles = 0;
    filas.forEach(fila => {
        const coincide = fila.dataset.nombre.includes(q) || fila.dataset.cuarto.includes(q);
        fila.classList.toggle('d-none', !coincide);
        if (coincide) visibles++;
    });
    document.getElementById('sinResultados').classList.toggle('d-none', visibles !== 0 || q === '');
});

// ACTUALIZAR: rellena el modal con los datos de la fila seleccionada
function abrirModalActualizar(id, nombre, telefono, personas, fechaFin) {
    document.getElementById('act_inquilino_id').value = id;
    document.getElementById('act_nombre').value = nombre;
    document.getElementById('act_telefono').value = telefono;
    document.getElementById('act_personas').value = personas;
    document.getElementById('act_fecha_fin').value = fechaFin;
    new bootstrap.Modal(document.getElementById('modalActualizar')).show();
}
</script>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
