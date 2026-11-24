param(
    [switch]$ConfirmarPreparacion
)

$ErrorActionPreference = 'Stop'

if (-not $ConfirmarPreparacion) {
    throw 'Para preparar PRODUCCION ejecuta el script con -ConfirmarPreparacion.'
}

$root = Split-Path $PSScriptRoot -Parent | Split-Path -Parent
$source = Join-Path $root 'DESARROLLO\Web'
$target = Join-Path $root 'PRODUCCION\Web'
$notificationSource = Join-Path $root 'DESARROLLO\modulos\notificaciones'
$notificationTarget = Join-Path $root 'PRODUCCION\modulos\notificaciones'

& 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'sincronizar-catalogo.php') --check
if ($LASTEXITCODE -ne 0) { throw 'El catálogo comercial no está sincronizado. Ejecuta sincronizar-catalogo.php antes de preparar producción.' }

# Guarda el estado productivo completo antes de cualquier sincronizacion.
if (-not (Test-Path -LiteralPath $target -PathType Container)) {
    throw 'No existe PRODUCCION/Web; no se puede respaldar el ambiente productivo antes de actualizarlo.'
}
$backupDirectory = Join-Path $root 'respaldos\codigo'
New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
$backupName = "produccion-$((Get-Date).ToString('yyyyMMdd-HHmmss-fff'))-$([guid]::NewGuid().ToString('N').Substring(0, 6)).zip"
$backupPath = Join-Path $backupDirectory $backupName
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    (Join-Path $root 'PRODUCCION'),
    $backupPath,
    [System.IO.Compression.CompressionLevel]::Optimal,
    $true
)
$backupArchive = [System.IO.Compression.ZipFile]::OpenRead($backupPath)
try {
    $backupFileCount = @($backupArchive.Entries | Where-Object { -not $_.FullName.EndsWith('/') }).Count
    if ($backupFileCount -eq 0) {
        throw "El respaldo productivo esta vacio: $backupPath"
    }
}
finally {
    $backupArchive.Dispose()
}
Write-Host "Respaldo completo del estado previo: $backupPath ($backupFileCount archivos)." -ForegroundColor Green

# BKP y docs son las únicas carpetas excluidas del artefacto web publicable.
# Elimina copias antiguas de esas carpetas del destino después del respaldo.
$excludedProductionDirectories = @(
    Get-ChildItem -LiteralPath $target -Directory -Recurse -Force |
        Where-Object { $_.Name -in @('BKP', 'docs') } |
        Sort-Object { $_.FullName.Length } -Descending
)
foreach ($directory in $excludedProductionDirectories) {
    if (Test-Path -LiteralPath $directory.FullName) {
        Remove-Item -LiteralPath $directory.FullName -Recurse -Force
    }
}
robocopy $source $target /MIR /XD BKP docs | Out-Null
if ($LASTEXITCODE -gt 7) {
    throw "No fue posible preparar PRODUCCION (robocopy: $LASTEXITCODE)."
}

# Los módulos PHP privados se preparan separados del directorio público del sitio.
robocopy $notificationSource $notificationTarget /MIR | Out-Null
if ($LASTEXITCODE -gt 7) {
    throw "No fue posible preparar el módulo de notificaciones (robocopy: $LASTEXITCODE)."
}

$manifest = Join-Path $root 'PRODUCCION\manifiesto-version.txt'
$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss K'
Set-Content -LiteralPath $manifest -Value "Preparado desde DESARROLLO el $timestamp" -Encoding utf8

Write-Host 'PRODUCCION/Web y PRODUCCION/modulos/notificaciones fueron preparadas desde DESARROLLO.'
