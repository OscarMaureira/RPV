$ErrorActionPreference = 'Stop'
$php = 'C:\xampp\php\php.exe'
$adminRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\Admin')).Path
if (-not (Get-NetTCPConnection -LocalPort 8002 -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $php -ArgumentList @('-S','localhost:8002','-t',('"' + $adminRoot + '"')) -WindowStyle Hidden
}
$url = 'http://localhost:8002/precios.php'
$ready = $false
for ($attempt = 0; $attempt -lt 10; $attempt++) {
    try { $page = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2; if ($page.StatusCode -eq 200) { $ready = $true; break } }
    catch { Start-Sleep -Milliseconds 300 }
}
if (-not $ready) { throw 'No fue posible abrir el panel de precios.' }
Start-Process $url
