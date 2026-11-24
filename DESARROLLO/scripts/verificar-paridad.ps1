$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path $PSScriptRoot -Parent | Split-Path -Parent
$developmentWeb = Join-Path $projectRoot 'DESARROLLO\Web'
$productionWeb = Join-Path $projectRoot 'PRODUCCION\Web'

function Get-FileMap([string]$root) {
    $map = @{}
    Get-ChildItem -LiteralPath $root -File -Recurse -Force | ForEach-Object {
        $relative = $_.FullName.Substring($root.Length).TrimStart('\', '/')
        $segments = $relative -split '[\\/]'
        $excludedSegment = @($segments | Where-Object { $_ -in @('BKP', 'docs') }).Count -gt 0
        if ($excludedSegment) { return }
        $map[$relative] = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash
    }
    return $map
}

$development = Get-FileMap $developmentWeb
$production = Get-FileMap $productionWeb
$paths = @($development.Keys + $production.Keys | Sort-Object -Unique)
$differences = foreach ($path in $paths) {
    if ($development[$path] -ne $production[$path]) { $path }
}

if ($differences) {
    Write-Host 'Hay diferencias entre DESARROLLO/Web y PRODUCCION/Web:' -ForegroundColor Yellow
    $differences
    exit 1
}

Write-Host 'Paridad confirmada: PRODUCCION/Web coincide con la versión publicable de DESARROLLO/Web.' -ForegroundColor Green
