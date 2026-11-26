<?php
declare(strict_types=1);

use RPV\Secciones\SeccionesSitio;

function rpvSeccionesPublicas(): array
{
    $cacheKey = 'rpv_secciones_publicas_v1';
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cacheKey, $success);
        if ($success && is_array($cached)) return $cached;
    }

    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $isProduction = in_array($host, ['rpvconstrucciones.com', 'www.rpvconstrucciones.com'], true);
    if ($isProduction) {
        require '/home/rpvconst/conexion_rpv.php';
        require_once '/home/rpvconst/modulos/secciones/SeccionesSitio.php';
    } else {
        require dirname(__DIR__, 2) . '/BaseDatos/conexion_rpv.php';
        require_once dirname(__DIR__, 2) . '/modulos/secciones/SeccionesSitio.php';
    }
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('Conexion PDO no disponible.');

    $states = (new SeccionesSitio($pdo))->estadosPublicos();
    if (function_exists('apcu_store')) @apcu_store($cacheKey, $states, 30);
    return $states;
}

function rpvProtegerSeccion(string $key): void
{
    try {
        $states = rpvSeccionesPublicas();
        if (array_key_exists($key, $states) && $states[$key] === false) {
            header('Location: /', true, 302);
            exit;
        }
    } catch (Throwable $error) {
        // Ante una falla de MySQL se conserva la disponibilidad actual del sitio.
        error_log('RPV control de secciones: ' . $error->getMessage());
    }
}
