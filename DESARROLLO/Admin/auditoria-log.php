<?php
declare(strict_types=1);
require __DIR__.'/acceso.php';
require __DIR__.'/componentes/layout.php';
Permissions::require('auditoria.ver');

function auditLabel(string $key):string{return ucfirst(str_replace('_',' ',$key));}
function auditSafeValue(string $key,mixed $value):mixed{
    if(preg_match('/password|contrasena|token|secret|secreto|credential|cookie|authorization/i',$key))return '[dato protegido]';
    if(is_array($value)){foreach($value as $childKey=>$childValue)$value[$childKey]=auditSafeValue((string)$childKey,$childValue);}
    return $value;
}
function auditRender(mixed $value):void{
    if(is_array($value)){if($value===[]){echo '<span class="muted">Sin datos</span>';return;}echo '<dl class="detail-list">';foreach($value as $key=>$child){echo '<div><dt>'.h(auditLabel((string)$key)).'</dt><dd>';auditRender($child);echo '</dd></div>';}echo '</dl>';return;}
    if($value===null){echo '<span class="muted">Sin valor</span>';return;}
    if(is_bool($value)){echo $value?'Si':'No';return;}
    echo '<span class="detail-value">'.nl2br(h((string)$value)).'</span>';
}
function auditUrl(array $changes=[]):string{$query=array_merge($_GET,$changes);foreach($query as $key=>$value)if($value===''||$value===null)unset($query[$key]);return '?'.http_build_query($query);}

$dateFrom=trim((string)($_GET['desde']??''));$dateTo=trim((string)($_GET['hasta']??''));$actor=trim((string)($_GET['usuario']??''));$event=trim((string)($_GET['evento']??''));$entity=trim((string)($_GET['modulo']??''));
$sort=(string)($_GET['orden']??'fecha');$direction=strtolower((string)($_GET['direccion']??'desc'))==='asc'?'asc':'desc';
$sortColumns=['id'=>'id_log','fecha'=>'fecha','usuario'=>'actor_usuario','evento'=>'evento','entidad'=>'entidad','origen'=>'ip'];if(!isset($sortColumns[$sort]))$sort='fecha';
$page=max(1,(int)($_GET['pagina']??1));$perPage=20;
$where=[];$params=[];
if($dateFrom!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)){$where[]='fecha>=?';$params[]=$dateFrom.' 00:00:00';}
if($dateTo!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)){$where[]='fecha<DATE_ADD(?,INTERVAL 1 DAY)';$params[]=$dateTo;}
if($actor!==''){$where[]='actor_usuario=?';$params[]=$actor;}
if($event!==''){$where[]='evento=?';$params[]=$event;}
if($entity!==''){$where[]='entidad=?';$params[]=$entity;}
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
$countStmt=$pdo->prepare('SELECT COUNT(*) FROM admin_log'.$whereSql);$countStmt->execute($params);$total=(int)$countStmt->fetchColumn();
$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
$orderColumn=$sortColumns[$sort];$orderDirection=strtoupper($direction);
$sql='SELECT id_log,fecha,evento,actor_usuario,entidad,entidad_id,detalle,ip FROM admin_log'.$whereSql." ORDER BY $orderColumn $orderDirection, id_log $orderDirection LIMIT $perPage OFFSET $offset";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$events=$pdo->query('SELECT DISTINCT evento FROM admin_log ORDER BY evento')->fetchAll(PDO::FETCH_COLUMN);
$entities=$pdo->query('SELECT DISTINCT entidad FROM admin_log ORDER BY entidad')->fetchAll(PDO::FETCH_COLUMN);
$actors=$pdo->query('SELECT DISTINCT actor_usuario FROM admin_log WHERE actor_usuario<>\'\' ORDER BY actor_usuario')->fetchAll(PDO::FETCH_COLUMN);

$sortLink=static function(string $column,string $label)use($sort,$direction):string{$active=$sort===$column;$next=$active&&$direction==='asc'?'desc':'asc';$indicator=$active?($direction==='asc'?' ↑':' ↓'):'';return '<a class="sort-link'.($active?' active':'').'" href="'.h(auditUrl(['orden'=>$column,'direccion'=>$next,'pagina'=>1])).'">'.h($label).'<span aria-hidden="true">'.$indicator.'</span></a>';};
adminStart('Auditoria','auditoria','Registro de solo lectura de acciones administrativas');
?>
<form class="card filters audit-filters" method="get"><label>Desde<input type="date" name="desde" value="<?=h($dateFrom)?>"></label><label>Hasta<input type="date" name="hasta" value="<?=h($dateTo)?>"></label><label>Usuario<select name="usuario"><option value="">Todos los usuarios</option><?php foreach($actors as $value):?><option value="<?=h($value)?>" <?=$actor===$value?'selected':''?>><?=h($value)?></option><?php endforeach;?></select></label><label>Evento<select name="evento"><option value="">Todos los eventos</option><?php foreach($events as $value):?><option value="<?=h($value)?>" <?=$event===$value?'selected':''?>><?=h(str_replace('_',' ',$value))?></option><?php endforeach;?></select></label><label>Modulo<select name="modulo"><option value="">Todos los modulos</option><?php foreach($entities as $value):?><option value="<?=h($value)?>" <?=$entity===$value?'selected':''?>><?=h($value)?></option><?php endforeach;?></select></label><input type="hidden" name="orden" value="<?=h($sort)?>"><input type="hidden" name="direccion" value="<?=h($direction)?>"><button class="button primary" type="submit">Filtrar</button><a class="button ghost" href="?">Limpiar</a></form>
<div class="section-head"><div><h2>Eventos</h2><p><?=$total?> registro<?=$total===1?'':'s'?> filtrado<?=$total===1?'':'s'?> · Pagina <?=$page?> de <?=$pages?> · 20 por pagina</p></div></div>
<div class="audit-layout"><div><div class="table-wrap"><table class="data-table audit-table"><thead><tr><th><?=$sortLink('fecha','Fecha')?></th><th><?=$sortLink('usuario','Usuario')?></th><th><?=$sortLink('evento','Evento')?></th><th><?=$sortLink('entidad','Entidad')?></th><th>Detalle</th><th><?=$sortLink('origen','Origen')?></th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty">No hay resultados para los filtros seleccionados.</td></tr><?php endif;?><?php foreach($rows as $row):$templateId='audit-detail-'.(int)$row['id_log'];?><tr class="audit-row" tabindex="0" data-detail-template="<?=h($templateId)?>" aria-label="Ver detalle del evento <?=h(str_replace('_',' ',$row['evento']))?>"><td data-label="Fecha"><?=h($row['fecha'])?></td><td data-label="Usuario"><?=h($row['actor_usuario'])?></td><td data-label="Evento"><?=h(str_replace('_',' ',$row['evento']))?></td><td data-label="Entidad"><?=h($row['entidad'])?><?=!empty($row['entidad_id'])?' · '.h($row['entidad_id']):''?></td><td data-label="Detalle"><span class="detail-hint">Ver detalle</span></td><td data-label="Origen"><?=h((string)$row['ip'])?></td></tr><?php $decoded=json_decode((string)($row['detalle']??''),true);$details=is_array($decoded)?auditSafeValue('detalle',$decoded):['descripcion'=>auditSafeValue('descripcion',(string)($row['detalle']??''))];?><template id="<?=h($templateId)?>"><div class="detail-summary"><dl class="detail-list"><div><dt>Evento</dt><dd><?=h(str_replace('_',' ',$row['evento']))?></dd></div><div><dt>Usuario</dt><dd><?=h($row['actor_usuario'])?></dd></div><div><dt>Entidad</dt><dd><?=h($row['entidad'])?><?=!empty($row['entidad_id'])?' · '.h($row['entidad_id']):''?></dd></div><div><dt>Fecha</dt><dd><?=h($row['fecha'])?></dd></div></dl><?php auditRender($details);?></div></template><?php endforeach;?></tbody></table></div>
<nav class="pagination" aria-label="Paginacion de auditoria"><a class="button ghost <?=$page<=1?'disabled':''?>" <?=$page<=1?'aria-disabled="true" tabindex="-1"':'href="'.h(auditUrl(['pagina'=>$page-1])).'"'?>>Anterior</a><span>Pagina <strong><?=$page?></strong> de <strong><?=$pages?></strong></span><a class="button ghost <?=$page>=$pages?'disabled':''?>" <?=$page>=$pages?'aria-disabled="true" tabindex="-1"':'href="'.h(auditUrl(['pagina'=>$page+1])).'"'?>>Siguiente</a></nav></div>
<aside class="audit-detail-panel" id="audit-detail-panel" aria-live="polite" aria-labelledby="audit-detail-title"><div class="detail-panel-head"><h2 id="audit-detail-title">Detalle del evento</h2><button class="button ghost small" type="button" data-close-audit-detail aria-label="Cerrar detalle">Cerrar</button></div><div data-audit-detail-content><p class="muted">Selecciona una fila para consultar su detalle.</p></div></aside></div>
<?php adminEnd();
