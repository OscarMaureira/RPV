<?php
declare(strict_types=1);

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

require dirname(__DIR__, 2) . '/modulos/catalogo/Catalogo.php';

try {
    $catalogo = new \RPV\Catalogo\Catalogo(dirname(__DIR__, 2));
    $artefactos = $catalogo->artefactos($catalogo->leer());
    $rutaPublica = str_replace('\\', '/', dirname(__DIR__) . '/assets/data/catalogo-rpv.json');
    $contenido = null;
    foreach ($artefactos as $ruta => $generado) {
        if (str_replace('\\', '/', $ruta) === $rutaPublica) {
            $contenido = $generado;
            break;
        }
    }
    if ($contenido === null) {
        throw new RuntimeException('No se pudo generar el catálogo público.');
    }
    echo $contenido;
} catch (Throwable $error) {
    error_log('RPV catálogo público: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Catálogo temporalmente no disponible'], JSON_UNESCAPED_UNICODE);
}
