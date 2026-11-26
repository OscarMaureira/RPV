<?php
declare(strict_types=1);

require dirname(__DIR__).'/BaseDatos/conexion_rpv.php';
require dirname(__DIR__).'/Admin/core/Auth.php';
require dirname(__DIR__).'/Admin/auditoria.php';

function requestCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "OK: $message\n";}

$mode=strtoupper((string)($argv[1]??'GET'));
if(!in_array($mode,['GET','POST'],true))throw new InvalidArgumentException('Modo no valido.');
$account=$pdo->query('SELECT id,usuario FROM admin_usuarios WHERE activo=1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if(!$account)throw new RuntimeException('Se requiere una cuenta administrativa activa para la prueba.');

$_SESSION['admin_user_id']=(int)$account['id'];
$_SESSION['admin_user']=['id'=>(int)$account['id'],'usuario'=>(string)$account['usuario']];
$_SERVER['REQUEST_METHOD']=$mode;
$_SERVER['SCRIPT_NAME']='/opiniones.php';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
if($mode==='GET')$_GET=['estado'=>'borrador'];
else $_POST=['action'=>'publish','id'=>'123','csrf'=>'no-registrar','password'=>'no-registrar'];

$pdo->beginTransaction();
adminAuditRequestStart($pdo);
if($mode==='POST')adminAuditRequestMark('exito',['evento_negocio'=>'opinion_publicada']);

register_shutdown_function(static function()use($pdo,$mode):void{
    $event=$mode==='GET'?'panel_consulta':'panel_accion';
    $stmt=$pdo->prepare('SELECT evento,entidad,entidad_id,detalle FROM admin_log WHERE evento=? ORDER BY id_log DESC LIMIT 1');
    $stmt->execute([$event]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    requestCheck(is_array($row),'Solicitud transversal insertada en Auditoria');
    $detail=json_decode((string)$row['detalle'],true,512,JSON_THROW_ON_ERROR);
    requestCheck($row['entidad']==='modulo_panel'&&$row['entidad_id']==='opiniones','Modulo visitado queda identificado');
    requestCheck($detail['metodo']===$mode&&isset($detail['duracion_ms'],$detail['codigo_http']),'Metodo, duracion y resultado quedan registrados');
    if($mode==='GET')requestCheck(($detail['filtros']['estado']??null)==='borrador','Filtros de consulta quedan registrados');
    else{
        requestCheck(($detail['accion']??null)==='publish'&&($detail['resultado']??null)==='exito','Accion y resultado quedan registrados');
        requestCheck(in_array('id',$detail['campos_enviados'],true)&&!in_array('csrf',$detail['campos_enviados'],true)&&!in_array('password',$detail['campos_enviados'],true),'Campos sensibles quedan excluidos');
    }
    if($pdo->inTransaction())$pdo->rollBack();
    echo "Prueba revertida; no dejo auditoria de prueba persistente.\n";
});
