<?php
declare(strict_types=1);
require dirname(__DIR__) . '/modulos/catalogo/Catalogo.php';
use RPV\Catalogo\Catalogo;
$fixture = sys_get_temp_dir() . '/rpv-catalogo-test-' . bin2hex(random_bytes(8));
$dev = dirname(__DIR__);
$checks = 0;
function expect(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function rejects(callable $fn, string $contains): void { try { $fn(); } catch (Throwable $e) { expect(str_contains($e->getMessage(), $contains), 'Error inesperado: '.$e->getMessage()); return; } throw new RuntimeException('Se aceptó una entrada inválida.'); }
function inputFor(array $p): array { $input = array_intersect_key($p, array_flip(array_merge(['modo_precio','vigente_hasta'], Catalogo::TEXTOS))); $input['precio_clp']=(string)($p['precio_clp'] ?? '');$input['aprobar']='1';return $input; }
function cleanup(string $dir, string $base): void {
    $resolved = realpath($dir); $root = realpath($base);
    if ($resolved === false || $root === false || !str_starts_with($resolved, $root)) throw new RuntimeException('Ruta temporal no válida.');
    foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $entry) { if ($entry->isDir() && !$entry->isLink()) cleanup($entry->getPathname(), $base); else unlink($entry->getPathname()); }
    rmdir($dir);
}
mkdir($fixture,0770,true);
try {
    foreach (['catalogo/productos.json','modulos/catalogo/conocimiento-base.md','Web/index.html','Web/calderas/index.html','Web/tinajas/index.html','Web/estufas-a-pellets/index.html'] as $relative) { @mkdir(dirname($fixture.'/'.$relative),0770,true); copy($dev.'/'.$relative,$fixture.'/'.$relative); }
    mkdir($fixture.'/Web/assets/data',0770,true);
    $catalogo=new Catalogo($fixture);$catalogo->sincronizar();$data=$catalogo->leer();
    expect(count($data['productos'])===6,'Faltan productos.');
    $input=inputFor($data['productos'][0]);$input['precio_clp']='1234567';$input['incluye']='Kit comercial de prueba aprobado.';
    $saved=$catalogo->guardar('caldera-tipo-a',$input,$data['revision']);
    expect($saved['productos'][0]['precio_clp']===1234567,'No se guardó el precio.');
    expect($saved['productos'][0]['aprobado']===true,'Falta aprobación.');
    expect($saved['productos'][1]===$data['productos'][1],'Se cambió otro producto.');
    $public=json_decode(file_get_contents($fixture.'/Web/assets/data/catalogo-rpv.json'),true);
    expect($public['revision']===$saved['revision'],'Las revisiones difieren.');
    expect(str_contains($public['productos'][0]['respuesta'],'$1.234.567'),'JSON sin actualizar.');
    expect(str_contains(file_get_contents($fixture.'/Web/assets/data/conocimiento-rpv.md'),'$1.234.567'),'Conocimiento sin actualizar.');
    expect(str_contains(file_get_contents($fixture.'/Web/calderas/index.html'),'$1.234.567'),'HTML sin actualizar.');
    expect(!str_contains(file_get_contents($fixture.'/Web/assets/data/conocimiento-rpv.md'),'$1.150.000'),'Persistió un precio anterior.');
    expect(count(glob($fixture.'/catalogo/historial/*.json'))===1,'Falta historial.');
    rejects(fn()=>$catalogo->guardar('caldera-tipo-a',$input,$data['revision']),'otra ventana');
    foreach (['-1','1.5','1.150.000','1000000000',''] as $value) { $bad=$input;$bad['precio_clp']=$value;rejects(fn()=>$catalogo->guardar('caldera-tipo-a',$bad,$saved['revision']),'pesos enteros'); }
    $bad=$input;$bad['aprobar']='';rejects(fn()=>$catalogo->guardar('caldera-tipo-a',$bad,$saved['revision']),'Confirma');
    $bad=$input;$bad['garantia']='Cuesta $123000';rejects(fn()=>$catalogo->guardar('caldera-tipo-a',$bad,$saved['revision']),'campo Precio');
    $bad=$input;$bad['vigente_hasta']='2026-02-30';rejects(fn()=>$catalogo->guardar('caldera-tipo-a',$bad,$saved['revision']),'vigencia');
    rejects(fn()=>$catalogo->guardar('caldera-tipo-z',$input,$saved['revision']),'no existe');
    $noPrice=inputFor($saved['productos'][0]);$noPrice['modo_precio']='consultar';$noPrice['precio_clp']='';
    $saved=$catalogo->guardar('caldera-tipo-a',$noPrice,$saved['revision']);
    expect($saved['productos'][0]['precio_clp']===null,'A consultar conserva un monto.');
    expect(str_contains(file_get_contents($fixture.'/Web/calderas/index.html'),'Precio a consultar'),'No se publicó A consultar.');
    $expired=$saved['productos'][1];$expired['vigente_hasta']='2000-01-01';expect($catalogo->precioTexto($expired)==='Precio a consultar','No se ocultó precio vencido.');
    // Fallo de publicación: no debe quedar una fuente o un Markdown parcialmente actualizado.
    $beforeSource=file_get_contents($fixture.'/catalogo/productos.json');$beforeMd=file_get_contents($fixture.'/Web/assets/data/conocimiento-rpv.md');
    unlink($fixture.'/Web/assets/data/catalogo-rpv.json');mkdir($fixture.'/Web/assets/data/catalogo-rpv.json');
    $input=inputFor($saved['productos'][1]);$input['precio_clp']='765432';
    rejects(fn()=>$catalogo->guardar('caldera-tipo-b',$input,$saved['revision']),'No se guardó');
    expect(file_get_contents($fixture.'/catalogo/productos.json')===$beforeSource,'Rollback de la fuente incompleto.');
    expect(file_get_contents($fixture.'/Web/assets/data/conocimiento-rpv.md')===$beforeMd,'Rollback del conocimiento incompleto.');
    rmdir($fixture.'/Web/assets/data/catalogo-rpv.json');$catalogo->sincronizar();$catalogo->sincronizar(true);
    echo "OK: $checks controles del catálogo, concurrencia, validación y recuperación.\n";
} finally { cleanup($fixture,$fixture); }
