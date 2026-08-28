<?php
/**
 * Conexión a la base de datos usando el usuario de mínimo privilegio.
 */

const DB_HOST = 'mi_gestor_bd';
const DB_USER = 'web_user';
const DB_PASS = 'CuartosSeguros2026!';
const DB_NAME = 'renta_cuartos_db';

function get_conn(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn === false) {
            http_response_code(503);
            die('<div style="font-family:sans-serif;padding:2rem;color:#b02a37;">
                    <h2>Servicio no disponible</h2>
                    <p>No fue posible conectar con la base de datos. Intenta nuevamente en unos segundos.</p>
                 </div>');
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }

    return $conn;
}
