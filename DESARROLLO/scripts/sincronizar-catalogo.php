<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/modulos/catalogo/Catalogo.php';
try {
    $catalogo = new \RPV\Catalogo\Catalogo();
    $check = in_array('--check', $argv, true);
    $catalogo->sincronizar($check);
    echo $check ? "Catálogo y artefactos comerciales sincronizados.\n" : "Catálogo, páginas y conocimiento actualizados en DESARROLLO.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
