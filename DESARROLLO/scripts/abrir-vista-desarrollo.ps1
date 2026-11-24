$ErrorActionPreference = 'Stop'

$php = 'C:\xampp\php\php.exe'
$projectRoot = Split-Path $PSScriptRoot -Parent | Split-Path -Parent
$developmentWeb = Join-Path $projectRoot 'DESARROLLO\Web'

if (-not (Test-Path -LiteralPath $php)) {
    throw 'No se encontró PHP de XAMPP en C:\xampp\php\php.exe.'
}
if (-not (Test-Path -LiteralPath $developmentWeb)) {
    throw 'Falta DESARROLLO\Web.'
}

$listener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if (-not $listener) {
    $serverArguments = "-S localhost:8000 -t `"$developmentWeb`""
    Start-Process -FilePath $php -ArgumentList $serverArguments -WindowStyle Hidden
}

$page = $null
for ($attempt = 1; $attempt -le 10; $attempt++) {
    try {
        $page = Invoke-WebRequest -Uri 'http://localhost:8000/' -UseBasicParsing -TimeoutSec 2
        if ($page.StatusCode -eq 200) { break }
    } catch {
        if ($attempt -lt 10) { Start-Sleep -Seconds 1 }
    }
}

if ($null -eq $page -or $page.StatusCode -ne 200) {
    throw 'El ambiente de desarrollo no respondió correctamente en http://localhost:8000.'
}

Start-Process 'http://localhost:8000/'
