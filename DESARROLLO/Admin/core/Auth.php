<?php
declare(strict_types=1);

final class Auth
{
    public static function user(): ?array { return $_SESSION['admin_user'] ?? null; }
    public static function id(): int { return (int) (self::user()['id'] ?? 0); }
    public static function requireLogin(): void
    {
        if (!self::user()) go('/login.php');
    }
    public static function validateSession(PDO $pdo, array $config): void
    {
        if (empty($_SESSION['admin_user_id'])) return;
        $now = time();
        if ($now - (int) ($_SESSION['admin_last_active'] ?? 0) > $config['idle_timeout'] || $now - (int) ($_SESSION['admin_started_at'] ?? $now) > $config['absolute_timeout']) {
            self::logout($pdo, 'sesion_expirada'); return;
        }
        $stmt = $pdo->prepare('SELECT id, usuario, password_hash, activo FROM admin_usuarios WHERE id=? LIMIT 1');
        $stmt->execute([(int) $_SESSION['admin_user_id']]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int) $user['activo'] || !hash_equals((string) ($_SESSION['admin_password_stamp'] ?? ''), hash('sha256', (string) $user['password_hash']))) {
            self::clear(); return;
        }
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM AdmPanel_sesiones WHERE token_hash=? AND usuario_id=? AND revocada_en IS NULL AND expira_en>NOW()');
            $stmt->execute([(string) ($_SESSION['admin_session_token_hash'] ?? ''), (int) $user['id']]);
            if (!$stmt->fetchColumn()) { self::clear(); return; }
            $pdo->prepare('UPDATE AdmPanel_sesiones SET ultima_actividad=NOW() WHERE token_hash=?')->execute([(string) $_SESSION['admin_session_token_hash']]);
        } catch (PDOException $e) { /* compatibilidad antes de aplicar la migracion */ }
        $_SESSION['admin_last_active'] = $now;
        $totpEnabled=false; try { $q=$pdo->prepare('SELECT totp_habilitado FROM admin_usuarios WHERE id=?'); $q->execute([$user['id']]); $totpEnabled=(bool)$q->fetchColumn(); } catch(PDOException $e) { }
        $_SESSION['admin_user'] = ['id'=>(int)$user['id'], 'usuario'=>(string)$user['usuario'], 'totp_habilitado'=>$totpEnabled];
        Permissions::load($pdo, (int) $user['id']);
    }
    public static function attempt(PDO $pdo, string $username, string $password, string $totp, array $config): string
    {
        $key = hash('sha256', strtolower($username) . '|' . Security::clientIp());
        $wait = self::rateLimit($pdo, $key);
        if ($wait > 0) return 'Espera ' . $wait . ' segundos antes de intentarlo nuevamente.';
        $stmt = $pdo->prepare('SELECT id, usuario, password_hash, activo FROM admin_usuarios WHERE usuario=? LIMIT 1');
        $stmt->execute([$username]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $valid = $user && (int)$user['activo'] === 1 && password_verify($password, (string)$user['password_hash']);
        if ($valid) { try { $q=$pdo->prepare('SELECT totp_habilitado,totp_secreto FROM admin_usuarios WHERE id=?'); $q->execute([$user['id']]); $factor=$q->fetch(PDO::FETCH_ASSOC); if($factor&&(int)$factor['totp_habilitado']===1){$valid=Totp::verify(SecretVault::decrypt((string)$factor['totp_secreto'],(string)$config['encryption_key']),$totp);if(!$valid){$q=$pdo->prepare('SELECT id,codigo_hash FROM AdmPanel_recuperacion_totp WHERE usuario_id=? AND usado_en IS NULL');$q->execute([$user['id']]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $recovery){if(password_verify(strtoupper(trim($totp)),(string)$recovery['codigo_hash'])){$used=$pdo->prepare('UPDATE AdmPanel_recuperacion_totp SET usado_en=NOW() WHERE id=? AND usado_en IS NULL');$used->execute([$recovery['id']]);$valid=$used->rowCount()===1;break;}}}} } catch(PDOException $e) { if($e->getCode()!=='42S22'){$valid=false;error_log('RPV TOTP: '.$e->getMessage());} } catch(Throwable $e) { $valid=false; error_log('RPV TOTP: '.$e->getMessage()); } }
        if (!$valid) {
            self::recordFailure($pdo, $key, $username);
            adminAuditSafe($pdo, 'inicio_sesion_fallido', 'autenticacion', null, ['origen_hash'=>hash('sha256', Security::clientIp())], $user ? (int)$user['id'] : null, $user ? (string)$user['usuario'] : 'desconocido');
            return 'Las credenciales o el codigo de verificacion no son correctos.';
        }
        if (password_needs_rehash((string)$user['password_hash'], self::algorithm())) {
            $hash = password_hash($password, self::algorithm());
            $pdo->prepare('UPDATE admin_usuarios SET password_hash=? WHERE id=?')->execute([$hash, $user['id']]); $user['password_hash']=$hash;
        }
        self::clear(); session_regenerate_id(true); $token = bin2hex(random_bytes(32));
        $_SESSION['admin_user_id']=(int)$user['id']; $_SESSION['admin_started_at']=time(); $_SESSION['admin_last_active']=time();
        $_SESSION['admin_password_stamp']=hash('sha256',(string)$user['password_hash']); $_SESSION['admin_session_token_hash']=hash('sha256',$token);
        try {
            $pdo->prepare('INSERT INTO AdmPanel_sesiones (usuario_id,token_hash,ip,user_agent,expira_en) VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND))')->execute([(int)$user['id'],hash('sha256',$token),Security::clientIp(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),(int)$config['absolute_timeout']]);
            $pdo->prepare('DELETE FROM AdmPanel_intentos WHERE clave=?')->execute([$key]);
        } catch (PDOException $e) { }
        adminAuditSafe($pdo,'inicio_sesion','autenticacion',null,[],(int)$user['id'],(string)$user['usuario']);
        return '';
    }
    public static function logout(PDO $pdo, string $event='cierre_sesion'): void
    {
        if (self::id()) adminAuditSafe($pdo,$event,'autenticacion',null);
        try { $pdo->prepare('UPDATE AdmPanel_sesiones SET revocada_en=NOW() WHERE token_hash=?')->execute([(string)($_SESSION['admin_session_token_hash']??'')]); } catch (PDOException $e) { }
        self::clear(); session_regenerate_id(true);
    }
    public static function revokeUserSessions(PDO $pdo, int $userId, bool $keepCurrent=false): void
    {
        try {
            if($keepCurrent){$pdo->prepare('UPDATE AdmPanel_sesiones SET revocada_en=NOW() WHERE usuario_id=? AND revocada_en IS NULL AND token_hash<>?')->execute([$userId,(string)($_SESSION['admin_session_token_hash']??'')]);}
            else{$pdo->prepare('UPDATE AdmPanel_sesiones SET revocada_en=NOW() WHERE usuario_id=? AND revocada_en IS NULL')->execute([$userId]);}
        } catch (PDOException $e) { }
    }
    public static function algorithm(): string|int { return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT; }
    private static function rateLimit(PDO $pdo,string $key): int
    {
        try { $stmt=$pdo->prepare('SELECT intentos,bloqueado_hasta FROM AdmPanel_intentos WHERE clave=?'); $stmt->execute([$key]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if($row&&strtotime((string)$row['bloqueado_hasta'])>time()) return strtotime((string)$row['bloqueado_hasta'])-time(); } catch(PDOException $e) { }
        return 0;
    }
    private static function recordFailure(PDO $pdo,string $key,string $username): void
    {
        try { $pdo->prepare('INSERT INTO AdmPanel_intentos (clave,usuario_hash,ip_hash,intentos,ultimo_intento,bloqueado_hasta) VALUES (?,?,?,1,NOW(),DATE_ADD(NOW(),INTERVAL 2 SECOND)) ON DUPLICATE KEY UPDATE intentos=intentos+1,ultimo_intento=NOW(),bloqueado_hasta=DATE_ADD(NOW(),INTERVAL LEAST(900,POW(2,LEAST(intentos,9))) SECOND)')->execute([$key,hash('sha256',strtolower($username)),hash('sha256',Security::clientIp())]); } catch(PDOException $e) { }
    }
    private static function clear(): void { unset($_SESSION['admin_user_id'],$_SESSION['admin_user'],$_SESSION['admin_started_at'],$_SESSION['admin_last_active'],$_SESSION['admin_password_stamp'],$_SESSION['admin_session_token_hash']); }
}
