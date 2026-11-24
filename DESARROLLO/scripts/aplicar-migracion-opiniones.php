<?php
declare(strict_types=1);

$configuredHost = getenv('RPV_DB_HOST') ?: '127.0.0.1';
$configuredPort = getenv('RPV_DB_PORT') ?: '4444';
$configuredDatabase = getenv('RPV_DB_NAME') ?: 'rpvconst_DataMain_dev';
if (!in_array($configuredHost, ['127.0.0.1', 'localhost', '::1'], true)
    || $configuredPort !== '4444'
    || $configuredDatabase !== 'rpvconst_DataMain_dev') {
    fwrite(STDERR, "Migración cancelada: la conexión configurada no es la base MariaDB local de desarrollo.\n");
    exit(2);
}

require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
$portQuery = $pdo->query('SELECT @@port');
if ((string) $portQuery->fetchColumn() !== '4444') {
    fwrite(STDERR, "Migración cancelada: MariaDB no respondió en el puerto de desarrollo 4444.\n");
    exit(2);
}

$exists = $pdo->query("SHOW TABLES LIKE 'opiniones_productos'")->fetchColumn();
if ($exists !== false) {
    echo "La tabla opiniones_productos ya existe en rpvconst_DataMain_dev. No se requiere otra acción.\n";
    exit(0);
}

$migration = dirname(__DIR__) . '/database/migrations/20261112_opiniones_productos.sql';
$sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($migration));
try {
    $pdo->exec(trim((string) $sql));
} catch (PDOException $e) {
    if ((string) $e->getCode() === '42000') {
        fwrite(STDERR, "La base de desarrollo respondió, pero el usuario rpv_local no tiene permiso CREATE. Ejecuta el SQL de database/migrations/20261112_opiniones_productos.sql con la cuenta root de MariaDB local (puerto 4444).\n");
        exit(3);
    }
    throw $e;
}
echo "Migración de opiniones aplicada en rpvconst_DataMain_dev (MariaDB local, puerto 4444).\n";
