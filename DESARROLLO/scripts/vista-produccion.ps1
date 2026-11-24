$ErrorActionPreference = 'Stop'

$php = 'C:\xampp\php\php.exe'
$projectRoot = Split-Path $PSScriptRoot -Parent | Split-Path -Parent
$productionWeb = Join-Path $projectRoot 'PRODUCCION\Web'

if (-not (Test-Path -LiteralPath $php)) {
    throw 'No se encontró PHP de XAMPP en C:\xampp\php\php.exe.'
}
if (-not (Test-Path -LiteralPath $productionWeb)) {
    throw 'Falta PRODUCCION\Web. Ejecuta primero preparar-produccion.ps1.'
}

Write-Host 'Vista previa de producción: http://localhost:8081'
& $php -S localhost:8081 -t $productionWeb
