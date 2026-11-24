param(
    [switch]$PrepararProduccion
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent | Split-Path -Parent
$developmentWeb = Join-Path $projectRoot 'DESARROLLO\Web'
$productionWeb = Join-Path $projectRoot 'PRODUCCION\Web'
$php = 'C:\xampp\php\php.exe'
$checks = [System.Collections.Generic.List[string]]::new()
$warnings = [System.Collections.Generic.List[string]]::new()

function Add-Check([string]$message) {
    $checks.Add($message)
    Write-Host "OK  $message" -ForegroundColor Green
}

function Add-Warning([string]$message) {
    $warnings.Add($message)
    Write-Host "AVISO  $message" -ForegroundColor Yellow
}

if ($PrepararProduccion) {
    throw 'QA no puede actualizar PRODUCCION. El usuario debe ejecutar explícitamente /prod.'
}

foreach ($path in @($developmentWeb, $productionWeb, (Join-Path $projectRoot 'DESARROLLO\database\schema.sql'))) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Falta la ruta requerida: $path" }
}
Add-Check 'Estructura de ambientes disponible.'

# QA siempre abre solo el ambiente de desarrollo. No inicia, abre ni consulta producción.
& powershell.exe -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'abrir-vista-desarrollo.ps1')
if ($LASTEXITCODE -ne 0) { throw 'No fue posible iniciar el ambiente de desarrollo.' }
Add-Check 'Ambiente de desarrollo abierto en el navegador predeterminado: http://localhost:8000.'

if (-not (Test-Path -LiteralPath $php)) { throw 'No se encontró PHP de XAMPP.' }
$phpFiles = Get-ChildItem -LiteralPath (Join-Path $projectRoot 'DESARROLLO\Web') -Filter '*.php' -File -Recurse
$phpFiles += Get-ChildItem -LiteralPath (Join-Path $projectRoot 'DESARROLLO\BaseDatos') -Filter '*.php' -File
foreach ($phpFile in $phpFiles) {
    & $php -l $phpFile.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Error de sintaxis PHP: $($phpFile.FullName)" }
}
Add-Check "Sintaxis PHP validada ($($phpFiles.Count) archivos)."

$assets = @(
    'assets\images\logos\logo-rpv-construcciones.webp',
    'assets\images\backgrounds\fondo.webp',
    'assets\images\calderas\calderas.webp',
    'assets\images\tinajas\tinajas.webp',
    'assets\images\estufas\estufas.webp'
)
foreach ($asset in $assets) {
    if (-not (Test-Path -LiteralPath (Join-Path $productionWeb $asset))) { throw "Falta recurso visual: $asset" }
}
Add-Check 'Recursos visuales de producción disponibles.'

if (Get-Process mysqld -ErrorAction SilentlyContinue) {
    $dbOutput = & $php (Join-Path $projectRoot 'DESARROLLO\BaseDatos\test_conexion.php') 2>&1
    if ($dbOutput -match 'CONEXION LOCAL CORRECTA') {
        Add-Check 'Conexión a la base local verificada.'
    } else {
        throw "La base local no respondió correctamente: $dbOutput"
    }
} else {
    Add-Warning 'MariaDB local no está iniciado; no se validó la base de datos.'
}

& powershell.exe -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'verificar-csp-admin.ps1')
if ($LASTEXITCODE -ne 0) { throw 'Los estilos del administrador no cumplen la política CSP.' }
Add-Check 'Estilos del administrador compatibles con CSP.'

& powershell.exe -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'verificar-paridad.ps1')
if ($LASTEXITCODE -ne 0) { throw 'DESARROLLO/Web y PRODUCCION/Web no tienen paridad.' }
Add-Check 'Paridad de archivos entre desarrollo y producción confirmada.'

Write-Host ''
Write-Host "QA finalizado: $($checks.Count) controles aprobados, $($warnings.Count) avisos." -ForegroundColor Cyan
