$ErrorActionPreference = 'Stop'
$admin = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\Admin')).Path
foreach ($name in @('acceso.php','auditoria-log.php','componentes/layout.php','cuentas.php','index.php','login.php','logout.php','opiniones.php','precios.php')) {
    $content = [System.IO.File]::ReadAllText((Join-Path $admin $name))
    if ($content -match '(?i)<style\b|\sstyle\s*=|\son[a-z]+\s*=') {
        throw "Estilo o evento inline bloqueado por CSP en Admin/$name"
    }
}
$layout = [System.IO.File]::ReadAllText((Join-Path $admin 'componentes\layout.php'))
$login = [System.IO.File]::ReadAllText((Join-Path $admin 'login.php'))
if (($layout -notmatch '/assets/panel\.css' -and $layout -notmatch "'/assets/panel\.css'") -or
    ($login -notmatch '/assets/panel\.css' -and $login -notmatch "'/assets/panel\.css'") -or
    -not (Test-Path -LiteralPath (Join-Path $admin 'assets\panel.css'))) {
    throw 'Falta la hoja de estilos externa del panel.'
}
Write-Output 'OK  Política CSP y estilos del administrador validados.'
