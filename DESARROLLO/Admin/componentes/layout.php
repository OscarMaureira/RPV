<?php
declare(strict_types=1);

function adminIcon(string $name): string
{
    $icons=['home'=>'⌂','message'=>'◇','users'=>'♙','tag'=>'$','shield'=>'◈','settings'=>'⚙'];
    return $icons[$name]??'•';
}
function adminStart(string $title,string $active,string $subtitle=''): void
{
    global $modules,$config;
    $user=Auth::user();
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><title><?=h($title)?> · RPV Construcciones</title><link rel="stylesheet" href="<?=h(url('/assets/panel.css'))?>"><script src="<?=h(url('/assets/panel.js'))?>" defer></script></head><body class="admin-app">
    <a class="skip-link" href="#contenido">Saltar al contenido</a><div class="app-grid">
    <aside class="sidebar" id="sidebar"><a class="panel-brand" href="<?=h(url('/index.php'))?>"><img src="<?=h(url('/logo.php'))?>" alt="RPV Construcciones"><span>Panel administrativo</span></a><nav aria-label="Administracion">
    <?php foreach($modules->visible() as $id=>$module): ?><a class="nav-item <?=$active===$id?'active':''?>" href="<?=h(url($module['ruta']))?>"><span aria-hidden="true"><?=h(adminIcon($module['icono']))?></span><span><?=h($module['nombre'])?></span></a><?php endforeach; ?>
    </nav><div class="sidebar-foot"><span><?=h(strtoupper($config['environment']==='development'?'Desarrollo':'Produccion'))?></span><small><?=h((string)($user['usuario']??''))?></small></div></aside>
    <div class="app-main"><header class="app-header"><button class="menu-toggle" type="button" aria-controls="sidebar" aria-expanded="false">☰<span class="sr-only">Abrir menu</span></button><div><h1><?=h($title)?></h1><?php if($subtitle!==''):?><p><?=h($subtitle)?></p><?php endif;?></div><form method="post" action="<?=h(url('/logout.php'))?>"><input type="hidden" name="csrf" value="<?=h(Csrf::token())?>"><button class="button ghost" type="submit">Cerrar sesion</button></form></header><div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="false"></div><main id="contenido" class="content">
    <?php $f=flash(); if($f): ?><div class="alert <?=$f['kind']==='error'?'error':'success'?>" role="status"><?=h($f['message'])?></div><?php endif;
}
function adminEnd(): void { ?></main><footer class="app-footer">RPV Construcciones · Administracion segura</footer></div></div></body></html><?php }
