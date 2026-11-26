<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/entorno.php';
require_once __DIR__ . '/core/Security.php';
Security::headers();
if ($config['environment'] === 'development') {
    $remote=(string)($_SERVER['REMOTE_ADDR']??''); $host=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))??'');
    if (!in_array($remote,['127.0.0.1','::1'],true)||!in_array($host,['localhost','127.0.0.1','[::1]'],true)) { http_response_code(403); exit('El entorno de desarrollo solo acepta acceso local.'); }
} else {
    $host=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))??'');
    if (!in_array($host,$config['production_hosts'],true)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') { http_response_code(403); exit('El panel de produccion requiere el dominio autorizado y HTTPS.'); }
}
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1'); ini_set('session.use_trans_sid','0');
if ($config['environment']==='development') { $sessionPath=dirname(__DIR__).'/.sessions'; if(!is_dir($sessionPath)&&!mkdir($sessionPath,0700,true)&&!is_dir($sessionPath)) throw new RuntimeException('No se pudo crear el directorio local de sesiones.'); session_save_path($sessionPath); }
session_name($config['session_name']);
session_set_cookie_params(['lifetime'=>0,'path'=>$config['base_path']?:'/','secure'=>$config['environment']==='production','httponly'=>true,'samesite'=>'Strict']);
session_start();
require_once __DIR__ . '/core/Csrf.php'; require_once __DIR__ . '/core/Totp.php'; require_once __DIR__ . '/core/SecretVault.php'; require_once __DIR__ . '/core/Permissions.php'; require_once __DIR__ . '/core/Auth.php'; require_once __DIR__ . '/core/ModuleRegistry.php'; require_once __DIR__ . '/auditoria.php';
function h(?string $v): string { return htmlspecialchars($v??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function textLength(string $v): int { return function_exists('mb_strlen')?mb_strlen($v,'UTF-8'):strlen($v); }
function cleanText(string $v): string { return trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',' ',$v)); }
function url(string $path=''): string { global $config; return $config['base_path'] . ($path===''?'/':$path); }
function go(string $path='/index.php'): never { header('Location: '.(preg_match('#^https?://#',$path)?$path:url($path)),true,303); exit; }
function notice(string $message,string $kind='ok'): void { $_SESSION['notice']=['message'=>$message,'kind'=>$kind]; }
function flash(): ?array { $v=$_SESSION['notice']??null; unset($_SESSION['notice']); return $v; }
if ($config['environment']==='development') require dirname(__DIR__).'/BaseDatos/conexion_rpv.php'; else require $config['production_connection'];
if (!isset($pdo)||!$pdo instanceof PDO) throw new RuntimeException('No existe una conexion PDO valida.');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
Csrf::token(); Auth::validateSession($pdo,$config);
$modules = new ModuleRegistry(require __DIR__.'/config/modulos.php');
