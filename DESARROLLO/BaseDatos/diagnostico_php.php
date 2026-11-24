
<?php
header('Content-Type: text/plain; charset=utf-8');

$ruta = '/home/rpvconst/conexion_rpv.php';

echo "PHP: " . PHP_VERSION . "\n";

if (!is_file($ruta)) {
    exit("Archivo no encontrado\n");
}

echo "Archivo: ENCONTRADO\n";
echo "Lectura: " .
    (is_readable($ruta) ? "SI" : "NO") . "\n";

echo "PDO MySQL: " .
    (extension_loaded('pdo_mysql') ? "SI" : "NO") . "\n";

echo "Diagnostico inicial completado\n";
