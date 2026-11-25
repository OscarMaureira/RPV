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

// Control geografico: poligonos simplificados de Chile continental e islas australes.
// Fuente cartografica: Natural Earth (escala 1:110m); aproximacion, no limite legal.
// Orden de coordenadas en cada vertice: [longitud, latitud].
function pointInPolygon(float $longitude, float $latitude, array $polygon): bool {
    $inside = false;
    $count = count($polygon);
    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        [$xi, $yi] = $polygon[$i];
        [$xj, $yj] = $polygon[$j];
        if (($yi > $latitude) !== ($yj > $latitude)) {
            $intersection = ($xj - $xi) * ($latitude - $yi) / ($yj - $yi) + $xi;
            if ($longitude < $intersection) $inside = !$inside;
        }
    }
    return $inside;
}
function coordinatesInChile(float $latitude, float $longitude): bool {
    // Territorios insulares no representados en el mapa Natural Earth 1:110m.
    // Ventanas conservadoras: Rapa Nui, Juan Fernandez y Desventuradas.
    $islands = [
        [-27.25, -27.02, -109.55, -109.15], // Rapa Nui
        [-33.83, -33.55, -80.95, -78.65],  // Archipielago Juan Fernandez
        [-26.55, -26.15, -80.25, -79.65],  // Islas Desventuradas
    ];
    foreach ($islands as [$minLat, $maxLat, $minLon, $maxLon]) {
        if ($latitude >= $minLat && $latitude <= $maxLat && $longitude >= $minLon && $longitude <= $maxLon) return true;
    }
    $polygons = [[[-68.63401,-52.63637],[-68.63335,-54.8695],[-67.56244,-54.87001],[-66.95992,-54.89681],[-67.29103,-55.30124],[-68.14863,-55.61183],[-68.63999,-55.58002],[-69.2321,-55.49906],[-69.95809,-55.19843],[-71.00568,-55.05383],[-72.2639,-54.49514],[-73.2852,-53.95752],[-74.66253,-52.83749],[-73.8381,-53.04743],[-72.43418,-53.7154],[-71.10773,-54.07433],[-70.59178,-53.61583],[-70.26748,-52.93123],[-69.34565,-52.5183],[-68.63401,-52.63637]],[[-69.59042,-17.58001],[-69.10025,-18.26013],[-68.96682,-18.98168],[-68.44223,-19.40507],[-68.75717,-20.37266],[-68.21991,-21.49435],[-67.82818,-22.87292],[-67.10667,-22.73592],[-66.98523,-22.98635],[-67.32844,-24.0253],[-68.41765,-24.51855],[-68.386,-26.18502],[-68.5948,-26.50691],[-68.29554,-26.89934],[-69.00123,-27.52121],[-69.65613,-28.45914],[-70.01355,-29.36792],[-69.91901,-30.33634],[-70.53507,-31.36501],[-70.0744,-33.09121],[-69.81478,-33.27389],[-69.81731,-34.19357],[-70.38805,-35.16969],[-70.36477,-36.00509],[-71.12188,-36.65812],[-71.11863,-37.57683],[-70.81466,-38.553],[-71.41352,-38.91602],[-71.68076,-39.80816],[-71.91573,-40.83234],[-71.7468,-42.05139],[-72.1489,-42.25489],[-71.91542,-43.40856],[-71.46406,-43.78761],[-71.79362,-44.20717],[-71.3298,-44.40752],[-71.22278,-44.78424],[-71.65932,-44.97369],[-71.55201,-45.56073],[-71.91726,-46.88484],[-72.44736,-47.73853],[-72.33116,-48.24424],[-72.64825,-48.87862],[-73.41544,-49.31844],[-73.32805,-50.37879],[-72.97575,-50.74145],[-72.30997,-50.67701],[-72.3294,-51.42596],[-71.9148,-52.00902],[-69.49836,-52.14276],[-68.57155,-52.29944],[-69.46128,-52.29195],[-69.94278,-52.53793],[-70.8451,-52.8992],[-71.00633,-53.83325],[-71.42979,-53.85645],[-72.55794,-53.53141],[-73.70276,-52.83507],[-73.70276,-52.83507],[-74.94676,-52.26275],[-75.26003,-51.62935],[-74.97663,-51.0434],[-75.47975,-50.37837],[-75.60802,-48.67377],[-75.18277,-47.71192],[-74.12658,-46.93925],[-75.6444,-46.64764],[-74.69215,-45.76398],[-74.35171,-44.10304],[-73.24036,-44.45496],[-72.7178,-42.38336],[-73.3889,-42.11753],[-73.70134,-43.36578],[-74.33194,-43.22496],[-74.01796,-41.79481],[-73.6771,-39.94221],[-73.21759,-39.25869],[-73.50556,-38.28288],[-73.58806,-37.15628],[-73.16672,-37.12378],[-72.55314,-35.50884],[-71.86173,-33.90909],[-71.43845,-32.4189],[-71.66872,-30.92064],[-71.37008,-30.09568],[-71.48989,-28.86144],[-70.90512,-27.64038],[-70.72495,-25.70592],[-70.40397,-23.629],[-70.09125,-21.39332],[-70.16442,-19.75647],[-70.37257,-18.34798],[-69.85844,-18.09269],[-69.59042,-17.58001]]];
    foreach ($polygons as $polygon) {
        if (pointInPolygon($longitude, $latitude, $polygon)) return true;
    }
    return false;
}

$lat = numberField($input, 'latitud', -90, 90, false);
$lon = numberField($input, 'longitud', -180, 180, false);
$accuracy = numberField($input, 'precision_metros', 0, 999999, false);
$conversation = textField($input, 'id_conversacion', 100);
// UUID generado por el asistente para cada captura; clientes anteriores pueden omitirlo.
$capture = textField($input, 'id_captura', 100);
if ($capture !== null && !preg_match('/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|cap-[0-9]{10,16}-[a-z0-9]+)$/i', $capture)) {
    reject(422, 'Identificador de captura invalido');
}
$comuna = textField($input, 'comuna', 100);
$provincia = textField($input, 'provincia', 100);
$region = textField($input, 'region', 150);
$pais = textField($input, 'pais', 100) ?? 'Chile';
$source = textField($input, 'fuente_ubicacion', 50) ?? 'navegador';
if ($pais !== 'Chile') reject(422, 'Pais fuera de cobertura');
if (!in_array($source, ['navegador', 'manual'], true)) reject(422, 'Fuente invalida');
$hasCoordinates = $lat !== null && $lon !== null;
if (($lat === null) !== ($lon === null)) reject(422, 'Latitud y longitud deben informarse juntas');
if ($hasCoordinates && !coordinatesInChile($lat, $lon)) reject(422, 'Coordenadas fuera de la cobertura geografica de Chile');
if (!$hasCoordinates) {
    if ($source !== 'manual') reject(422, 'La ubicacion sin GPS debe ser manual');
    if ($comuna === null || $region === null) reject(422, 'Indica comuna y region');
    if ($accuracy !== null) reject(422, 'Precision sin coordenadas');
}
$maps = $hasCoordinates ? 'https://www.google.com/maps?q=' . rawurlencode((string)$lat . ',' . (string)$lon) : null;

try {
    require '/home/rpvconst/conexion_rpv.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('Conexion PDO no disponible');
    // Repetir el mismo id_captura devuelve el registro existente.
    // El indice UNIQUE en MySQL resuelve tambien las solicitudes concurrentes.
    if ($capture !== null) {
        $lookup = $pdo->prepare('SELECT id_ubicacion FROM ubicaciones_chat WHERE id_captura = :capture LIMIT 1');
        $lookup->execute(['capture' => $capture]);
        $previous = $lookup->fetchColumn();
        if ($previous !== false) respond(200, ['ok' => true, 'id_ubicacion' => (int)$previous, 'duplicado' => true]);
    }
    // Compatibilidad: conservar el control historico de 5 minutos.
    if ($conversation !== null) {
        if ($hasCoordinates) {
            $check = $pdo->prepare('SELECT id_ubicacion FROM ubicaciones_chat WHERE id_conversacion = :conversation AND latitud = :lat AND longitud = :lon AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id_ubicacion DESC LIMIT 1');
            $check->execute(['conversation' => $conversation, 'lat' => $lat, 'lon' => $lon]);
        } else {
            $check = $pdo->prepare('SELECT id_ubicacion FROM ubicaciones_chat WHERE id_conversacion = :conversation AND latitud IS NULL AND longitud IS NULL AND comuna = :comuna AND region = :region AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id_ubicacion DESC LIMIT 1');
            $check->execute(['conversation' => $conversation, 'comuna' => $comuna, 'region' => $region]);
        }
        $previous = $check->fetchColumn();
        if ($previous !== false) respond(200, ['ok' => true, 'id_ubicacion' => (int)$previous, 'duplicado' => true]);
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO ubicaciones_chat (id_captura, id_conversacion, comuna, provincia, region, pais, latitud, longitud, precision_metros, url_google_maps, fuente_ubicacion) VALUES (:capture, :conversation, :comuna, :provincia, :region, :pais, :lat, :lon, :accuracy, :maps, :source)');
        $stmt->execute(['capture' => $capture, 'conversation' => $conversation, 'comuna' => $comuna, 'provincia' => $provincia, 'region' => $region, 'pais' => $pais, 'lat' => $lat, 'lon' => $lon, 'accuracy' => $accuracy, 'maps' => $maps, 'source' => $source]);
    } catch (PDOException $e) {
        // Solo tratar colision UNIQUE (SQLSTATE 23000) como duplicado si
        // podemos demostrar que el id_captura ya existe.
        if ($capture !== null && $e->getCode() === '23000') {
            $lookup = $pdo->prepare('SELECT id_ubicacion FROM ubicaciones_chat WHERE id_captura = :capture LIMIT 1');
            $lookup->execute(['capture' => $capture]);
            $existing = $lookup->fetchColumn();
            if ($existing !== false) respond(200, ['ok' => true, 'id_ubicacion' => (int)$existing, 'duplicado' => true]);
        }
        throw $e;
    }
    respond(201, ['ok' => true, 'id_ubicacion' => (int)$pdo->lastInsertId(), 'duplicado' => false]);
} catch (Throwable $e) {
    error_log('RPV guardar ubicacion: ' . $e->getMessage());
    reject(500, 'No fue posible registrar la ubicacion');
}
