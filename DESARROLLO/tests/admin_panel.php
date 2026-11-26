<?php
declare(strict_types=1);

require dirname(__DIR__).'/Admin/core/Totp.php';
require dirname(__DIR__).'/Admin/core/SecretVault.php';
require dirname(__DIR__).'/Admin/auditoria.php';
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "OK: $message\n";}

$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
check(Totp::code($secret,59)==='287082','TOTP coincide con el vector esperado');
check(Totp::verify($secret,'000000')===false,'TOTP rechaza un codigo incorrecto');
$key=base64_encode(random_bytes(32));$encrypted=SecretVault::encrypt('secreto-prueba',$key);
check($encrypted!=='secreto-prueba'&&SecretVault::decrypt($encrypted,$key)==='secreto-prueba','SecretVault cifra y recupera secretos');

$root=dirname(__DIR__);
$migration=(string)file_get_contents($root.'/database/migrations/20261125_admin_panel_unificado.sql');
$states=(string)file_get_contents($root.'/database/migrations/20261125_opiniones_estados_finales.sql');
$permissions=(string)file_get_contents($root.'/Admin/core/Permissions.php');
$opinions=(string)file_get_contents($root.'/Admin/opiniones.php');
$accounts=(string)file_get_contents($root.'/Admin/cuentas.php');
$audit=(string)file_get_contents($root.'/Admin/auditoria-log.php');
$auditWriter=(string)file_get_contents($root.'/Admin/auditoria.php');
$accessSource=(string)file_get_contents($root.'/Admin/acceso.php');
$modules=(string)file_get_contents($root.'/Admin/config/modulos.php');
$publicList=(string)file_get_contents($root.'/Web/opiniones/listado.php');
$publicForm=(string)file_get_contents($root.'/Web/opiniones/index.php');
$publicApi=(string)file_get_contents($root.'/Web/opiniones/api.php');
$promotionMigration=(string)file_get_contents($root.'/database/migrations/20261125_opiniones_promocion_empresas.sql');
$normalizationMigration=(string)file_get_contents($root.'/database/migrations/20261126_normalizar_nombre_empresa.sql');

foreach(['AdmPanel_sesiones','AdmPanel_intentos','AdmPanel_recuperacion_totp'] as $table)check(str_contains($migration,$table),'Migracion declara '.$table);
check(!str_contains($migration,'AdmPanel_roles')&&!str_contains($permissions,'function role('),'No existe administracion de roles');
check(!str_contains($modules,"'configuracion'")&&!is_file($root.'/Admin/configuracion.php'),'Modulo Configuracion retirado');
check(str_contains($states,"'publicada'")&&str_contains($states,"'eliminada'"),'Migracion conserva los tres estados finales');
check(!str_contains($opinions,'INSERT INTO testimonios')&&!str_contains($opinions,'DELETE FROM testimonios')&&!str_contains($opinions,'opinion_editada'),'Panel de opiniones no crea, edita ni elimina fisicamente');
check(str_contains($opinions,"'publish'=>['from'=>'borrador','to'=>'publicada'")&&str_contains($opinions,"'unpublish'=>['from'=>'publicada','to'=>'borrador'"),'Transiciones de opiniones validadas en servidor');
check(str_contains($opinions,"'to'=>'eliminada'")&&str_contains($opinions,'WHERE id_testimonio=? AND estado=?'),'Eliminacion logica valida concurrencia');
check(str_contains($accounts,"strcasecmp((string)\$before['usuario'],'admin')")&&str_contains($accounts,'no permite modificar su correo'),'Correo de admin protegido en servidor');
check(!str_contains($accounts,'rol_id')&&!str_contains($accounts,'rol_nombre'),'Usuarios no muestra ni procesa roles');
check(str_contains($audit,'$perPage=20')&&str_contains($audit,'SELECT DISTINCT actor_usuario'),'Auditoria pagina y obtiene usuarios historicos');
check(str_contains($opinions,'adminAuditFailure')&&str_contains($auditWriter,"\$entity . '_error'"),'Errores operativos quedan registrados en auditoria');
check(str_contains($auditWriter,"'sqlstate'")&&str_contains($auditWriter,"'codigo_motor'")&&str_contains($auditWriter,"'mensaje_tecnico'"),'Diagnostico conserva causa concreta y codigos del motor');
check(str_contains($accessSource,'adminAuditRequestStart')&&str_contains($auditWriter,"'panel_consulta'")&&str_contains($auditWriter,"'panel_accion'"),'Todas las solicitudes autenticadas del panel se auditan transversalmente');
$sanitizedDiagnostic=(string)adminAuditSafeDiagnostic('token=abc usuario@ejemplo.cl','detalle');
check(!str_contains($sanitizedDiagnostic,'token=abc')&&!str_contains($sanitizedDiagnostic,'usuario@ejemplo.cl'),'Diagnostico oculta tokens y correos');
check(str_contains($publicList,"WHERE estado='publicada'")&&!str_contains($publicList,'correo_privado'),'API publica entrega solo opiniones publicadas sin correos');
check(str_contains($promotionMigration,'nombre_empresa')&&str_contains($promotionMigration,'url_empresa')&&str_contains($promotionMigration,'autoriza_promocion'),'Migracion agrega datos promocionales sin eliminar testimonios');
check(str_contains($normalizationMigration,'TRIM(empresa_pyme)')&&str_contains($normalizationMigration,'DROP COLUMN IF EXISTS empresa_pyme'),'Migracion conserva el nombre historico y elimina la columna duplicada');
check(!str_contains($opinions,'empresa_pyme')&&!str_contains($publicForm,'empresa_pyme')&&!str_contains($publicApi,'empresa_pyme'),'Aplicacion usa solo nombre_empresa como campo comercial canonico');
check(!str_contains($publicForm,'name="promotion_consent"')&&substr_count($publicForm,'name="consent"')===1&&str_contains($publicForm,'name="company_url"'),'Formulario usa un unico consentimiento que incluye la publicacion del negocio');
check(str_contains($publicApi,"['http', 'https']")&&str_contains($publicApi,"'borrador'")&&str_contains($publicApi,'autoriza_promocion'),'API valida esquemas web y conserva estado borrador');
check(str_contains($publicList,"autoriza_promocion")&&str_contains($publicList,"['http','https']")&&!str_contains($publicList,'empresa_pyme'),'Listado solo publica promociones autorizadas con URL segura');
echo "Pruebas unitarias del panel completadas.\n";
