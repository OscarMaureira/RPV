$ErrorActionPreference = 'Stop'

$php = 'C:\xampp\php\php.exe'
$webRoot = Join-Path $PSScriptRoot '..\Web'

if (-not (Test-Path -LiteralPath $php)) {
    throw 'No se encontro PHP de XAMPP en C:\xampp\php\php.exe.'
}

Write-Host 'Servidor local: http://localhost:8000'
& $php -S localhost:8000 -t $webRoot
