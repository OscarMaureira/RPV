<?php
header('Content-Type: text/plain; charset=utf-8');

echo "PHP FUNCIONANDO\n";
echo "Version: " . PHP_VERSION . "\n";
echo "PDO: " . (class_exists('PDO') ? 'SI' : 'NO') . "\n";
echo "PDO MySQL: " . (
    extension_loaded('pdo_mysql') ? 'SI' : 'NO'
) . "\n";