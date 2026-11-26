<?php
declare(strict_types=1);

$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?? 'localhost');
$localHosts = ['localhost', '127.0.0.1', '[::1]'];
$isDevelopment = in_array($host, $localHosts, true);
$private = [];
$privateFile = $isDevelopment ? dirname(__DIR__, 2) . '/BaseDatos/admin_panel.local.php' : '/home/rpvconst/.config/rpv/admin.php';
if (is_file($privateFile)) $private = require $privateFile;

return [
    'environment' => $isDevelopment ? 'development' : 'production',
    'base_path' => $isDevelopment ? '' : '/admin',
    'session_name' => $isDevelopment ? 'RPV_ADMIN_DEV' : 'RPV_ADMIN',
    'idle_timeout' => 1200,
    'absolute_timeout' => 28800,
    'production_hosts' => ['rpvconstrucciones.com', 'www.rpvconstrucciones.com'],
    'production_connection' => '/home/rpvconst/conexion_rpv.php',
    'encryption_key' => (string) ($private['encryption_key'] ?? getenv('RPV_ADMIN_ENCRYPTION_KEY') ?: ''),
];
