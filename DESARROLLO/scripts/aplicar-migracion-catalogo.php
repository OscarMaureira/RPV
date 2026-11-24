<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
$environment = $pdo->query('SELECT DATABASE() AS db, @@port AS port')->fetch(PDO::FETCH_ASSOC);
if (strcasecmp((string)$environment['db'], 'rpvconst_DataMain_dev') !== 0 || (int)$environment['port'] !== 4444) {
    fwrite(STDERR, "Cancelado: la conexión no corresponde a MariaDB local de desarrollo, puerto 4444.\n"); exit(2);
}
try {
    if ($pdo->query("SHOW TABLES LIKE 'catalogo_productos'")->fetchColumn() === false) {
        $pdo->exec((string)file_get_contents(dirname(__DIR__) . '/database/migrations/20261118_catalogo_productos_local.sql'));
    }
    $source = json_decode((string)file_get_contents(dirname(__DIR__) . '/catalogo/productos.json'), true, 512, JSON_THROW_ON_ERROR);
    $insert = $pdo->prepare('INSERT INTO catalogo_productos (id_producto,nombre,categoria,precio_clp,modo_precio,rendimiento,incluye,instalacion,despacho,garantia,aprobado,revision,actualizado_el) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id_producto=VALUES(id_producto)');
    $pdo->beginTransaction();
    foreach ($source['productos'] as $p) {
        $updated = empty($p['actualizado_el']) ? null : (new DateTimeImmutable($p['actualizado_el']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $insert->execute([$p['id'],$p['nombre'],$p['categoria'],$p['precio_clp'],$p['modo_precio'],$p['rendimiento'],$p['incluye'],$p['instalacion'],$p['despacho'],$p['garantia'],(int)$p['aprobado'],bin2hex(random_bytes(16)),$updated]);
    }
    $pdo->exec('INSERT INTO productos (id_producto,nombre,categoria) SELECT id_producto,nombre,categoria FROM catalogo_productos ON DUPLICATE KEY UPDATE id_producto=VALUES(id_producto)');
    $pdo->exec('INSERT INTO precios (id_producto,precio_clp,estado) SELECT id_producto,precio_clp,modo_precio FROM catalogo_productos ON DUPLICATE KEY UPDATE id_producto=VALUES(id_producto)');
    $pdo->commit();
    echo 'Tabla catalogo_productos preparada: ', $pdo->query('SELECT COUNT(*) FROM catalogo_productos')->fetchColumn(), " productos. Los valores existentes se conservan.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof PDOException && (in_array((int)($e->errorInfo[1] ?? 0), [1142,1044,1045], true))) {
        fwrite(STDERR, "El usuario local no tiene permiso para crear o cargar la tabla. Aplica database/migrations/20261118_catalogo_productos_local.sql con un administrador de MariaDB local (4444) y vuelve a ejecutar este script.\n");
    } else { fwrite(STDERR, 'No se pudo preparar el catálogo: ' . $e->getMessage() . "\n"); }
    exit(1);
}
