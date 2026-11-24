$ErrorActionPreference = 'Stop'

$php = 'C:\xampp\php\php.exe'
$adminRoot = Join-Path $PSScriptRoot '..\Admin'
$url = 'http://localhost:8002/'

if (-not (Test-Path -LiteralPath $php)) { throw 'No se encontró PHP de XAMPP.' }
if (-not (Test-Path -LiteralPath (Join-Path $adminRoot 'index.php'))) { throw 'Falta la página de testimonios.' }

$listener = Get-NetTCPConnection -LocalPort 8002 -State Listen -ErrorAction SilentlyContinue
if (-not $listener) {
    $serverArguments = "-S localhost:8002 -t `"$adminRoot`""
    Start-Process -FilePath $php -ArgumentList $serverArguments -WindowStyle Hidden
}

$ready = $false
for ($attempt = 1; $attempt -le 10; $attempt++) {
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2
        if ($response.StatusCode -eq 200) { $ready = $true; break }
    } catch {
        if ($attempt -lt 10) { Start-Sleep -Seconds 1 }
    }
}

if (-not $ready) { throw "El gestor no respondió en $url. Revisa MariaDB y la tabla testimonios." }
Start-Process $url
