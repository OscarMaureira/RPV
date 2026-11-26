$ErrorActionPreference = 'Stop'

$mariaDb = 'C:\Program Files\MariaDB 11.4\bin\mariadb.exe'
$migration = Join-Path $PSScriptRoot '..\database\migrations\20261126_normalizar_nombre_empresa.sql'
$verifier = Join-Path $PSScriptRoot 'aplicar-normalizacion-testimonios.php'
$php = 'C:\xampp\php\php.exe'

foreach ($requiredPath in @($mariaDb, $migration, $verifier, $php)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Falta la ruta requerida: $requiredPath"
    }
}

Write-Host 'MariaDB local 127.0.0.1:4444: ingresa la contraseña de root cuando se solicite.'
Get-Content -LiteralPath $migration -Raw |
    & $mariaDb --no-defaults --host=127.0.0.1 --port=4444 --user=root --password rpvconst_DataMain_dev
if ($LASTEXITCODE -ne 0) {
    throw 'No fue posible normalizar testimonios en la base local.'
}

& $php $verifier
if ($LASTEXITCODE -ne 0) {
    throw 'La migracion termino, pero fallo su verificacion posterior.'
}
Write-Host 'Tabla testimonios normalizada correctamente.' -ForegroundColor Green
