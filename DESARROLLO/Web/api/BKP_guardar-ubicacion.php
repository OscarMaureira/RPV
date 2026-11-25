<?php
declare(strict_types=1);

// RPV Construcciones - API de ubicaciones, revision de seguridad 2026-11-25.
// Compatible con /assets/js/asistente-rpv.js y asistente-rpv-calderas.js.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function reject(int $status, string $message): void {
    respond($status, ['ok' => false, 'error' => $message]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    reject(405, 'Metodo no permitido');
}

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (!in_array($host, ['rpvconstrucciones.com', 'www.rpvconstrucciones.com'], true)
    || !in_array($origin, ['https://rpvconstrucciones.com', 'https://www.rpvconstrucciones.com'], true)) {
    reject(403, 'Origen no autorizado');
}
// Origin es una defensa para navegadores, NO autentica clientes externos.
$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if (!preg_match('~^application/json(?:\s*;\s*charset\s*=\s*"?utf-8"?)?\s*$~i', $contentType)) {
    reject(415, 'Formato no permitido');
}
$length = $_SERVER['CONTENT_LENGTH'] ?? null;
if ($length !== null && (!ctype_digit((string)$length) || (int)$length > 6000)) {
    reject(413, 'Solicitud demasiado grande');
}

// Limite compartido entre procesos PHP, por IP de conexion (no confiar en X-Forwarded-For).
// Almacenamiento privado fuera de public_html. Si falla, no se permite la escritura en MySQL.
function enforceRateLimit(): void {
    $dir = '/home/rpvconst/tmp/rpv_api_rate_limit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        error_log('RPV API: no se pudo crear directorio privado de limites');
        reject(503, 'Servicio temporalmente no disponible');
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        reject(403, 'Conexion no valida');
    }
    $path = $dir . '/' . hash('sha256', $ip) . '.json';
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        error_log('RPV API: no se pudo abrir contador');
        reject(503, 'Servicio temporalmente no disponible');
    }
    @chmod($path, 0600);
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        reject(503, 'Servicio temporalmente no disponible');
    }
    $now = time();
    $saved = stream_get_contents($fp);
    $timestamps = json_decode((string)$saved, true);
    if (!is_array($timestamps)) $timestamps = [];
    $timestamps = array_values(array_filter($timestamps, static function ($t) use ($now): bool {
        return is_int($t) && $t > $now - 600 && $t <= $now;
    }));
    $limited = count($timestamps) >= 20;
    if (!$limited) $timestamps[] = $now;
    rewind($fp);
    $written = fwrite($fp, json_encode($timestamps));
    $truncated = $written !== false && ftruncate($fp, ftell($fp));
    $flushed = $truncated && fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if (!$flushed) {
        error_log('RPV API: fallo al guardar contador');
        reject(503, 'Servicio temporalmente no disponible');
    }
    if ($limited) {
        header('Retry-After: 600');
        reject(429, 'Demasiadas solicitudes. Intenta mas tarde');
    }
}
enforceRateLimit();

$raw = file_get_contents('php://input', false, null, 0, 6001);
if ($raw === false || $raw === '' || strlen($raw) > 6000) reject(413, 'Solicitud vacia o demasiado grande');
try {
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    reject(400, 'JSON invalido');
}
// JSON debe ser un objeto, no una lista.
if (!is_array($input) || array_is_list($input)) reject(400, 'JSON invalido');

function textField(array $input, string $key, int $max): ?string {
    $value = $input[$key] ?? null;
    if ($value === null || $value === '') return null;
    if (!is_string($value) || !preg_match('//u', $value)) reject(422, 'Campo invalido: ' . $key);
    $value = trim((string)preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value));
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
    if ($length === false || $length > $max) reject(422, 'Campo invalido: ' . $key);
    return $value === '' ? null : $value;
}
function numberField(array $input, string $key, float $min, float $max, bool $required): ?float {
    $value = $input[$key] ?? null;
    if ($value === null || $value === '') {
        if ($required) reject(422, 'Falta ' . $key);
        return null;
    }
    if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
        reject(422, 'Valor invalido: ' . $key);
    }
    $number = (float)$value;
    if (!is_finite($number) || $number < $min || $number > $max) reject(422, 'Valor invalido: ' . $key);
    return $number;
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
if ($pais !== 'Chile') reject(422, 'Pais fuera de cobertura');
if (!in_array($source, ['navegador', 'manual'], true)) reject(422, 'Fuente invalida');
$maps = 'https://www.google.com/maps?q=' . rawurlencode((string)$lat . ',' . (string)$lon);

try {
    require '/home/rpvconst/conexion_rpv.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('Conexion PDO no disponible');
    if ($conversation !== null) {
        $check = $pdo->prepare('SELECT id_ubicacion FROM ubicaciones_chat WHERE id_conversacion = :conversation AND latitud = :lat AND longitud = :lon AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id_ubicacion DESC LIMIT 1');
        $check->execute(['conversation' => $conversation, 'lat' => $lat, 'lon' => $lon]);
        $previous = $check->fetchColumn();
        if ($previous !== false) respond(200, ['ok' => true, 'id_ubicacion' => (int)$previous, 'duplicado' => true]);
    }
    $stmt = $pdo->prepare('INSERT INTO ubicaciones_chat (id_conversacion, comuna, provincia, region, pais, latitud, longitud, precision_metros, url_google_maps, fuente_ubicacion) VALUES (:conversation, :comuna, :provincia, :region, :pais, :lat, :lon, :accuracy, :maps, :source)');
    $stmt->execute(['conversation' => $conversation, 'comuna' => $comuna, 'provincia' => $provincia, 'region' => $region, 'pais' => $pais, 'lat' => $lat, 'lon' => $lon, 'accuracy' => $accuracy, 'maps' => $maps, 'source' => $source]);
    respond(201, ['ok' => true, 'id_ubicacion' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    error_log('RPV guardar ubicacion: ' . $e->getMessage());
    reject(500, 'No fue posible registrar la ubicacion');
}
