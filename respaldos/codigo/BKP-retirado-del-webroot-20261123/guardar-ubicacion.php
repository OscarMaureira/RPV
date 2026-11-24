<?php
declare(strict_types=1);

// API RPV: almacena ubicaciones compartidas voluntariamente.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function output(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    output(405, ['ok' => false, 'error' => 'Metodo no permitido']);
}
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (!in_array($host, ['rpvconstrucciones.com', 'www.rpvconstrucciones.com'], true)
    || !$origin || !in_array($origin, ['https://rpvconstrucciones.com', 'https://www.rpvconstrucciones.com'], true)) {
    output(403, ['ok' => false, 'error' => 'Origen no autorizado']);
}
if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
    output(415, ['ok' => false, 'error' => 'Formato no permitido']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 6000) {
    output(413, ['ok' => false, 'error' => 'Solicitud demasiado grande']);
}
$raw = file_get_contents('php://input', false, null, 0, 6001);
$input = json_decode((string)$raw, true);
if (!is_array($input)) output(400, ['ok'=>false, 'error'=>'JSON invalido']);

function textField(array $input, string $key, int $max): ?string {
    $v = $input[$key] ?? null;
    if ($v === null || $v === '') return null;
    if (!is_string($v) || (function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v)) > $max) output(422, ['ok'=>false, 'error'=>'Campo invalido: '.$key]);
    $v = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v));
    return $v === '' ? null : $v;
}
function numberField(array $input, string $key, float $min, float $max, bool $required): ?float {
    $v = $input[$key] ?? null;
    if ($v === null || $v === '') {
        if ($required) output(422, ['ok'=>false, 'error'=>'Falta '.$key]);
        return null;
    }
    if (!is_numeric($v) || !is_finite((float)$v) || (float)$v < $min || (float)$v > $max) {
        output(422, ['ok'=>false, 'error'=>'Valor invalido: '.$key]);
    }
    return (float)$v;
}
$lat = numberField($input, 'latitud', -90, 90, true);
$lon = numberField($input, 'longitud', -180, 180, true);
$accuracy = numberField($input, 'precision_metros', 0, 999999, false);
$conversation = textField($input, 'id_conversacion', 100);
$comuna = textField($input, 'comuna', 100);
$provincia = textField($input, 'provincia', 100);
$region = textField($input, 'region', 150);
$pais = textField($input, 'pais', 100) ?? 'Chile';
$source = textField($input, 'fuente_ubicacion', 50) ?? 'navegador';
if ($pais !== 'Chile') output(422, ['ok'=>false,'error'=>'Pais fuera de cobertura']);
if (!in_array($source, ['navegador', 'manual'], true)) output(422, ['ok'=>false,'error'=>'Fuente invalida']);
// El enlace se genera en el servidor a partir de las coordenadas validadas.
$maps = 'https://www.google.com/maps?q='.rawurlencode((string)$lat.','.(string)$lon);

try {
    require '/home/rpvconst/conexion_rpv.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('Conexion PDO no disponible');
    // Evitar duplicar la misma ubicacion capturada varias veces en cinco minutos.
    // El ID de conversacion es aleatorio; no es una identidad del visitante.
    if ($conversation !== null) {
        $check = $pdo->prepare('SELECT id_ubicacion FROM ubicaciones_chat WHERE id_conversacion = :conversation AND latitud = :lat AND longitud = :lon AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id_ubicacion DESC LIMIT 1');
        $check->execute(['conversation'=>$conversation, 'lat'=>$lat, 'lon'=>$lon]);
        $previous = $check->fetchColumn();
        if ($previous !== false) output(200, ['ok'=>true,'id_ubicacion'=>(int)$previous,'duplicado'=>true]);
    }
    $stmt = $pdo->prepare('INSERT INTO ubicaciones_chat (id_conversacion, comuna, provincia, region, pais, latitud, longitud, precision_metros, url_google_maps, fuente_ubicacion) VALUES (:conversation, :comuna, :provincia, :region, :pais, :lat, :lon, :accuracy, :maps, :source)');
    $stmt->execute(['conversation'=>$conversation,'comuna'=>$comuna,'provincia'=>$provincia,'region'=>$region,'pais'=>$pais,'lat'=>$lat,'lon'=>$lon,'accuracy'=>$accuracy,'maps'=>$maps,'source'=>$source]);
    output(201, ['ok'=>true,'id_ubicacion'=>(int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    error_log('RPV guardar ubicacion: '.$e->getMessage());
    output(500, ['ok'=>false,'error'=>'No fue posible registrar la ubicacion']);
}
