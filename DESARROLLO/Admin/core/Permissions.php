<?php
declare(strict_types=1);

final class Permissions
{
    private static array $permissions = [];

    private const ADMIN_PERMISSIONS = [
        'opiniones.ver',
        'opiniones.publicar',
        'usuarios.ver',
        'usuarios.administrar',
        'precios.ver',
        'precios.editar',
        'secciones.ver',
        'secciones.editar',
        'auditoria.ver',
    ];

    public static function load(PDO $pdo, int $userId): void
    {
        // Todas las cuentas de esta tabla son cuentas administrativas. La
        // autorizacion depende de una sesion valida, no de roles editables.
        self::$permissions = $userId > 0
            ? array_fill_keys(self::ADMIN_PERMISSIONS, true)
            : [];
    }
    public static function allows(string $permission): bool { return isset(self::$permissions[$permission]); }
    public static function require(string $permission): void
    {
        if (!self::allows($permission)) { http_response_code(403); exit('No tienes permiso para acceder a esta funcion.'); }
    }
}
