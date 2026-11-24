$ErrorActionPreference = 'Stop'

$php = 'C:\xampp\php\php.exe'
$projectRoot = Split-Path $PSScriptRoot -Parent | Split-Path -Parent
$productionWeb = Join-Path $projectRoot 'PRODUCCION\Web'

if (-not (Test-Path -LiteralPath $php)) {
    throw 'No se encontró PHP de XAMPP en C:\xampp\php\php.exe.'
}

$listener = Get-NetTCPConnection -LocalPort 8081 -State Listen -ErrorAction SilentlyContinue
if (-not $listener) {
    Start-Process -FilePath $php -ArgumentList '-S', 'localhost:8081', '-t', $productionWeb -WindowStyle Hidden
    Start-Sleep -Seconds 2
}

Start-Process 'http://localhost:8081/'
