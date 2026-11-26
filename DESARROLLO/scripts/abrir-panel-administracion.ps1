$ErrorActionPreference = 'Stop'

$php = 'C:\xampp\php\php.exe'
$adminRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\Admin')).Path
$url = 'http://localhost:8002/'

if (-not (Test-Path -LiteralPath $php)) {
    throw 'No se encontró PHP de XAMPP en C:\xampp\php\php.exe.'
}
if (-not (Test-Path -LiteralPath (Join-Path $adminRoot 'index.php'))) {
    throw 'Falta la entrada de DESARROLLO\Admin.'
}

$listener = Get-NetTCPConnection -LocalPort 8002 -State Listen -ErrorAction SilentlyContinue
if (-not $listener) {
    $serverArguments = "-S localhost:8002 -t `"$adminRoot`""
    Start-Process -FilePath $php -ArgumentList $serverArguments -WindowStyle Hidden
}

$page = $null
for ($attempt = 1; $attempt -le 10; $attempt++) {
    try {
        $page = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2
        if ($page.StatusCode -eq 200) { break }
    } catch {
        if ($attempt -lt 10) { Start-Sleep -Seconds 1 }
    }
}

if ($null -eq $page -or $page.StatusCode -ne 200) {
    throw "El Panel de Administración no respondió correctamente en $url."
}

Start-Process $url
