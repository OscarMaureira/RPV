<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
$rows = $pdo->query('SELECT evento, entidad, detalle FROM admin_log ORDER BY id_log DESC LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $hasSecret = preg_match('/password_hash|\\$2[ayb]\\$/i', (string) $row['detalle']) === 1;
    echo $row['evento'], ' / ', $row['entidad'], $hasSecret ? ' ERROR: secreto en detalle' : ' / sin secreto', PHP_EOL;
}