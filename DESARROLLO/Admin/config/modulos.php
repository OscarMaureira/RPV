<?php
declare(strict_types=1);

return [
    'dashboard' => ['nombre' => 'Inicio', 'descripcion' => 'Resumen de la administracion', 'icono' => 'home', 'ruta' => '/index.php', 'permiso' => null, 'activo' => true, 'orden' => 10],
    'opiniones' => ['nombre' => 'Opiniones', 'descripcion' => 'Revisar y publicar testimonios', 'icono' => 'message', 'ruta' => '/opiniones.php', 'permiso' => 'opiniones.ver', 'activo' => true, 'orden' => 20],
    'usuarios' => ['nombre' => 'Usuarios', 'descripcion' => 'Cuentas y acceso', 'icono' => 'users', 'ruta' => '/cuentas.php', 'permiso' => 'usuarios.ver', 'activo' => true, 'orden' => 30],
    'precios' => ['nombre' => 'Precios', 'descripcion' => 'Catalogo y condiciones comerciales', 'icono' => 'tag', 'ruta' => '/precios.php', 'permiso' => 'precios.ver', 'activo' => true, 'orden' => 40],
    'secciones' => ['nombre' => 'Control de secciones', 'descripcion' => 'Disponibilidad publica de categorias', 'icono' => 'settings', 'ruta' => '/secciones.php', 'permiso' => 'secciones.ver', 'activo' => true, 'orden' => 50],
    'auditoria' => ['nombre' => 'Auditoria', 'descripcion' => 'Actividad administrativa inmutable', 'icono' => 'shield', 'ruta' => '/auditoria-log.php', 'permiso' => 'auditoria.ver', 'activo' => true, 'orden' => 60],
];
