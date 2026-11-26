<?php
declare(strict_types=1);

namespace RPV\Secciones;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class SeccionesSitio
{
    private ?array $cache = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function todas(): array
    {
        if ($this->cache !== null) return $this->cache;

        $stmt = $this->pdo->query(
            'SELECT id, clave, nombre, activo, orden, fecha_actualizacion, usuario_actualizacion
             FROM secciones_sitio
             ORDER BY orden, id'
        );

        return $this->cache = array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'clave' => (string) $row['clave'],
            'nombre' => (string) $row['nombre'],
            'activo' => (bool) $row['activo'],
            'orden' => (int) $row['orden'],
            'fecha_actualizacion' => (string) $row['fecha_actualizacion'],
            'usuario_actualizacion' => $row['usuario_actualizacion'] !== null ? (int) $row['usuario_actualizacion'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function estadosPublicos(): array
    {
        $states = [];
        foreach ($this->todas() as $section) $states[$section['clave']] = $section['activo'];
        return $states;
    }

    public function estaActiva(string $key): bool
    {
        foreach ($this->todas() as $section) {
            if ($section['clave'] === $key) return $section['activo'];
        }
        return false;
    }

    public function cambiarEstado(string $key, bool $active, int $userId, callable $audit): array
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key)) {
            throw new InvalidArgumentException('La seccion indicada no es valida.');
        }
        if ($userId < 1) throw new InvalidArgumentException('No existe un administrador valido.');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, clave, nombre, activo FROM secciones_sitio WHERE clave = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$key]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$section) throw new InvalidArgumentException('La seccion no existe.');

            $before = (bool) $section['activo'];
            if ($before === $active) throw new RuntimeException('La seccion ya tiene el estado solicitado.');

            $update = $this->pdo->prepare(
                'UPDATE secciones_sitio
                 SET activo = ?, usuario_actualizacion = ?, fecha_actualizacion = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $update->execute([$active ? 1 : 0, $userId, (int) $section['id']]);
            if ($update->rowCount() !== 1) throw new RuntimeException('No fue posible actualizar la seccion.');

            $audit($this->pdo, [
                'clave' => (string) $section['clave'],
                'nombre' => (string) $section['nombre'],
                'anterior' => $before,
                'nuevo' => $active,
            ]);
            $this->pdo->commit();
            $this->cache = null;
            if (function_exists('apcu_delete')) @apcu_delete('rpv_secciones_publicas_v1');

            return ['clave' => $key, 'nombre' => (string) $section['nombre'], 'activo' => $active];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
