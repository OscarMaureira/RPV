<?php
declare(strict_types=1);
$adminHome = $adminHome ?? '/';
$adminArea = $adminHome === '/precios.php' ? 'Precios y condiciones' : ($adminHome === '/cuentas.php' ? 'Cuentas de administración' : 'Gestor de testimonios');
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if (!in_array($remote, ['127.0.0.1', '::1'], true) || !in_array($host, ['localhost:8002', '127.0.0.1:8002', '[::1]:8002'], true)) {
    http_response_code(403);
    exit('Acceso disponible solo en el equipo local.');
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
ini_set('session.use_strict_mode', '1');
session_name('RPV_ADMIN_DEV');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'path' => '/']);
session_start();
if (isset($_SESSION['admin_last_active']) && time() - (int) $_SESSION['admin_last_active'] > 1800) {
    unset($_SESSION['admin_user_id'], $_SESSION['admin_last_active']);
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function textLength(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function cleanText(string $value): string { return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $value)); }
function go(string $location = '/'): never { header('Location: ' . $location, true, 303); exit; }
function notice(string $message, string $kind = 'ok'): void { $_SESSION['notice'] = ['message' => $message, 'kind' => $kind]; }
require_once __DIR__ . '/auditoria.php';
$accessError = '';
$authAction = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($authAction, ['login', 'logout'], true)) {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('La sesión caducó. Actualiza la página.');
    }
    if ($authAction === 'logout') {
        unset($_SESSION['admin_user_id'], $_SESSION['admin_last_active']);
        session_regenerate_id(true);
        go('https://www.rpvconstrucciones.com/');
    }
    try {
        require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
        $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_usuarios WHERE usuario = ? AND activo = 1 LIMIT 1');
        $stmt->execute([trim((string) ($_POST['username'] ?? ''))]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($account && password_verify((string) ($_POST['password'] ?? ''), (string) $account['password_hash'])) {
            adminAudit($pdo, 'inicio_sesion', 'modulo', $adminHome, [], (int) $account['id'], trim((string) $_POST['username']));
            session_regenerate_id(true);
            $_SESSION['admin_user_id'] = (int) $account['id'];
            $_SESSION['admin_password_stamp'] = hash('sha256', (string) $account['password_hash']);
            $_SESSION['admin_last_active'] = time();
            go($adminHome);
        }
        $accessError = 'El usuario o la contraseña no son correctos.';
    } catch (Throwable $e) {
        error_log('RPV autenticación: ' . $e->getMessage());
        http_response_code(503);
        $accessError = 'No se pudo consultar la base local o falta la tabla admin_usuarios.';
    }
}
$isAdmin = false;
if (!empty($_SESSION['admin_user_id'])) {
    try {
        if (!isset($pdo)) require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
        $stmt = $pdo->prepare('SELECT password_hash FROM admin_usuarios WHERE id = ? AND activo = 1 LIMIT 1');
        $stmt->execute([(int) $_SESSION['admin_user_id']]);
        $currentHash = $stmt->fetchColumn();
        $isAdmin = is_string($currentHash) && isset($_SESSION['admin_password_stamp']) && hash_equals(hash('sha256', $currentHash), (string) $_SESSION['admin_password_stamp']);
    } catch (Throwable $e) {
        error_log('RPV sesión: ' . $e->getMessage());
        http_response_code(503);
        $accessError = 'No se pudo consultar la base local o falta la tabla admin_usuarios.';
    }
}
if ($isAdmin) {
    $_SESSION['admin_last_active'] = time();
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($adminHome, ['/cuentas.php', '/precios.php'], true)) {
        try {
            adminAudit($pdo, 'acceso_modulo', 'modulo', $adminHome);
        } catch (Throwable $e) {
            error_log('RPV auditoría acceso: ' . $e->getMessage());
            http_response_code(503);
            exit('No se pudo registrar el acceso al módulo.');
        }
    }
}
if (!$isAdmin) {
    unset($_SESSION['admin_user_id'], $_SESSION['admin_last_active']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $authAction !== 'login') {
        http_response_code(403);
        exit('Acceso denegado.');
    }
    ?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Acceso de administrador · RPV Construcciones</title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="access-page">
<header class="topbar"><a class="brand brand-logo" href="/" aria-label="RPV Construcciones"><img src="/logo.php" alt="Logo RPV Construcciones"></a></header>
<main class="access-shell">
<div class="access-intro"><span class="eyebrow">ACCESO PRIVADO · DESARROLLO</span><h1>Bienvenido de nuevo.</h1><p>Ingresa para administrar <?= h($adminArea === 'Precios y condiciones' ? 'los precios y las condiciones comerciales' : 'los testimonios de clientes') ?>.</p></div>
<section class="access-card" aria-labelledby="access-title"><span class="access-icon" aria-hidden="true">✦</span><h2 id="access-title">Ingresar al gestor</h2><p>Ingresa tu usuario y contraseña.</p>
<?php if ($accessError): ?><div class="alert alert-error" role="alert"><?= h($accessError) ?></div><?php endif; ?>
<form method="post" action="<?= h($adminHome) ?>"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="login">
<label>Usuario<input type="text" name="username" required maxlength="80" autocomplete="username"></label>
<label>Contraseña<input type="password" name="password" required maxlength="128" autocomplete="current-password"></label>
<button class="primary access-submit" type="submit">Entrar al gestor <span aria-hidden="true">→</span></button></form></section>
<p class="access-note">Disponible únicamente en este equipo mediante <strong>localhost:8002</strong>.</p>
</main></body></html>
<?php
    exit;
}