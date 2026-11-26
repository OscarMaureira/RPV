<?php
declare(strict_types=1);

require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
if (!isset($pdo) || !$pdo instanceof PDO) throw new RuntimeException('Conexion PDO no disponible.');

function promotionCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "OK: $message\n";
}

$marker='__rpv_promocion_test_' . bin2hex(random_bytes(6));
$pdo->beginTransaction();
try {
    $insert=$pdo->prepare("INSERT INTO testimonios (nombre_publico,correo_privado,ciudad,nombre_empresa,url_empresa,autoriza_promocion,categoria,calificacion,comentario,consentimiento_publicacion,estado) VALUES (?,?,?,?,?,?,?,?,?,1,'borrador')");
    $insert->execute(['Prueba automatizada',null,null,$marker,'https://example.com/',1,'Calderas',5,'Comentario temporal para validar la promocion empresarial.']);
    $id=(int)$pdo->lastInsertId();

    $read=$pdo->prepare('SELECT estado,nombre_empresa,url_empresa,autoriza_promocion FROM testimonios WHERE id_testimonio=?');
    $read->execute([$id]);$row=$read->fetch(PDO::FETCH_ASSOC);
    promotionCheck($row&&$row['estado']==='borrador','La opinion nueva queda en borrador');
    promotionCheck($row['nombre_empresa']===$marker&&(int)$row['autoriza_promocion']===1,'La autorizacion queda asociada a la opinion');
    promotionCheck($row['url_empresa']==='https://example.com/','La URL normalizada se conserva');

    $public=$pdo->prepare("SELECT COUNT(*) FROM testimonios WHERE id_testimonio=? AND estado='publicada' AND autoriza_promocion=1");
    $public->execute([$id]);promotionCheck((int)$public->fetchColumn()===0,'El borrador no es publico');

    $pdo->prepare("UPDATE testimonios SET estado='publicada',publicado_en=NOW() WHERE id_testimonio=? AND estado='borrador'")->execute([$id]);
    $public->execute([$id]);promotionCheck((int)$public->fetchColumn()===1,'La promocion autorizada aparece al publicar');

    $pdo->prepare("UPDATE testimonios SET estado='borrador',publicado_en=NULL WHERE id_testimonio=? AND estado='publicada'")->execute([$id]);
    $public->execute([$id]);promotionCheck((int)$public->fetchColumn()===0,'La promocion desaparece al despublicar');

    $pdo->prepare("UPDATE testimonios SET estado='eliminada',publicado_en=NULL WHERE id_testimonio=? AND estado='borrador'")->execute([$id]);
    $public->execute([$id]);promotionCheck((int)$public->fetchColumn()===0,'La promocion desaparece al eliminar logicamente');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

echo "Prueba transaccional completada sin conservar datos temporales.\n";
