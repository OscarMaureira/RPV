<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_inc/secciones-publicas.php';
rpvProtegerSeccion('calderas');
readfile(__DIR__ . '/index.html');
