<?php
declare(strict_types=1);

$hostHeader=strtolower((string)($_SERVER['HTTP_HOST']??''));
$host=preg_replace('/:\d+$/','',$hostHeader);
$remote=(string)($_SERVER['REMOTE_ADDR']??'');
$isProduction=in_array($host,['rpvconstrucciones.com','www.rpvconstrucciones.com'],true);
$isLocal=in_array($hostHeader,['localhost:8000','127.0.0.1:8000'],true)&&in_array($remote,['127.0.0.1','::1'],true);
if(!$isProduction&&!$isLocal){http_response_code(404);exit;}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit;}
try{
    if($isProduction)require '/home/rpvconst/conexion_rpv.php';else require dirname(__DIR__,3).'/DESARROLLO/BaseDatos/conexion_rpv.php';
    if(!isset($pdo)||!$pdo instanceof PDO)throw new RuntimeException('Conexion PDO no disponible');
    $stmt=$pdo->query("SELECT nombre_publico,comentario,calificacion,nombre_empresa,url_empresa,autoriza_promocion FROM testimonios WHERE estado='publicada' AND calificacion BETWEEN 1 AND 5 ORDER BY COALESCE(publicado_en,creado_en) DESC,id_testimonio DESC LIMIT 30");
    $opinions=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $opinion=['nombre'=>(string)$row['nombre_publico'],'comentario'=>(string)$row['comentario'],'calificacion'=>(int)$row['calificacion']];
        $companyName=trim((string)($row['nombre_empresa']??''));
        $companyUrl=trim((string)($row['url_empresa']??''));
        if((int)($row['autoriza_promocion']??0)===1&&$companyName!==''&&$companyUrl!==''){
            $parts=$companyUrl!==''?parse_url($companyUrl):false;
            $scheme=is_array($parts)?strtolower((string)($parts['scheme']??'')):'';
            if(filter_var($companyUrl,FILTER_VALIDATE_URL)&&in_array($scheme,['http','https'],true)&&!isset($parts['user'])&&!isset($parts['pass'])){
                $opinion['empresa']=$companyName;
                $opinion['url_empresa']=$companyUrl;
            }
        }
        $opinions[]=$opinion;
    }
    echo json_encode(['ok'=>true,'opiniones'=>$opinions],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable $e){error_log('Opiniones publicas RPV: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'opiniones'=>[]]);}
