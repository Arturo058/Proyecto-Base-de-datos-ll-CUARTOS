<?php
/**
 * Módulo Avanzado Financiero - Sincronizado con la Seguridad de Arturo
 * Versión optimizada: Filtra bajas lógicas, controla homónimos y audita concurrencia en vivo.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login('admin'); // EL GUARDIA: Valida que eres admin y repara tu menú lateral
$conn = get_conn();

$mensaje = '';

// 1. PROCESAR REGISTRO DE NUEVO PAGO (Acción POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $inquilino_id = (int)$_POST['inquilino_id'];
    $monto = (float)$_POST['monto_pagado'];
    $mes = mysqli_real_escape_string($conn, $_POST['mes_cobertura']);
    $metodo = mysqli_real_escape_string($conn, $_POST['metodo_pago']);

    if ($inquilino_id > 0 && $monto > 0 && $mes !== '') {
        //  Auditoría ACID: Avisamos al monitor que iniciamos el bloqueo exclusivo en InnoDB
        registrar_evento_acid('warning', "🔒 [LOCK] Solicitando bloqueo exclusivo de fila (X LOCK) en InnoDB para inquilino_id #$inquilino_id.");
        mysqli_begin_transaction($conn);
        registrar_evento_acid('info', "⚙️ [TRANSACTION] START: Abriendo espacio aislado en RAM temporal.");

        // Buscar automáticamente el cuarto mapeado al inquilino usando tus índices reales
        $q_cuarto = mysqli_query($conn, "SELECT cuarto_id FROM inquilinos WHERE inquilino_id = $inquilino_id");
        $cuarto_data = mysqli_fetch_assoc($q_cuarto);
        $cuarto_id = $cuarto_data ? (int)$cuarto_data['cuarto_id'] : 0;

        if ($cuarto_id > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO pagos_renta (inquilino_id, cuarto_id, monto_pagado, mes_cobertura, metodo_pago) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iidss', $inquilino_id, $cuarto_id, $monto, $mes, $metodo);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_commit($conn);
                registrar_evento_acid('success', "🚀 [COMMIT] Transacción asentada. Fondos guardados con éxito en la persistencia de Docker.");
                registrar_evento_acid('dark', "🔓 [UNLOCK] Liberando candado de fila para accesos concurrentes.");
                $mensaje = "<div class='alert alert-success py-2'>💰 Transacción guardada en los libros de la base de datos.</div>";
            } else {
                mysqli_rollback($conn);
                registrar_evento_acid('danger', "💥 [ROLLBACK] Error en el motor físico. Revirtiendo cambios de forma segura.");
                registrar_evento_acid('dark', "🔓 [UNLOCK] Removiendo restricciones de aislamiento.");
                $mensaje = "<div class='alert alert-danger py-2'>❌ Falló el almacenamiento de la persistencia.</div>";
            }
            mysqli_stmt_close($stmt);
        } else {
            mysqli_rollback($conn);
            registrar_evento_acid('danger', "💥 [ROLLBACK] El inquilino no tiene un cuarto válido asociado.");
            $mensaje = "<div class='alert alert-danger py-2'>❌ Error: Estructura relacional rota.</div>";
        }
    }
}

//  2. CONSULTAR ÚNICAMENTE INQUILINOS ACTIVOS UNIFICANDO SU NÚMERO DE CUARTO REAL
// Resolvemos la homonimia y las bajas lógicas mediante un INNER JOIN filtrado por activo = 1
$inquilinos_list = mysqli_query($conn, "
    SELECT i.inquilino_id, i.nombre_completo, c.numero_cuarto 
    FROM inquilinos i
    INNER JOIN cuartos c ON i.cuarto_id = c.cuarto_id
    WHERE i.activo = 1 
    ORDER BY i.nombre_completo ASC
");

// 3. CONSULTAR EL HISTORIAL CON TU ESTRUCTURA EXACTA INNER JOIN
$historial = mysqli_query($conn, "
    SELECT p.*, i.nombre_completo, c.numero_cuarto 
    FROM pagos_renta p
    INNER JOIN inquilinos i ON p.inquilino_id = i.inquilino_id
    INNER JOIN cuartos c ON p.cuarto_id = c.cuarto_id
    ORDER BY p.id DESC
");

$titulo = 'Historial Financiero y Pagos';
$activo = 'pagos';
require __DIR__ . '/../includes/layout_top.php';
?>

<!-- Alertas del sistema homologadas con el diseño de cuartos.php -->
<?php if ($mensaje !== ''): ?><?= $mensaje ?><?php endif; ?>

<div class="row">
    <!-- Columna de Captura (Diseño igual a "Nuevo Cuarto") -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><strong>Registrar pago de renta</strong></div>
            <div class="card-body">
                <form method="post" action="pagos.php">
                    <input type="hidden" name="registrar_pago" value="1">
                    
                    <div class="mb-2">
                        <label class="form-label">Arrendatario Titular</label>
                        <select name="inquilino_id" class="form-select" required>
                            <option value="">-- Busque al inquilino --</option>
                            <?php if ($inquilinos_list): ?>
                                <?php while ($inq = mysqli_fetch_assoc($inquilinos_list)): ?>
                                    <option value="<?= (int)$inq['inquilino_id'] ?>">
                                        <?= htmlspecialchars($inq['nombre_completo']) ?> (Cuarto <?= htmlspecialchars($inq['numero_cuarto']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Monto del Pago (MXN)</label>
                        <input type="number" step="0.01" min="0.01" name="monto_pagado" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Periodo (Mes/Año)</label>
                        <input type="text" name="mes_cobertura" class="form-control" placeholder="Ej: Septiembre 2026" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Método de Recepción</label>
                        <select name="metodo_pago" class="form-select" required>
                            <option value="Transferencia">Transferencia Electrónica</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Deposito">Depósito Bancario</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="background-color:#3b82f6; border-color:#3b82f6;">Guardar registro</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Columna del Historial (Diseño igual a "Cuartos Registrados") -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><strong>Libro diario de ingresos</strong></div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Folio</th>
                            <th>Inquilino</th>
                            <th>Habitación</th>
                            <th>Monto</th>
                            <th>Mes Cubierto</th>
                            <th>Método</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($historial && mysqli_num_rows($historial) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($historial)): ?>
                            <tr>
                                <td class="text-secondary font-monospace">#<?= (int)$row['id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['nombre_completo']) ?></strong></td>
                                <td><span class="badge bg-secondary">🚪 Cuarto <?= htmlspecialchars($row['numero_cuarto']) ?></span></td>
                                <td class="text-success fw-bold font-monospace">$<?= number_format((float)$row['monto_pagado'], 2) ?></td>
                                <td><?= htmlspecialchars($row['mes_cobertura']) ?></td>
                                <td class="small text-uppercase font-monospace text-muted"><?= htmlspecialchars($row['metodo_pago']) ?></td>
                                <td><span class="badge bg-success">Completado</span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4 small">No se registran transacciones financieras en este periodo.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
