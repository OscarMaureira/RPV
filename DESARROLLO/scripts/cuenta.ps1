[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Read-AppServerResponse {
    param(
        [Parameter(Mandatory)] [System.Diagnostics.Process] $Process,
        [Parameter(Mandatory)] [int] $RequestId,
        [int] $TimeoutSeconds = 10
    )

    $deadline = [DateTime]::UtcNow.AddSeconds($TimeoutSeconds)
    while ([DateTime]::UtcNow -lt $deadline) {
        $pending = $Process.StandardOutput.ReadLineAsync()
        $remaining = [int][Math]::Max(1, ($deadline - [DateTime]::UtcNow).TotalMilliseconds)
        if (-not $pending.Wait($remaining)) { break }

        $line = $pending.Result
        if ([string]::IsNullOrWhiteSpace($line)) { continue }

        try { $message = $line | ConvertFrom-Json -ErrorAction Stop }
        catch { continue }

        $messageId = $message.PSObject.Properties['id']
        if ($null -ne $messageId -and $messageId.Value -eq $RequestId) { return $message }
    }

    throw "Codex no respondió a la consulta de cuenta en $TimeoutSeconds segundos."
}

function Format-AccountValue {
    param($Value)

    if ($null -eq $Value -or [string]::IsNullOrWhiteSpace([string] $Value)) {
        return 'No disponible'
    }

    return [string] $Value
}

function Get-PlanValueReference {
    param($PlanType)

    switch (([string] $PlanType).ToLowerInvariant()) {
        'plus' { return 'USD 20.00/mes' }
        'free' { return 'USD 0.00/mes' }
        default { return 'No disponible para este plan' }
    }
}

try {
    $codex = Get-Command codex.exe -ErrorAction Stop
    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $codex.Source
    $startInfo.Arguments = 'app-server'
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.CreateNoWindow = $true

    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    [void] $process.Start()

    try {
        $initialize = @{ id = 1; method = 'initialize'; params = @{ clientInfo = @{ name = 'RPV Cuenta'; version = '1.0' }; capabilities = @{} } } | ConvertTo-Json -Compress -Depth 6
        $process.StandardInput.WriteLine($initialize)
        $process.StandardInput.Flush()
        [void] (Read-AppServerResponse -Process $process -RequestId 1)

        $process.StandardInput.WriteLine((@{ method = 'initialized'; params = @{} } | ConvertTo-Json -Compress))
        $query = @{ id = 2; method = 'account/read'; params = @{ refreshToken = $false } } | ConvertTo-Json -Compress
        $process.StandardInput.WriteLine($query)
        $process.StandardInput.Flush()
        $response = Read-AppServerResponse -Process $process -RequestId 2
    }
    finally {
        if (-not $process.HasExited) { $process.Kill() }
        $process.Dispose()
    }

    $responseError = $response.PSObject.Properties['error']
    if ($null -ne $responseError -and $null -ne $responseError.Value) {
        throw "Codex informó: $($responseError.Value.message)"
    }

    $account = $response.result.account

    if (-not $account) {
        Write-Output 'No hay una cuenta de Codex autenticada en Cursor.'
        exit 1
    }

    Write-Output 'Cuenta activa de Codex en Cursor'
    Write-Output "Usuario: $(Format-AccountValue $account.email)"
    Write-Output "Tipo de cuenta: $(Format-AccountValue $account.type)"
    Write-Output "Plan: $(Format-AccountValue $account.planType)"
    Write-Output "Valor mensual del plan (referencia): $(Get-PlanValueReference $account.planType)"
    Write-Output 'Costo adicional estimado por token incluido: USD 0.00'
    Write-Output 'Nota: Codex no asigna una cantidad fija de tokens ni una tarifa USD por token al plan. Los creditos adicionales se revisan en el panel de uso.'
}
catch {
    Write-Error "No fue posible consultar la cuenta activa de Codex: $($_.Exception.Message)"
    exit 1
}
