<?php
declare(strict_types=1);

require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';

$apply = in_array('--apply', $argv, true);
$backfillOnly = in_array('--backfill-only', $argv, true);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_ends_with(strtolower($database), '_dev')) {
    throw new RuntimeException('Este ejecutor solo admite la base de desarrollo.');
}

$columnNames = static function (PDO $pdo): array {
    return array_map(
        static fn(array $column): string => (string) $column['Field'],
        $pdo->query('SHOW COLUMNS FROM testimonios')->fetchAll(PDO::FETCH_ASSOC)
    );
};

$columns = $columnNames($pdo);
if (!in_array('empresa_pyme', $columns, true)) {
    if (!in_array('nombre_empresa', $columns, true)) {
        throw new RuntimeException('Faltan ambas columnas de empresa en testimonios.');
    }
    echo "La tabla ya usa solo nombre_empresa; no hay cambios pendientes.\n";
    exit(0);
}

$summary = $pdo->query(<<<'SQL'
SELECT
  COUNT(*) AS total,
  SUM((nombre_empresa IS NULL OR TRIM(nombre_empresa)='') AND empresa_pyme IS NOT NULL AND TRIM(empresa_pyme)<>'') AS migrables,
  SUM(nombre_empresa IS NOT NULL AND TRIM(nombre_empresa)<>'' AND empresa_pyme IS NOT NULL AND TRIM(empresa_pyme)<>'' AND TRIM(nombre_empresa)<>TRIM(empresa_pyme)) AS conflictos
FROM testimonios
SQL)->fetch(PDO::FETCH_ASSOC);

$migratable = (int) ($summary['migrables'] ?? 0);
$conflicts = (int) ($summary['conflictos'] ?? 0);
echo 'Base local verificada. Filas: ' . (int) ($summary['total'] ?? 0)
    . "; migrables: $migratable; conflictos: $conflicts.\n";

if ($conflicts > 0) {
    throw new RuntimeException('Hay valores distintos en empresa_pyme y nombre_empresa. Resuelve esos conflictos antes de eliminar la columna antigua.');
}

if ($backfillOnly) {
    $updated = $pdo->exec(<<<'SQL'
UPDATE testimonios
SET nombre_empresa=NULLIF(TRIM(empresa_pyme),'')
WHERE (nombre_empresa IS NULL OR TRIM(nombre_empresa)='')
  AND empresa_pyme IS NOT NULL
  AND TRIM(empresa_pyme)<>''
SQL);
    echo 'Backfill completado: ' . (int) $updated . " registro(s) copiados a nombre_empresa. La eliminacion de empresa_pyme sigue pendiente de una cuenta con ALTER.\n";
    exit(0);
}

$grants = array_map('strval', $pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN));
$grantText = strtoupper(implode(' ', $grants));
$canAlter = str_contains($grantText, 'ALL PRIVILEGES') || str_contains($grantText, 'ALTER');
if (!$canAlter) {
    $message = 'La cuenta de la aplicacion no posee ALTER. Ejecuta DESARROLLO/scripts/aplicar-normalizacion-testimonios-admin.ps1 con la cuenta root local.';
    if ($apply) {
        throw new RuntimeException($message);
    }
    echo $message . "\n";
    exit(0);
}
if (!$apply) {
    echo "Preflight correcto. Ejecuta nuevamente con --apply para normalizar la tabla.\n";
    exit(0);
}

$path = dirname(__DIR__) . '/database/migrations/20261126_normalizar_nombre_empresa.sql';
$sql = file_get_contents($path);
if ($sql === false) {
    throw new RuntimeException('No se pudo leer la migracion de normalizacion.');
}
$sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
$statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])));
foreach ($statements as $statement) {
    $pdo->exec($statement);
}

$columns = $columnNames($pdo);
if (in_array('empresa_pyme', $columns, true) || !in_array('nombre_empresa', $columns, true)) {
    throw new RuntimeException('La verificacion posterior detecto un esquema incompleto.');
}
echo "Normalizacion completada: $migratable nombre(s) conservados en nombre_empresa y empresa_pyme eliminada.\n";
