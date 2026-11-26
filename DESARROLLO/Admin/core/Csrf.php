<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return (string) $_SESSION['csrf'];
    }

    public static function validate(?string $token): void
    {
        if (!is_string($token) || !hash_equals(self::token(), $token)) {
            if (function_exists('adminAuditRequestMark')) adminAuditRequestMark('error_seguridad', ['causa' => 'csrf_invalido']);
            http_response_code(403);
            exit('La sesion caduco. Recarga la pagina e intentalo nuevamente.');
        }
    }
}
