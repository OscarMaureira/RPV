<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, stale-if-error=300');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

require dirname(__DIR__) . '/_inc/secciones-publicas.php';

try {
    echo json_encode(rpvSeccionesPublicas(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('RPV configuracion publica de secciones: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Configuracion temporalmente no disponible'], JSON_UNESCAPED_UNICODE);
}
