$ErrorActionPreference = 'Stop'

$mysql = 'C:\Program Files\MariaDB 11.4\bin\mariadb.exe'
$schema = Join-Path $PSScriptRoot '..\database\schema.sql'

if (-not (Test-Path -LiteralPath $mysql)) {
    throw 'No se encontró MariaDB 11.4 en la ruta configurada.'
}

Write-Host 'MariaDB 11.4 local: ingresa la contraseña de root cuando se solicite.'
Get-Content -LiteralPath $schema -Raw | & $mysql --no-defaults --host=127.0.0.1 --port=4444 --user=root --password
if ($LASTEXITCODE -ne 0) {
    throw 'No fue posible crear la base local en MariaDB 11.4 (puerto 4444). Revisa el servicio y la contraseña de root.'
}

Write-Host 'Base local rpvconst_DataMain_dev creada o actualizada.'
