<?php
declare(strict_types=1);

require __DIR__ . '/acceso.php';
require __DIR__ . '/componentes/layout.php';
require_once dirname(__DIR__) . '/modulos/secciones/SeccionesSitio.php';

use RPV\Secciones\SeccionesSitio;

Permissions::require('secciones.ver');
$service = new SeccionesSitio($pdo);
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::validate($_POST['csrf'] ?? null);
    Permissions::require('secciones.editar');
    $key = (string) ($_POST['clave'] ?? '');
    try {
        if (($_POST['action'] ?? '') !== 'change_status') throw new InvalidArgumentException('Accion no valida.');
        $value = filter_var($_POST['activo'] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || !in_array($value, [0, 1], true)) throw new InvalidArgumentException('El estado no es valido.');

        $changed = $service->cambiarEstado($key, $value === 1, Auth::id(), static function (PDO $db, array $change): void {
            adminAudit($db, 'seccion_estado_cambiado', 'seccion_sitio', $change['clave'], [
                'seccion' => $change['nombre'],
                'estado_anterior' => $change['anterior'] ? 'activa' : 'inactiva',
                'estado_nuevo' => $change['nuevo'] ? 'activa' : 'inactiva',
            ]);
        });
        notice($changed['nombre'] . ' ahora esta ' . ($changed['activo'] ? 'activa' : 'inactiva') . '.');
        go('/secciones.php');
    } catch (Throwable $exception) {
        $failure = adminAuditFailure($pdo, 'cambiar_estado', 'seccion_sitio', $key !== '' ? $key : null, $exception);
        $message = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'No se pudo guardar el estado.';
        $error = $message . ' Referencia: ' . $failure['reference'] . '.';
    }
}

try {
    $sections = $service->todas();
} catch (Throwable $exception) {
    $failure = adminAuditFailure($pdo, 'cargar_secciones', 'seccion_sitio', null, $exception);
    $sections = [];
    $driverCode = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
    $error = $driverCode === 1142
        ? 'La cuenta MySQL del sitio no tiene permiso SELECT sobre secciones_sitio. Aplica los privilegios de la migracion. Referencia: ' . $failure['reference'] . '.'
        : 'No fue posible cargar las secciones. Verifica que la migracion este aplicada. Referencia: ' . $failure['reference'] . '.';
}

adminStart('Control de secciones', 'secciones', 'Define que categorias pueden ver y abrir los visitantes');
if ($error !== ''): ?><div class="alert error" role="alert"><?=h($error)?></div><?php endif; ?>
<div class="section-head"><div><h2>Secciones comerciales</h2><p>Desactivar conserva productos, archivos e imagenes; solo retira el acceso publico.</p></div><span class="badge"><?=count($sections)?> secciones</span></div>
<section class="section-control-list" aria-label="Disponibilidad de secciones">
<?php if (!$sections): ?><div class="card empty">No hay secciones configuradas.</div><?php endif; ?>
<?php foreach ($sections as $section): $next = $section['activo'] ? 0 : 1; ?>
<article class="card section-control-row">
  <div><h3><?=h($section['nombre'])?></h3><p>Clave estable: <code><?=h($section['clave'])?></code></p></div>
  <span class="badge <?=$section['activo'] ? 'ok' : 'off'?>" aria-label="Estado actual"><?=$section['activo'] ? 'ACTIVA' : 'INACTIVA'?></span>
  <form method="post" data-confirm="¿Confirmas que deseas <?=$next ? 'activar' : 'desactivar'?> <?=h($section['nombre'])?>?">
    <input type="hidden" name="csrf" value="<?=h(Csrf::token())?>">
    <input type="hidden" name="action" value="change_status">
    <input type="hidden" name="clave" value="<?=h($section['clave'])?>">
    <input type="hidden" name="activo" value="<?=$next?>">
    <button class="section-switch <?=$section['activo'] ? 'is-active' : ''?>" type="submit" role="switch" aria-checked="<?=$section['activo'] ? 'true' : 'false'?>" aria-label="<?=$next ? 'Activar' : 'Desactivar'?> <?=h($section['nombre'])?>">
      <span aria-hidden="true"></span><strong><?=$section['activo'] ? 'Activo' : 'Inactivo'?></strong>
    </button>
  </form>
</article>
<?php endforeach; ?>
</section>
<p class="muted">Las nuevas filas agregadas a <code>secciones_sitio</code> apareceran automaticamente en este modulo.</p>
<?php adminEnd();
