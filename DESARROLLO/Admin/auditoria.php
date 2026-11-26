<?php
declare(strict_types=1);

function adminAudit(PDO $pdo, string $event, string $entity, ?string $target, array $details = [], ?int $actorId = null, ?string $actorName = null): void
{
    $actorId ??= isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
    if ($actorName === null && $actorId !== null) {
        $stmt = $pdo->prepare('SELECT usuario FROM admin_usuarios WHERE id = ?');
        $stmt->execute([$actorId]);
        $actorName = $stmt->fetchColumn() ?: null;
    }
    $stmt = $pdo->prepare('INSERT INTO admin_log (evento, actor_id, actor_usuario, entidad, entidad_id, detalle, ip) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $event, $actorId, (string) ($actorName ?? 'desconocido'), $entity, $target,
        $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($GLOBALS['admin_audit_request_flushing']) && !str_ends_with($event, '_error')) {
        adminAuditRequestMark('exito', ['evento_negocio' => $event, 'entidad' => $entity, 'entidad_id' => $target]);
    }
}

function adminAuditSafe(PDO $pdo, string $event, string $entity, ?string $target, array $details = [], ?int $actorId = null, ?string $actorName = null): void
{
    try { adminAudit($pdo, $event, $entity, $target, $details, $actorId, $actorName); }
    catch (Throwable $e) { error_log('RPV auditoria: ' . $e->getMessage()); }
}

function adminAuditSafeDiagnostic(mixed $value, string $key = ''): mixed
{
    if (preg_match('/password|contrasena|token|secret|secreto|cookie|authorization|correo|email/i', $key)) return '[dato protegido]';
    if (is_array($value)) {
        $clean = [];
        $count = 0;
        foreach ($value as $childKey => $childValue) {
            if ($count++ >= 30) { $clean['_truncado'] = true; break; }
            $clean[$childKey] = adminAuditSafeDiagnostic($childValue, (string) $childKey);
        }
        return $clean;
    }
    if (!is_string($value)) return $value;
    $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? $value;
    $value = preg_replace('/[A-Z]:\\[^\s]+/i', '[ruta local]', $value) ?? $value;
    $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[correo protegido]', $value) ?? $value;
    $value = preg_replace('/\b(?:password|token|secret|authorization)\s*[=:]\s*[^\s,;]+/i', '$1=[dato protegido]', $value) ?? $value;
    return function_exists('mb_substr') ? mb_substr($value, 0, 600, 'UTF-8') : substr($value, 0, 600);
}

function adminAuditFailure(PDO $pdo, string $operation, string $entity, ?string $target, Throwable $error, array $context = []): array
{
    try { $suffix = strtoupper(bin2hex(random_bytes(3))); }
    catch (Throwable) { $suffix = strtoupper(substr(sha1(uniqid('', true)), 0, 6)); }
    $reference = 'ERR-' . date('Ymd-His') . '-' . $suffix;
    $sqlState = $error instanceof PDOException ? (string) ($error->errorInfo[0] ?? $error->getCode()) : null;
    $driverCode = $error instanceof PDOException ? ($error->errorInfo[1] ?? null) : null;
    $technicalMessage = $error instanceof PDOException ? ($error->errorInfo[2] ?? $error->getMessage()) : $error->getMessage();
    $cause = 'error_interno';
    if ($error instanceof InvalidArgumentException) $cause = 'validacion_regla_negocio';
    elseif ($error instanceof PDOException) {
        $cause = match ((int) $driverCode) {
            1062 => 'restriccion_unicidad',
            1205, 1213 => 'concurrencia_base_datos',
            1265 => $entity === 'opinion' ? 'esquema_estado_incompatible' : 'dato_no_compatible_con_esquema',
            1451, 1452 => 'integridad_referencial',
            default => in_array($sqlState, ['42S02', '42S22'], true) ? 'esquema_incompleto' : 'error_base_datos',
        };
    } elseif ($error instanceof RuntimeException) $cause = 'conflicto_operacion';
    $details = [
        'referencia' => $reference,
        'operacion' => $operation,
        'causa' => $cause,
        'tipo_error' => (new ReflectionClass($error))->getShortName(),
        'sqlstate' => $sqlState,
        'codigo_motor' => $driverCode,
        'mensaje_tecnico' => adminAuditSafeDiagnostic((string) $technicalMessage, 'mensaje_tecnico'),
        'contexto' => adminAuditSafeDiagnostic(array_merge([
            'metodo' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'ruta' => basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')),
        ], $context)),
    ];
    adminAuditSafe($pdo, $entity . '_error', $entity, $target, $details);
    adminAuditRequestMark('error', ['referencia_error' => $reference, 'causa' => $cause]);
    error_log('RPV ' . $entity . ' [' . $reference . '] ' . $cause . ': ' . adminAuditSafeDiagnostic((string) $technicalMessage, 'mensaje_tecnico'));
    return ['reference' => $reference, 'cause' => $cause];
}

function adminAuditRequestMark(string $outcome, array $details = []): void
{
    if (empty($GLOBALS['admin_audit_request']) || !is_array($GLOBALS['admin_audit_request'])) return;
    $GLOBALS['admin_audit_request']['resultado'] = $outcome;
    $GLOBALS['admin_audit_request']['detalle_resultado'] = array_merge(
        (array) ($GLOBALS['admin_audit_request']['detalle_resultado'] ?? []),
        adminAuditSafeDiagnostic($details)
    );
}

function adminAuditRequestStart(PDO $pdo): void
{
    if (!empty($GLOBALS['admin_audit_request']) || !Auth::id()) return;
    $actor = Auth::user();
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'desconocido.php'));
    $module = [
        'index.php' => 'inicio',
        'opiniones.php' => 'opiniones',
        'cuentas.php' => 'usuarios',
        'precios.php' => 'precios',
        'secciones.php' => 'secciones',
        'auditoria-log.php' => 'auditoria',
    ][$script] ?? pathinfo($script, PATHINFO_FILENAME);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $safePostKeys = array_values(array_filter(array_map('strval', array_keys($_POST)), static fn(string $key): bool => !preg_match('/csrf|password|contrasena|token|secret|correo|email/i', $key)));
    $target = $_POST['id'] ?? $_POST['product_id'] ?? $_POST['clave'] ?? $_GET['id'] ?? null;
    $GLOBALS['admin_audit_request'] = [
        'pdo' => $pdo,
        'actor_id' => Auth::id(),
        'actor_usuario' => (string) ($actor['usuario'] ?? 'desconocido'),
        'inicio' => microtime(true),
        'modulo' => $module,
        'metodo' => $method,
        'ruta' => $script,
        'accion' => $method === 'POST' ? (string) ($_POST['action'] ?? 'sin_accion') : 'consultar',
        'objetivo' => is_scalar($target) ? (string) $target : null,
        'filtros' => $method === 'GET' ? adminAuditSafeDiagnostic($_GET) : [],
        'campos_enviados' => $method === 'POST' ? $safePostKeys : [],
        'resultado' => $method === 'GET' ? 'consulta' : 'procesada',
        'detalle_resultado' => [],
    ];
    register_shutdown_function(static function (): void {
        $request = $GLOBALS['admin_audit_request'] ?? null;
        if (!is_array($request) || !isset($request['pdo']) || !$request['pdo'] instanceof PDO) return;
        $pdo = $request['pdo'];
        $fatal = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (is_array($fatal) && in_array((int) $fatal['type'], $fatalTypes, true)) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failure = adminAuditFailure($pdo, (string) $request['accion'], (string) $request['modulo'], $request['objetivo'], new ErrorException((string) $fatal['message'], 0, (int) $fatal['type'], (string) $fatal['file'], (int) $fatal['line']), ['fase' => 'shutdown']);
            adminAuditRequestMark('error_fatal', ['referencia_error' => $failure['reference']]);
            $request = $GLOBALS['admin_audit_request'];
        }
        $status = (int) (http_response_code() ?: 200);
        if (is_array($fatal) && in_array((int) $fatal['type'], $fatalTypes, true) && $status < 400) $status = 500;
        if ($status >= 400 && !str_starts_with((string) $request['resultado'], 'error')) {
            $request['resultado'] = 'error_http';
            $request['detalle_resultado']['codigo_http'] = $status;
        }
        $details = [
            'metodo' => $request['metodo'],
            'ruta' => $request['ruta'],
            'accion' => $request['accion'],
            'resultado' => $request['resultado'],
            'duracion_ms' => (int) round((microtime(true) - (float) $request['inicio']) * 1000),
            'codigo_http' => $status,
            'filtros' => $request['filtros'],
            'campos_enviados' => $request['campos_enviados'],
            'detalle_resultado' => $request['detalle_resultado'],
        ];
        $GLOBALS['admin_audit_request_flushing'] = true;
        adminAuditSafe($pdo, $request['metodo'] === 'GET' ? 'panel_consulta' : 'panel_accion', 'modulo_panel', (string) $request['modulo'], $details, (int) $request['actor_id'], (string) $request['actor_usuario']);
        unset($GLOBALS['admin_audit_request_flushing']);
    });
}
