<?php
declare(strict_types=1);

require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';

$reference = $argv[1] ?? '';
echo 'DB=' . (string) $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
echo 'CURRENT_USER=' . (string) $pdo->query('SELECT CURRENT_USER()')->fetchColumn() . PHP_EOL;
echo "GRANTS:\n" . implode("\n", array_map('strval', $pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN))) . PHP_EOL;

if ($reference !== '') {
    $stmt = $pdo->prepare('SELECT fecha, evento, entidad, entidad_id, detalle FROM admin_log WHERE detalle LIKE ? ORDER BY id_log DESC LIMIT 5');
    $stmt->execute(['%' . $reference . '%']);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}

try {
    echo json_encode($pdo->query('SHOW COLUMNS FROM secciones_sitio')->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    echo json_encode($pdo->query('SELECT * FROM secciones_sitio ORDER BY orden, id')->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (PDOException $error) {
    echo 'TABLE_ACCESS_ERROR=' . (string) ($error->errorInfo[2] ?? $error->getMessage()) . PHP_EOL;
    exit(2);
}
