# Módulo de notificaciones por correo

Este módulo permite que los controladores PHP envíen correo mediante el SMTP
autenticado de RPV. Está fuera de `Web`, por lo que no se publica como recurso
descargable.

## Uso desde un controlador PHP

```php
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/modulos/notificaciones/Notificador.php';

try {
    \RPV\Modulos\Notificaciones\Notificador::enviar(
        'destinatario@example.com',
        'Asunto de la notificación',
        'Versión de texto plano del mensaje.',
        '<p>Versión HTML opcional del mensaje.</p>',
        'aviso_general'
    );
} catch (\RuntimeException $error) {
    error_log('No se pudo enviar una notificación RPV: ' . $error->getMessage());
}
```

El destinatario también puede ser una lista de hasta 10 direcciones. El remitente
se fija al buzón autenticado y no se permite definirlo en cada llamada.

## Confirmación de opiniones

Al guardar una opinión en `testimonios`, la API envía un correo independiente al
correo ingresado por el cliente. La plantilla `Plantillas/OpinionRegistrada.php`
usa la cabecera y el pie compartidos en `Plantillas/componentes/`. Si SMTP falla,
la opinión sigue guardada y la falla se registra en el log del servidor.

El sexto argumento opcional de `Notificador::enviar` acepta una lista BCC. El SMTP entrega esas copias sin poner sus direcciones en el encabezado `To`. En Opiniones, la confirmación al cliente incluye su nombre y comentario y se copia ocultamente a `rpinochetv@rpvconstrucciones.com` y `omaureira@rpvconstrucciones.com`.

Cada intento agrega una línea a `DESARROLLO/logs/notificaciones.log` con fecha UTC,
resultado (`ACEPTADO_SMTP` o `FALLIDO`), tipo de notificación y correo del destinatario.
Las entradas históricas anteriores conservan solo una huella parcial irreversible.
El log no registra el contenido del correo ni las credenciales. `ACEPTADO_SMTP`
significa que el servidor SMTP aceptó el mensaje; no confirma que llegó a la bandeja.

## Configuración global

El módulo usa TLS implícito por SMTP SSL, puerto `465`. Lee todos los parámetros
desde un único archivo privado en el directorio personal del usuario que ejecuta PHP:

- Windows: `%USERPROFILE%\.config\rpv\notificaciones.php`
- Linux/cPanel: `$HOME/.config/rpv/notificaciones.php`

Copia `configuracion.php.example` a esa ruta y completa `password`. El archivo
debe devolver un arreglo PHP con `host`, `port`, `username`, `password`, `from`,
`from_name`, `reply_to` y `ehlo`. El remitente debe coincidir con `username`.
En cPanel, crea la carpeta bajo `/home/USUARIO/.config/rpv/`, fuera de `public_html`,
y limita el permiso del archivo a su propietario (por ejemplo, `chmod 600`).

No guardes el archivo real en el repositorio ni en la carpeta sincronizada del proyecto.
La plantilla de ejemplo no contiene secretos.

## Entorno local

Una vez creado el archivo global en el perfil de Windows, inicia el servidor con
`powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\iniciar-servidor.ps1`.
Para probar el transporte sin registrar comentarios, ejecuta desde la raíz del
proyecto `powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\probar-notificaciones.ps1`.
El mensaje de prueba se envía al buzón de contacto y el resultado queda en el log.

El módulo no expone un endpoint público para enviar correos. Cada flujo PHP debe
llamarlo explícitamente y manejar los errores sin interrumpir una operación ya guardada.
