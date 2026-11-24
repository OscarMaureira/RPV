$ErrorActionPreference = 'Stop'
$admin = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\Admin')).Path
foreach ($name in @('acceso.php','cuentas.php','index.php','precios.php')) {
    $content = [System.IO.File]::ReadAllText((Join-Path $admin $name))
    if ($content -match '(?i)<style\b|\sstyle\s*=|\son[a-z]+\s*=') {
        throw "Estilo o evento inline bloqueado por CSP en Admin/$name"
    }
}
$accounts = [System.IO.File]::ReadAllText((Join-Path $admin 'cuentas.php'))
if ($accounts -notmatch '/assets/cuentas\.css' -or -not (Test-Path -LiteralPath (Join-Path $admin 'assets\cuentas.css'))) {
    throw 'Falta la hoja de estilos externa de cuentas.'
}
Write-Output 'OK  Política CSP y estilos del administrador validados.'