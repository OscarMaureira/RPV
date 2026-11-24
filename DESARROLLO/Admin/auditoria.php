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
}