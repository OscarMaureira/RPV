<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

try {
    require __DIR__ . '/conexion_rpv.php';
    echo "CONEXION LOCAL CORRECTA\n";
    echo 'Base de datos: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
    echo 'Puerto MariaDB: ' . $pdo->query('SELECT @@port')->fetchColumn() . "\n";
    echo 'Ubicaciones guardadas: ' . $pdo->query('SELECT COUNT(*) FROM ubicaciones_chat')->fetchColumn() . "\n";
    echo 'Testimonios guardados: ' . $pdo->query('SELECT COUNT(*) FROM testimonios')->fetchColumn() . "\n";
} catch (Throwable $e) {
    error_log('RPV MySQL local: ' . $e->getMessage());
    http_response_code(500);
    echo "ERROR DE CONEXION LOCAL\n";
    echo "Configura BaseDatos/conexion_rpv.local.php.";
}
