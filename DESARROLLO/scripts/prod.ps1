$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path $PSScriptRoot -Parent | Split-Path -Parent
$developmentWeb = Join-Path $projectRoot 'DESARROLLO\Web'
$productionWeb = Join-Path $projectRoot 'PRODUCCION\Web'
$developmentNotifications = Join-Path $projectRoot 'DESARROLLO\modulos\notificaciones'
$productionNotifications = Join-Path $projectRoot 'PRODUCCION\modulos\notificaciones'
$productionDomain = 'https://www.rpvconstrucciones.com/'
$php = 'C:\xampp\php\php.exe'
$databaseMigrations = Join-Path $projectRoot 'DESARROLLO\database\migrations'
$appliedMigrationsFile = Join-Path $projectRoot 'DESARROLLO\database\migraciones-cpanel-aplicadas.txt'

function Get-PendingProductionMigrations {
    $applied = @{}
    if (Test-Path -LiteralPath $appliedMigrationsFile -PathType Leaf) {
        foreach ($line in Get-Content -LiteralPath $appliedMigrationsFile -Encoding utf8) {
            if ([string]::IsNullOrWhiteSpace($line) -or $line.TrimStart().StartsWith('#')) { continue }
            $ledgerMatch = [regex]::Match($line, '^(?<name>[^|]+)\|(?<hash>[a-fA-F0-9]{64})$')
            if (-not $ledgerMatch.Success) {
                throw "Registro de migración inválido en ${appliedMigrationsFile}: $line"
            }
            $ledgerName = $ledgerMatch.Groups['name'].Value
            if ($ledgerName -match '(^|[/\\])\.\.([/\\]|$)' -or [System.IO.Path]::IsPathRooted($ledgerName)) {
                throw "Ruta inválida en el registro de migraciones cPanel: $ledgerName"
            }
            if ($applied.ContainsKey($ledgerName)) {
                throw "Migración duplicada en ${appliedMigrationsFile}: $ledgerName"
            }
            $applied[$ledgerName] = $ledgerMatch.Groups['hash'].Value.ToLowerInvariant()
        }
    }

    $pending = [System.Collections.Generic.List[object]]::new()
    $migrationFiles = @(Get-ChildItem -LiteralPath $databaseMigrations -Filter '*.sql' -File -Recurse -Force | Sort-Object FullName)
    foreach ($file in $migrationFiles) {
        $content = Get-Content -LiteralPath $file.FullName -Raw -Encoding utf8
        $targets = [regex]::Matches($content, '(?im)^(?:\uFEFF)?--[ \t]*RPV-Target:[ \t]*(production|development)[ \t]*$')
        if ($targets.Count -ne 1 -or $content -notmatch '(?i)\A(?:\uFEFF)?--[ \t]*RPV-Target:[ \t]*(production|development)[ \t]*(?:\r?\n|$)') {
            throw "Declara exactamente una línea '-- RPV-Target: production' o 'development' en $($file.FullName)."
        }
        $relative = $file.FullName.Substring($databaseMigrations.Length).TrimStart('\', '/') -replace '\\', '/'
        $hash = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($targets[0].Groups[1].Value -ieq 'development') {
            if ($applied.ContainsKey($relative)) {
                throw "Una migración marcada development figura como aplicada en cPanel: $relative"
            }
            continue
        }
        if ($content -match '(?im)^\s*USE\s+|rpvconst_DataMain_dev|rpvconst_conectBD|password\s*=\s*[\x27\x22][^\x27\x22]+') {
            throw "La migración productiva contiene una selección de base, referencia de desarrollo o secreto: $relative"
        }
        if ($applied.ContainsKey($relative)) {
            if ($applied[$relative] -ne $hash) {
                throw "La migración aplicada fue modificada ($relative). Crea una nueva migración en vez de editarla."
            }
            continue
        }
        $pending.Add([pscustomobject]@{ Name = $relative; Hash = $hash; Content = $content })
    }

    foreach ($appliedName in $applied.Keys) {
        if (-not (Test-Path -LiteralPath (Join-Path $databaseMigrations ($appliedName -replace '/', '\')) -PathType Leaf)) {
            throw "La migración marcada como aplicada ya no existe en desarrollo: $appliedName"
        }
    }
    return @($pending | Sort-Object Name)
}

& 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'sincronizar-catalogo.php') --check
if ($LASTEXITCODE -ne 0) { throw 'El catálogo comercial no está sincronizado. Ejecuta sincronizar-catalogo.php antes de preparar producción.' }

$pendingDatabaseMigrations = @(Get-PendingProductionMigrations)
$databaseMigrationSql = $null
if ($pendingDatabaseMigrations.Count -gt 0) {
    $sql = [System.Text.StringBuilder]::new()
    [void]$sql.AppendLine('-- RPV Construcciones - migraciones pendientes para cPanel')
    [void]$sql.AppendLine('-- Selecciona la base de datos de PRODUCCION antes de ejecutar este archivo.')
    [void]$sql.AppendLine('-- No subir este archivo a public_html. Ejecutarlo una sola vez y conservarlo como registro.')
    [void]$sql.AppendLine('-- Tras una ejecucion exitosa, registra cada archivo y SHA256 en DESARROLLO/database/migraciones-cpanel-aplicadas.txt.')
    foreach ($migration in $pendingDatabaseMigrations) {
        [void]$sql.AppendLine('')
        [void]$sql.AppendLine("-- BEGIN $($migration.Name) SHA256 $($migration.Hash)")
        [void]$sql.AppendLine($migration.Content.TrimEnd())
        [void]$sql.AppendLine("-- END $($migration.Name)")
    }
    $databaseMigrationSql = $sql.ToString()
}

function Get-PublishFileMap([string]$root) {
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

function Test-PublicRoutes([string]$root) {
    $issues = [System.Collections.Generic.List[string]]::new()
    $checked = [System.Collections.Generic.HashSet[string]]::new()
    $patterns = @(
        '(?i)(?:src|href)\s*=\s*["''](?<path>/[^"''?#]+)',
        '(?i)fetch\(\s*["''](?<path>/[^"''?#]+)',
        '(?i)url\(\s*["'']?(?<path>/[^)"''?#]+)'
    )
    $webFiles = Get-ChildItem -LiteralPath $root -File -Recurse -Force | Where-Object {
        $_.Extension -in @('.html', '.php', '.js', '.css')
    }

    foreach ($webFile in $webFiles) {
        $content = Get-Content -LiteralPath $webFile.FullName -Raw
        foreach ($pattern in $patterns) {
            foreach ($match in [regex]::Matches($content, $pattern)) {
                $route = $match.Groups['path'].Value
                if ($route.StartsWith('//') -or -not $checked.Add($route)) { continue }
                if ($route -eq '/') { $resolved = Join-Path $root 'index.html' }
                else { $resolved = Join-Path $root $route.TrimStart('/').Replace('/', '\') }
                if (-not (Test-Path -LiteralPath $resolved)) {
                    if ($route.EndsWith('/') -and (Test-Path -LiteralPath (Join-Path $resolved 'index.html'))) { continue }
                    $issues.Add("$route (referencia en $($webFile.Name))")
                }
            }
        }
    }

    $invalidReferences = $webFiles | Select-String -Pattern 'file://|[A-Za-z]:\\(?![nrt])' -AllMatches
    foreach ($reference in $invalidReferences) {
        $issues.Add("Ruta local detectada en $($reference.Path)")
    }
    if ($issues.Count -gt 0) {
        throw "Rutas públicas inválidas o faltantes: $($issues -join '; ')"
    }
    return $checked.Count
}

$sourceBefore = Get-PublishFileMap $developmentWeb
$targetBefore = Get-PublishFileMap $productionWeb
$addedWebFiles = @($sourceBefore.Keys | Where-Object { -not $targetBefore.ContainsKey($_) } | Sort-Object)
$removedWebFiles = @($targetBefore.Keys | Where-Object { -not $sourceBefore.ContainsKey($_) } | Sort-Object)
$modifiedWebFiles = @($sourceBefore.Keys | Where-Object {
    $targetBefore.ContainsKey($_) -and $sourceBefore[$_] -ne $targetBefore[$_]
} | Sort-Object)
$notificationSourceBefore = Get-PublishFileMap $developmentNotifications
$notificationTargetBefore = if (Test-Path -LiteralPath $productionNotifications) {
    Get-PublishFileMap $productionNotifications
} else {
    @{}
}
$addedNotificationFiles = @($notificationSourceBefore.Keys | Where-Object { -not $notificationTargetBefore.ContainsKey($_) } | Sort-Object)
$removedNotificationFiles = @($notificationTargetBefore.Keys | Where-Object { -not $notificationSourceBefore.ContainsKey($_) } | Sort-Object)
$modifiedNotificationFiles = @($notificationSourceBefore.Keys | Where-Object {
    $notificationTargetBefore.ContainsKey($_) -and $notificationSourceBefore[$_] -ne $notificationTargetBefore[$_]
} | Sort-Object)
$addedFiles = @($addedWebFiles | ForEach-Object { "Web/$_" }) + @($addedNotificationFiles | ForEach-Object { "modulos/notificaciones/$_" })
$removedFiles = @($removedWebFiles | ForEach-Object { "Web/$_" }) + @($removedNotificationFiles | ForEach-Object { "modulos/notificaciones/$_" })
$modifiedFiles = @($modifiedWebFiles | ForEach-Object { "Web/$_" }) + @($modifiedNotificationFiles | ForEach-Object { "modulos/notificaciones/$_" })
$changedFiles = @($addedFiles + $modifiedFiles + $removedFiles)
$changedFolders = @($changedFiles | ForEach-Object {
    $folder = Split-Path $_ -Parent
    if ($folder) { $folder } else { '(raíz de Web)' }
} | Sort-Object -Unique)

& powershell.exe -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'preparar-produccion.ps1') -ConfirmarPreparacion
if ($LASTEXITCODE -ne 0) { throw 'No fue posible sincronizar los artefactos de PRODUCCION.' }

if (-not (Test-Path -LiteralPath $php)) { throw 'No se encontró PHP de XAMPP.' }
if (-not (Test-Path -LiteralPath (Join-Path $productionNotifications 'Notificador.php'))) {
    throw 'Falta el módulo privado de notificaciones en PRODUCCION/modulos/notificaciones.'
}
$requiredNotificationFiles = @(
    'Notificador.php'
    'Plantillas\LayoutCorreo.php'
    'Plantillas\OpinionRegistrada.php'
)
foreach ($relativePath in $requiredNotificationFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $productionNotifications $relativePath) -PathType Leaf)) {
        throw "Falta un archivo requerido del módulo privado de notificaciones: $relativePath"
    }
}
if (Test-Path -LiteralPath (Join-Path $productionNotifications 'notificaciones.php')) {
    throw 'La configuración SMTP real debe permanecer fuera del artefacto de producción.'
}
$phpFiles = @(
    Get-ChildItem -LiteralPath $productionWeb -Filter '*.php' -File -Recurse -Force
    Get-ChildItem -LiteralPath $productionNotifications -Filter '*.php' -File -Recurse -Force
)
foreach ($phpFile in $phpFiles) {
    & $php -l $phpFile.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Error de sintaxis PHP: $($phpFile.FullName)" }
}

$api = Join-Path $productionWeb 'api\guardar-ubicacion.php'
$apiContent = Get-Content -LiteralPath $api -Raw
if ($apiContent -notmatch "require '/home/rpvconst/conexion_rpv.php'") {
    throw 'La ruta de conexión de producción no está configurada para cPanel.'
}
if ($apiContent -notmatch '\$isProduction') {
    throw 'La API no distingue correctamente el ambiente de producción.'
}
$reviewApi = Join-Path $productionWeb 'opiniones\api.php'
$reviewApiContent = Get-Content -LiteralPath $reviewApi -Raw
if ($reviewApiContent -notmatch "require '/home/rpvconst/conexion_rpv.php'" -or
    $reviewApiContent -notmatch "'/home/rpvconst/modulos/notificaciones'") {
    throw 'La API de opiniones no usa las rutas privadas preconfiguradas de cPanel.'
}

$routeCount = Test-PublicRoutes $productionWeb
Write-Host "Rutas públicas verificadas: $routeCount" -ForegroundColor Green

$secretPatterns = @('rpvconst_conectBD', 'password\s*=\s*[\x27\x22][^\x27\x22]+')
$secretMatches = @(
    Get-ChildItem -LiteralPath $productionWeb -File -Recurse -Force
    Get-ChildItem -LiteralPath $productionNotifications -File -Recurse -Force
) | Select-String -Pattern $secretPatterns -AllMatches
if ($secretMatches) {
    $files = @($secretMatches | ForEach-Object { $_.Path } | Sort-Object -Unique) -join ', '
    throw "Se detectaron credenciales dentro del paquete publicable: $files"
}
Write-Host 'Credenciales excluidas de los paquetes; configuración y conexión PDO se mantienen fuera de public_html.' -ForegroundColor Green

$excludedDirectories = @(
    Get-ChildItem -LiteralPath $productionWeb -Directory -Recurse -Force |
        Where-Object { $_.Name -in @('BKP', 'docs') }
)
if ($excludedDirectories.Count -gt 0) {
    $paths = @($excludedDirectories | ForEach-Object { $_.FullName }) -join ', '
    throw "BKP y docs deben excluirse del paquete publicable: $paths"
}

& powershell.exe -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'verificar-paridad.ps1')
if ($LASTEXITCODE -ne 0) { throw 'No hay paridad entre DESARROLLO/Web y PRODUCCION/Web.' }

$generatedDatabaseMigration = $null
if ($databaseMigrationSql) {
    $databaseOutputDirectory = Join-Path $projectRoot 'PRODUCCION\BaseDatos'
    if (-not (Test-Path -LiteralPath $databaseOutputDirectory -PathType Container)) {
        throw 'Falta PRODUCCION/BaseDatos para guardar la migración destinada a cPanel.'
    }
    $migrationFileName = "migracion-cpanel-$((Get-Date).ToString('yyyyMMdd-HHmmss-fff'))-$([guid]::NewGuid().ToString('N').Substring(0, 6)).sql"
    $generatedDatabaseMigration = Join-Path $databaseOutputDirectory $migrationFileName
    [System.IO.File]::WriteAllText($generatedDatabaseMigration, $databaseMigrationSql, [System.Text.UTF8Encoding]::new($false))
    if (-not (Test-Path -LiteralPath $generatedDatabaseMigration -PathType Leaf) -or (Get-Item -LiteralPath $generatedDatabaseMigration).Length -eq 0) {
        throw 'No se pudo generar el script SQL pendiente para cPanel.'
    }
    $relativeMigration = "BaseDatos/$migrationFileName"
    $addedFiles += $relativeMigration
    $changedFiles += $relativeMigration
    $changedFolders += 'BaseDatos'
    $changedFolders = @($changedFolders | Sort-Object -Unique)
    Write-Host "Migraciones de base de datos detectadas: $($pendingDatabaseMigrations.Count)" -ForegroundColor Yellow
    Write-Host "Script SQL para ejecutar en cPanel: $generatedDatabaseMigration" -ForegroundColor Yellow
    Write-Host 'Después de ejecutarlo correctamente, registra los nombres y SHA256 de las migraciones en DESARROLLO/database/migraciones-cpanel-aplicadas.txt.' -ForegroundColor Yellow
} else {
    Write-Host 'No hay migraciones de base de datos pendientes para cPanel.' -ForegroundColor Green
}

$manifest = Join-Path $projectRoot 'PRODUCCION\manifiesto-version.txt'
$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss K'
$manifestStatus = if ($generatedDatabaseMigration) { 'LISTO PARA CPANEL - ejecutar migración SQL antes de publicar' } else { 'LISTO PARA CPANEL' }
Set-Content -LiteralPath $manifest -Value "$manifestStatus - preparado desde DESARROLLO el $timestamp" -Encoding utf8

Write-Host ''
Write-Host "Resumen de sincronización: $($changedFiles.Count) archivo(s), $($changedFolders.Count) carpeta(s)." -ForegroundColor Cyan
if ($changedFiles.Count -eq 0) {
    Write-Host 'No hubo cambios pendientes entre DESARROLLO/Web y PRODUCCION/Web.'
} else {
    Write-Host 'Carpetas afectadas:'
    $changedFolders | ForEach-Object { Write-Host " - $_" }
    Write-Host 'Archivos:'
    $addedFiles | ForEach-Object { Write-Host " + agregado: $_" }
    $modifiedFiles | ForEach-Object { Write-Host " ~ modificado: $_" }
    $removedFiles | ForEach-Object { Write-Host " - eliminado: $_" }
}

$logDirectory = Join-Path $projectRoot 'PRODUCCION\Log-Paso-Produccion'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
$logName = "paso-produccion-$((Get-Date).ToString('yyyyMMdd-HHmmss-fff'))-$([guid]::NewGuid().ToString('N').Substring(0, 6)).txt"
$logPath = Join-Path $logDirectory $logName
$logLines = [System.Collections.Generic.List[string]]::new()
$logLines.Add('PASO DE PRODUCCION - RPV Construcciones')
$logLines.Add("Fecha de generaci$([char]0x00F3)n: $timestamp")
$logLines.Add("Dominio: $productionDomain")
$logLines.Add("Estado: $manifestStatus")
$logLines.Add("Rutas p$([char]0x00FA)blicas: verificadas")
$logLines.Add('Credenciales en public_html: no detectadas')
$logLines.Add("Resumen: $($changedFiles.Count) archivo(s), $($changedFolders.Count) carpeta(s) afectadas.")
$logLines.Add("Migraciones SQL productivas pendientes: $($pendingDatabaseMigrations.Count)")
if ($generatedDatabaseMigration) { $logLines.Add("Script SQL generado: $generatedDatabaseMigration") }
foreach ($migration in $pendingDatabaseMigrations) {
    $logLines.Add("Migración: $($migration.Name) | SHA256 $($migration.Hash)")
}
$logLines.Add('')
$logLines.Add('Carpetas afectadas:')
if ($changedFolders.Count -eq 0) { $logLines.Add('- Sin cambios.') }
else { $changedFolders | ForEach-Object { $logLines.Add("- $_") } }
$logLines.Add('')
$logLines.Add('Archivos:')
if ($changedFiles.Count -eq 0) { $logLines.Add('- Sin cambios.') }
else {
    $addedFiles | ForEach-Object { $logLines.Add("+ agregado: $_") }
    $modifiedFiles | ForEach-Object { $logLines.Add("~ modificado: $_") }
    $removedFiles | ForEach-Object { $logLines.Add("- eliminado: $_") }
}
[System.IO.File]::WriteAllLines($logPath, $logLines, [System.Text.UTF8Encoding]::new($true))

if ($generatedDatabaseMigration) {
    Write-Host 'Artefactos preparados; ejecuta primero el script SQL generado en cPanel antes de subir Web.' -ForegroundColor Yellow
} else {
    Write-Host 'Artefactos listos para cPanel: PRODUCCION/Web y PRODUCCION/modulos/notificaciones.' -ForegroundColor Green
}
Write-Host "Manifiesto: $manifest" -ForegroundColor Green
Write-Host "Dominio de producción: $productionDomain" -ForegroundColor Green
Write-Host "Registro de producción: $logPath" -ForegroundColor Green
