<?php
declare(strict_types=1);
$hostHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\\d+$/', '', $hostHeader);
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$productionHosts = ['rpvconstrucciones.com', 'www.rpvconstrucciones.com'];
$productionOrigins = ['https://rpvconstrucciones.com', 'https://www.rpvconstrucciones.com'];
$localOrigins = ['http://localhost:8000', 'http://127.0.0.1:8000'];
$isProduction = in_array($host, $productionHosts, true);
$isLocal = in_array($hostHeader, ['localhost:8000', '127.0.0.1:8000'], true)
    && in_array($remote, ['127.0.0.1', '::1'], true);
if (!$isProduction && !$isLocal) {
    http_response_code(404); exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = $isProduction ? $productionOrigins : $localOrigins;
if (!in_array($origin, $allowedOrigins, true)) respond(403, ['ok' => false, 'message' => 'No se pudo validar el origen del envío. Actualiza la página.']);
if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) respond(415, ['ok' => false, 'message' => 'Formato no permitido.']);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 12000) respond(413, ['ok' => false, 'message' => 'El formulario supera el tamaño permitido.']);
ini_set('session.use_strict_mode', '1');
if ($isLocal) {
    $sessionPath = dirname(__DIR__, 2) . '/.sessions';
    if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) { http_response_code(503); echo '{"ok":false,"message":"No se pudo iniciar la sesion."}'; exit; }
    session_save_path($sessionPath);
}
session_name($isProduction ? 'RPV_OPINIONES' : 'RPV_OPINIONES_DEV');
session_set_cookie_params(['httponly' => true, 'secure' => $isProduction, 'samesite' => 'Lax', 'path' => '/']);
session_start();
function respond(int $status, array $body): never { http_response_code($status); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }
$raw = (string) file_get_contents('php://input', false, null, 0, 12001);
if (strlen($raw) > 12000) respond(413, ['ok' => false, 'message' => 'El formulario supera el tamaño permitido.']);
$data = json_decode($raw, true);
if (!is_array($data)) respond(400, ['ok' => false, 'message' => 'No pudimos leer el formulario. Actualiza la página e inténtalo nuevamente.']);
if (!empty($data['form_field_extra_7c39'])) respond(202, ['ok' => false, 'message' => 'No pudimos procesar el envío.']);
if (empty($_SESSION['opiniones_csrf']) || !is_string($data['csrf'] ?? null) || !hash_equals($_SESSION['opiniones_csrf'], $data['csrf'])) respond(403, ['ok' => false, 'message' => 'La sesión venció. Actualiza la página e inténtalo nuevamente.']);
if (isset($_SESSION['opiniones_last_submit']) && time() - (int)$_SESSION['opiniones_last_submit'] < 30) respond(429, ['ok' => false, 'message' => 'Espera unos segundos antes de enviar otra opinión.']);
$clean = static fn($v): string => trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', (string)$v));
$length = static fn(string $v): int => function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
$name = $clean($data['name'] ?? '');
$email = trim((string)($data['email'] ?? ''));
$city = $clean($data['city'] ?? '');
$companyName = $clean($data['company_name'] ?? $data['company'] ?? '');
$companyUrlInput = trim((string)($data['company_url'] ?? ''));
$product = (string)($data['product'] ?? '');
$comment = $clean($data['comment'] ?? '');
$rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
$companyUrl = null;
$errors = [];
if ($length($name) < 2 || $length($name) > 80) $errors[] = 'El nombre debe tener entre 2 y 80 caracteres.';
if ($length($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ingresa un correo electrónico válido.';
if ($length($city) > 80) $errors[] = 'La ciudad debe tener como máximo 80 caracteres.';
if ($length($companyName) > 120) $errors[] = 'El nombre de la empresa debe tener como máximo 120 caracteres.';
if ($companyUrlInput !== '') {
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $companyUrlInput)) $companyUrlInput = 'https://' . $companyUrlInput;
    $parts = parse_url($companyUrlInput);
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    $hostPart = is_array($parts) ? (string)($parts['host'] ?? '') : '';
    $hasCredentials = is_array($parts) && (isset($parts['user']) || isset($parts['pass']));
    if ($length($companyUrlInput) > 2048 || !filter_var($companyUrlInput, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true) || $hostPart === '' || $hasCredentials) {
        $errors[] = 'Ingresa un sitio web válido que comience con HTTP o HTTPS.';
    } else {
        $companyUrl = $companyUrlInput;
    }
}
if ($companyUrlInput !== '' && $companyName === '') $errors[] = 'Ingresa el nombre de la empresa cuando informes su sitio web.';
if ($companyName !== '' && $companyUrlInput === '') $errors[] = 'Ingresa el sitio web de la empresa cuando informes su nombre.';
$hasBusinessData = $companyName !== '' && $companyUrl !== null;
if (!in_array($product, ['Calderas', 'Tinajas', 'Estufas a pellets'], true)) $errors[] = 'Selecciona un producto de la lista.';
if ($rating === false || $rating < 1 || $rating > 5) $errors[] = 'Selecciona una valoración entre 1 y 5 estrellas.';
if ($length($comment) < 15 || $length($comment) > 1200) $errors[] = 'El comentario debe tener entre 15 y 1.200 caracteres.';
if (($data['consent'] ?? '') !== '1') $errors[] = 'Autoriza la publicación de tu nombre y comentario para continuar.';
if ($errors) respond(422, ['ok' => false, 'message' => implode(' ', $errors)]);
try {
    if ($isProduction) {
        require '/home/rpvconst/conexion_rpv.php';
    } else {
        require dirname(__DIR__, 3) . '/DESARROLLO/BaseDatos/conexion_rpv.php';
    }
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('Conexion PDO no disponible');
    $stmt = $pdo->prepare("INSERT INTO testimonios (nombre_publico, correo_privado, ciudad, nombre_empresa, url_empresa, autoriza_promocion, categoria, calificacion, comentario, consentimiento_publicacion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'borrador')");
    $stmt->execute([$name, $email, $city !== '' ? $city : null, $companyName !== '' ? $companyName : null, $companyUrl, $hasBusinessData ? 1 : 0, $product, $rating, $comment]);
    $_SESSION['opiniones_last_submit'] = time();

    $correoEnviado = false;
    try {
        $moduloNotificaciones = $isProduction
            ? '/home/rpvconst/modulos/notificaciones'
            : dirname(__DIR__, 3) . '/DESARROLLO/modulos/notificaciones';
        require_once $moduloNotificaciones . '/Notificador.php';
        require_once $moduloNotificaciones . '/Plantillas/LayoutCorreo.php';
        require_once $moduloNotificaciones . '/Plantillas/OpinionRegistrada.php';

        $correo = \RPV\Modulos\Notificaciones\Plantillas\OpinionRegistrada::generar($name, $product, (int) $rating, $comment);
        \RPV\Modulos\Notificaciones\Notificador::enviar($email, $correo['asunto'], $correo['texto'], $correo['html'], 'opinion_registrada', ['rpinochetv@rpvconstrucciones.com', 'omaureira@rpvconstrucciones.com']);
        $correoEnviado = true;
    } catch (Throwable $mailError) {
        // El comentario ya está guardado: una falla SMTP no debe provocar un reenvío duplicado.
        error_log('Opiniones RPV: comentario guardado; falló su correo de confirmación. ' . $mailError->getMessage());
    }

    respond(201, [
        'ok' => true,
        'email_sent' => $correoEnviado,
        'message' => $correoEnviado
            ? 'Gracias. Tu opinión es muy importante para nosotros.'
            : 'Tu opinión quedó guardada, pero no pudimos enviar el correo de confirmación.',
    ]);
} catch (Throwable $e) {
    error_log('Opiniones RPV: ' . $e->getMessage());
    respond(503, ['ok' => false, 'message' => 'No pudimos guardar tu opinión. Inténtalo nuevamente más tarde.']);
}
