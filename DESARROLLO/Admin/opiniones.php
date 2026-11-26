<?php
declare(strict_types=1);
require __DIR__.'/acceso.php';
require __DIR__.'/componentes/layout.php';
Permissions::require('opiniones.ver');

$error='';
$allowedStates=['borrador','publicada','eliminada'];
$stateFilter=(string)($_GET['estado']??'todos');
if($stateFilter!=='todos'&&!in_array($stateFilter,$allowedStates,true))$stateFilter='todos';

if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::validate($_POST['csrf']??null);
    Permissions::require('opiniones.publicar');
    $action=(string)($_POST['action']??'');
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $current=null;
    try{
        if(!$id||!in_array($action,['publish','unpublish','delete'],true))throw new InvalidArgumentException('Accion no valida.');
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT estado FROM testimonios WHERE id_testimonio=? FOR UPDATE');
        $stmt->execute([$id]);
        $current=$stmt->fetchColumn();
        if(!is_string($current))throw new InvalidArgumentException('La opinion ya no existe.');
        $transitions=[
            'publish'=>['from'=>'borrador','to'=>'publicada','event'=>'opinion_publicada','message'=>'Opinion publicada.'],
            'unpublish'=>['from'=>'publicada','to'=>'borrador','event'=>'opinion_despublicada','message'=>'Opinion despublicada.'],
            'delete'=>['from'=>['borrador','publicada'],'to'=>'eliminada','event'=>'opinion_eliminada','message'=>'Opinion eliminada logicamente.'],
        ];
        $transition=$transitions[$action];
        $validFrom=is_array($transition['from'])?in_array($current,$transition['from'],true):$current===$transition['from'];
        if(!$validFrom)throw new InvalidArgumentException('La transicion solicitada no esta permitida para el estado actual.');
        $publishedAt=$transition['to']==='publicada'?'COALESCE(publicado_en,NOW())':'NULL';
        $update=$pdo->prepare("UPDATE testimonios SET estado=?,publicado_en=$publishedAt WHERE id_testimonio=? AND estado=?");
        $update->execute([$transition['to'],$id,$current]);
        if($update->rowCount()!==1)throw new RuntimeException('La opinion cambio mientras se procesaba la solicitud.');
        adminAudit($pdo,$transition['event'],'opinion',(string)$id,['estado_anterior'=>$current,'estado_nuevo'=>$transition['to'],'accion'=>$action]);
        $pdo->commit();
        notice($transition['message']);
        $returnState=(string)($_POST['return_state']??'todos');
        go('/opiniones.php?estado='.rawurlencode(in_array($returnState,array_merge(['todos'],$allowedStates),true)?$returnState:'todos'));
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $failure=adminAuditFailure($pdo,$action!==''?$action:'accion_desconocida','opinion',$id?(string)$id:null,$e,[
            'estado_anterior'=>$current,
            'estado_solicitado'=>['publish'=>'publicada','unpublish'=>'borrador','delete'=>'eliminada'][$action]??null,
        ]);
        if($e instanceof InvalidArgumentException)$error=$e->getMessage().' Referencia: '.$failure['reference'].'.';
        elseif($failure['cause']==='esquema_estado_incompatible')$error='La base de datos aun no admite el estado solicitado. Aplica la migracion de estados de opiniones. Referencia: '.$failure['reference'].'.';
        else $error='No se pudo actualizar la opinion. Referencia: '.$failure['reference'].'.';
    }
}

$params=[];$where='';
if($stateFilter!=='todos'){$where=' WHERE estado=?';$params[]=$stateFilter;}
$stmt=$pdo->prepare('SELECT id_testimonio,nombre_publico,correo_privado,ciudad,nombre_empresa,url_empresa,autoriza_promocion,categoria,calificacion,comentario,estado,creado_en,publicado_en FROM testimonios'.$where.' ORDER BY id_testimonio DESC');
$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$counts=array_fill_keys($allowedStates,0);
foreach($pdo->query('SELECT estado,COUNT(*) total FROM testimonios GROUP BY estado') as $countRow){if(isset($counts[$countRow['estado']]))$counts[$countRow['estado']]=(int)$countRow['total'];}

adminStart('Opiniones','opiniones','Consulta, publicacion y eliminacion logica de opiniones');
?>
<?php if($error):?><div class="alert error" role="alert"><?=h($error)?></div><?php endif;?>
<section class="metric-grid metric-grid-three" aria-label="Resumen de opiniones">
  <article class="card metric"><span>Borradores</span><strong><?=$counts['borrador']?></strong></article>
  <article class="card metric"><span>Publicadas</span><strong><?=$counts['publicada']?></strong></article>
  <article class="card metric"><span>Eliminadas</span><strong><?=$counts['eliminada']?></strong></article>
</section>
<form class="card filters filters-compact" method="get">
  <label>Estado<select name="estado"><option value="todos">Todos</option><?php foreach($allowedStates as $state):?><option value="<?=h($state)?>" <?=$stateFilter===$state?'selected':''?>><?=h(ucfirst($state))?></option><?php endforeach;?></select></label>
  <button class="button primary" type="submit">Filtrar</button>
</form>
<div class="section-head"><div><h2>Opiniones registradas</h2><p><?=count($rows)?> resultado<?=count($rows)===1?'':'s'?>. Los datos originales no se pueden editar.</p></div></div>
<div class="table-wrap"><table class="data-table opinions-table"><thead><tr><th>Cliente</th><th>Opinion</th><th>Calificacion</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="5" class="empty">No hay opiniones para este filtro.</td></tr><?php endif;?>
<?php foreach($rows as $row):$state=(string)$row['estado'];$businessName=trim((string)($row['nombre_empresa']??''));?>
<tr class="<?=$state==='eliminada'?'row-disabled':''?>"><td data-label="Cliente"><strong><?=h($row['nombre_publico'])?></strong><br><small><?=h(implode(' · ',array_filter([(string)$row['categoria'],(string)($row['ciudad']??'')],static fn($v)=>$v!=='')))?></small><?php if($businessName!==''):?><div class="opinion-business-admin"><strong>Empresa: <?=h($businessName)?></strong><?php if(!empty($row['url_empresa'])):?><small>Sitio: <?=h((string)$row['url_empresa'])?></small><?php endif;?><small>Promoción autorizada: <?=(int)($row['autoriza_promocion']??0)===1?'Sí':'No'?></small></div><?php endif;?><br><small class="private-data">Correo privado: <?=h((string)($row['correo_privado']??'No informado'))?></small></td><td data-label="Opinion"><span class="opinion-copy"><?=nl2br(h($row['comentario']))?></span><br><small>Registrada: <?=h($row['creado_en'])?></small></td><td data-label="Calificacion"><span class="rating-display" aria-label="<?=(int)$row['calificacion']?> de 5 estrellas"><?=str_repeat('★',(int)$row['calificacion'])?><span><?=str_repeat('★',5-(int)$row['calificacion'])?></span></span></td><td data-label="Estado"><span class="badge <?=$state==='publicada'?'ok':($state==='eliminada'?'off':'warn')?>"><?=h(ucfirst($state))?></span></td><td data-label="Acciones"><div class="actions">
<?php if($state==='borrador'):?><form method="post"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?=(int)$row['id_testimonio']?>"><input type="hidden" name="return_state" value="<?=h($stateFilter)?>"><button class="button primary small" type="submit">Publicar</button></form><?php endif;?>
<?php if($state==='publicada'):?><form method="post"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="unpublish"><input type="hidden" name="id" value="<?=(int)$row['id_testimonio']?>"><input type="hidden" name="return_state" value="<?=h($stateFilter)?>"><button class="button ghost small" type="submit">Despublicar</button></form><?php endif;?>
<?php if($state!=='eliminada'):?><form method="post" data-delete-opinion><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id_testimonio']?>"><input type="hidden" name="return_state" value="<?=h($stateFilter)?>"><button class="button danger small" type="submit">Eliminar</button></form><?php else:?><span class="muted">Sin acciones disponibles</span><?php endif;?>
</div></td></tr><?php endforeach;?></tbody></table></div>
<dialog class="admin-dialog" id="delete-opinion-dialog" aria-labelledby="delete-opinion-title" aria-describedby="delete-opinion-message"><h2 id="delete-opinion-title">Confirmar eliminacion</h2><p id="delete-opinion-message">¿Esta seguro de que desea eliminar esta opinion? La opinion permanecera registrada, pero no podra recuperarse ni realizar nuevas acciones sobre ella.</p><div class="dialog-actions"><button class="button ghost" type="button" data-dialog-cancel>Cancelar</button><button class="button danger" type="button" data-dialog-confirm>Confirmar eliminacion</button></div></dialog>
<?php adminEnd();
