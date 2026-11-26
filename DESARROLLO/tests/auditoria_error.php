<?php
declare(strict_types=1);

require dirname(__DIR__).'/BaseDatos/conexion_rpv.php';
require dirname(__DIR__).'/Admin/auditoria.php';

function auditCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "OK: $message\n";}

$pdo->beginTransaction();
try{
    $error=new PDOException("SQLSTATE[01000]: Warning: 1265 Data truncated for column 'estado' at row 1");
    $error->errorInfo=['01000',1265,"Data truncated for column 'estado' at row 1"];
    $result=adminAuditFailure($pdo,'publish','opinion','999999',$error,['estado_anterior'=>'borrador','estado_solicitado'=>'publicada']);
    $stmt=$pdo->prepare("SELECT evento,entidad,entidad_id,detalle FROM admin_log WHERE detalle LIKE ? ORDER BY id_log DESC LIMIT 1");
    $stmt->execute(['%'.$result['reference'].'%']);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    auditCheck(is_array($row),'Evento de error insertado en la transaccion');
    $detail=json_decode((string)$row['detalle'],true,512,JSON_THROW_ON_ERROR);
    auditCheck($row['evento']==='opinion_error'&&$row['entidad']==='opinion','Evento identifica el modulo afectado');
    auditCheck($detail['referencia']===$result['reference'],'Referencia de interfaz coincide con Auditoria');
    auditCheck($detail['causa']==='esquema_estado_incompatible','Codigo 1265 identifica incompatibilidad del esquema de estados');
    auditCheck($detail['sqlstate']==='01000'&&(int)$detail['codigo_motor']===1265,'SQLSTATE y codigo del motor quedan disponibles');
    auditCheck($detail['contexto']['estado_anterior']==='borrador'&&$detail['contexto']['estado_solicitado']==='publicada','Contexto conserva la transicion que fallo');
}finally{
    if($pdo->inTransaction())$pdo->rollBack();
}
echo "Prueba revertida; no dejo eventos de prueba persistentes.\n";
