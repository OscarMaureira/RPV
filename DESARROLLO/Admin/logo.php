<?php
declare(strict_types=1);
$path = dirname(__DIR__) . '/Web/assets/images/logos/logo-rpv-construcciones.webp';
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: image/webp');
header('Cache-Control: public, max-age=3600');
readfile($path);