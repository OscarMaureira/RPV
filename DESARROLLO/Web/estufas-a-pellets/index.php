<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_inc/secciones-publicas.php';
rpvProtegerSeccion('estufas_pellets');
readfile(__DIR__ . '/index.html');
