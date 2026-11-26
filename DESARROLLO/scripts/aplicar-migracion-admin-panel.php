<?php
declare(strict_types=1);

require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';

$apply=in_array('--apply',$argv,true);
$environment=$pdo->query('SELECT DATABASE() AS db, @@port AS port, CURRENT_USER() AS account')->fetch(PDO::FETCH_ASSOC);
$database=(string)($environment['db']??'');
if(!str_ends_with(strtolower($database),'_dev')){
    throw new RuntimeException('Este ejecutor solo admite la base de desarrollo. Produccion requiere el procedimiento cPanel documentado.');
}

$grants=$pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN);
$grantText=strtoupper(implode(' ',array_map('strval',$grants)));
$required=['CREATE','ALTER','REFERENCES'];$missing=[];
if(!str_contains($grantText,'ALL PRIVILEGES'))foreach($required as $privilege)if(!str_contains($grantText,$privilege))$missing[]=$privilege;

echo 'Base verificada: '.$database.'; puerto '.(int)$environment['port'].".\n";
echo 'Cuenta conectada: '.(string)$environment['account'].".\n";
if($missing)throw new RuntimeException('La cuenta no posee permisos DDL requeridos: '.implode(', ',$missing).'. Usa una cuenta local exclusiva para migraciones.');
if(!$apply){echo "Preflight correcto. Ejecuta nuevamente con --apply para aplicar la migracion.\n";exit(0);}

$paths=[
    dirname(__DIR__).'/database/migrations/20261125_admin_panel_unificado.sql',
    dirname(__DIR__).'/database/migrations/20261125_opiniones_estados_finales.sql',
    dirname(__DIR__).'/database/migrations/20261125_opiniones_promocion_empresas.sql',
    dirname(__DIR__).'/database/migrations/20261126_normalizar_nombre_empresa.sql',
    dirname(__DIR__).'/database/migrations/20261126_control_secciones.sql',
];
foreach($paths as $path){
    $sql=file_get_contents($path);if($sql===false)throw new RuntimeException('No se pudo leer la migracion '.$path.'.');
    $sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
    $statements=array_values(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[])));
    foreach($statements as $index=>$statement){$pdo->exec($statement);echo basename($path).' · sentencia '.($index+1).' de '.count($statements)." OK.\n";}
}
echo "Migraciones del panel aplicadas en desarrollo.\n";
