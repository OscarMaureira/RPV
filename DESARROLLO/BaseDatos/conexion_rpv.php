<?php
declare(strict_types=1);

$localConfig = __DIR__ . '/conexion_rpv.local.php';
if (!is_file($localConfig)) {
    throw new RuntimeException(
        'Falta BaseDatos/conexion_rpv.local.php. Copia conexion_rpv.local.php.example y configura la base local.'
    );
}

require $localConfig;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('La configuración local no creó una conexión PDO válida.');
}
