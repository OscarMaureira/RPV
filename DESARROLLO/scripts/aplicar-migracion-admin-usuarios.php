<?php
declare(strict_types=1);
require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/20261123_admin_usuarios_local.sql');
$pdo->exec($sql);
echo "Tabla admin_usuarios disponible.\n";