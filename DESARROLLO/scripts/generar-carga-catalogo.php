<?php
declare(strict_types=1);
require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
$source = json_decode((string) file_get_contents(dirname(__DIR__) . '/catalogo/productos.json'), true, 512, JSON_THROW_ON_ERROR);
$q = static fn($value): string => $value === null || $value === '' ? 'NULL' : $pdo->quote((string) $value);
$sql = "-- RPV-Target: development\n";
foreach ($source['productos'] as $p) {
    $columns = ['id_producto','nombre','categoria','precio_clp','modo_precio','rendimiento','incluye','instalacion','despacho','garantia','aprobado','revision','actualizado_el'];
    $values = [$p['id'],$p['nombre'],$p['categoria'],$p['precio_clp'],$p['modo_precio'],$p['rendimiento'],$p['incluye'],$p['instalacion'],$p['despacho'],$p['garantia'],(int)$p['aprobado'],bin2hex(random_bytes(16)),null];
    $sql .= 'INSERT INTO catalogo_productos ('.implode(',',$columns).') VALUES ('.implode(',',array_map($q,$values)).') ON DUPLICATE KEY UPDATE id_producto=VALUES(id_producto);'."\n";
    $sql .= 'INSERT INTO productos (id_producto,nombre,categoria) VALUES ('.implode(',',array_map($q,[$p['id'],$p['nombre'],$p['categoria']])).') ON DUPLICATE KEY UPDATE id_producto=VALUES(id_producto);'."\n";
    $sql .= 'INSERT INTO precios (id_producto,precio_clp,estado,link_pago) VALUES ('.implode(',',array_map($q,[$p['id'],$p['precio_clp'],$p['modo_precio'],null])).') ON DUPLICATE KEY UPDATE id_producto=VALUES(id_producto);'."\n";
}
file_put_contents(dirname(__DIR__) . '/database/migrations/20261123_carga_productos.sql', $sql);
echo count($source['productos']), " productos preparados.\n";