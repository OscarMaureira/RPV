$ErrorActionPreference = 'Stop'

$php = 'C:\xampp\php\php.exe'
$probe = Join-Path $PSScriptRoot 'probar-notificacion.php'

if (-not (Test-Path -LiteralPath $php)) {
    throw 'No se encontro PHP de XAMPP en C:\xampp\php\php.exe.'
}

& $php $probe
if ($LASTEXITCODE -ne 0) {
    throw 'La prueba SMTP fallo. Revisa el archivo de configuracion global y DESARROLLO/logs/notificaciones.log.'
}
