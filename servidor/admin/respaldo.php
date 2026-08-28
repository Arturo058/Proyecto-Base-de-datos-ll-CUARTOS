<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('admin');

// ---- RESPALDO: generar y descargar el dump SQL completo (Admin exclusivo) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'respaldo_bd') {
    $root_pass = getenv('DB_ROOT_PASSWORD') ?: 'R00t_S3guro_Cu4rtos_2026!';
    $comando = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --skip-ssl --single-transaction --routines --triggers %s',
        escapeshellarg(DB_HOST),
        escapeshellarg('root'),
        escapeshellarg($root_pass),
        escapeshellarg(DB_NAME)
    );

    // Usamos proc_open (en vez de shell_exec) para poder leer POR SEPARADO
    // la salida del respaldo (stdout), el mensaje de error real (stderr) y
    // el código de salida del proceso. Así, si algo falla, sabemos exactamente
    // qué fue en lugar de solo "no funcionó".
    $descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proceso = proc_open($comando, $descriptores, $pipes);

    $dump          = '';
    $error_mysql   = '';
    $codigo_salida = -1;

    if (is_resource($proceso)) {
        $dump        = stream_get_contents($pipes[1]);
        $error_mysql = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo_salida = proc_close($proceso);
    }

    if ($codigo_salida === 0 && trim($dump) !== '') {
        $nombre_archivo = 'respaldo_renta_cuartos_' . date('Y-m-d_His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
        header('Content-Length: ' . strlen($dump));
        echo $dump;
        exit;
    }

    $form_error = 'No fue posible generar el respaldo. Detalle: ' . htmlspecialchars(trim($error_mysql) ?: 'sin salida de error (revisa que mysqldump esté instalado).');
}

$titulo = 'Sistema / Respaldo';
$activo = 'respaldo';
require __DIR__ . '/../includes/layout_top.php';
?>

<?php if (!empty($form_error)): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>

<div class="card shadow-sm" style="max-width: 520px;">
    <div class="card-header bg-white"><strong>💾 Respaldo completo de la base de datos</strong></div>
    <div class="card-body">
        <p class="text-muted">Al presionar el botón, el sistema genera y descarga automáticamente un archivo <code>.sql</code> con el respaldo completo de <code>renta_cuartos_db</code> (equivalente a <code>mysqldump</code>).</p>
        <form method="post" action="respaldo.php">
            <input type="hidden" name="accion" value="respaldo_bd">
            <button type="submit" class="btn btn-dark w-100">Descargar respaldo (.sql)</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
