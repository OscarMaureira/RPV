<?php
declare(strict_types=1);

require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';
require_once dirname(__DIR__) . '/modulos/secciones/SeccionesSitio.php';

use RPV\Secciones\SeccionesSitio;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "OK  {$message}\n";
}

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/20261126_control_secciones.sql');
if ($migration === false) throw new RuntimeException('No se pudo leer la migracion.');
try {
    $pdo->exec($migration);
} catch (PDOException $error) {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'secciones_sitio'")->fetchColumn() !== false;
    if ((int) ($error->errorInfo[1] ?? 0) === 1142) {
        if (!$tableExists) {
            fwrite(STDERR, "OMITIDA  La cuenta local no posee CREATE. Aplica la migracion con una cuenta DDL y repite la prueba.\n");
            exit(2);
        }
        echo "OK  La tabla existente se reutiliza sin requerir permisos DDL\n";
    } else {
        throw $error;
    }
}

$initial = (new SeccionesSitio($pdo))->estadosPublicos();
foreach (['tinajas', 'calderas', 'estufas_pellets'] as $key) {
    check(array_key_exists($key, $initial), "Existe la seccion {$key}");
}
$snapshot = $initial;
$combinations = [
    [1, 1, 1],
    [1, 0, 0],
    [0, 1, 0],
    [0, 0, 1],
    [1, 1, 0],
    [0, 0, 0],
];
$setState = $pdo->prepare('UPDATE secciones_sitio SET activo = ? WHERE clave = ?');

try {
    foreach ($combinations as $index => [$tinajas, $calderas, $pellets]) {
        foreach (['tinajas' => $tinajas, 'calderas' => $calderas, 'estufas_pellets' => $pellets] as $key => $active) {
            $setState->execute([$active, $key]);
        }
        $states = (new SeccionesSitio($pdo))->estadosPublicos();
        check(
            $states['tinajas'] === (bool) $tinajas
            && $states['calderas'] === (bool) $calderas
            && $states['estufas_pellets'] === (bool) $pellets,
            'Combinacion ' . ($index + 1) . ' persistida correctamente'
        );
    }

    $userId = (int) $pdo->query('SELECT id FROM admin_usuarios WHERE activo = 1 ORDER BY id LIMIT 1')->fetchColumn();
    if ($userId > 0) {
        $setState->execute([1, 'calderas']);
        $service = new SeccionesSitio($pdo);
        $service->cambiarEstado('calderas', false, $userId, static function (PDO $db, array $change): void {
            $stmt = $db->prepare("INSERT INTO admin_log (evento, actor_id, actor_usuario, entidad, entidad_id, detalle, ip) VALUES ('test_seccion_estado', ?, 'prueba_automatizada', 'seccion_sitio', ?, ?, '127.0.0.1')");
            $stmt->execute([(int) $db->query('SELECT id FROM admin_usuarios WHERE activo=1 ORDER BY id LIMIT 1')->fetchColumn(), $change['clave'], json_encode($change)]);
        });
        check(!$service->estaActiva('calderas'), 'Servicio modifica el estado dentro de una transaccion');
        check((int) $pdo->query("SELECT COUNT(*) FROM admin_log WHERE evento='test_seccion_estado'")->fetchColumn() > 0, 'Cambio de estado registra auditoria');
    } else {
        echo "AVISO  No hay administrador local activo; se omite la prueba de auditoria.\n";
    }
} finally {
    foreach ($snapshot as $key => $active) $setState->execute([$active ? 1 : 0, $key]);
}

check((new SeccionesSitio($pdo))->estadosPublicos() === $snapshot, 'Los estados iniciales fueron restaurados');
