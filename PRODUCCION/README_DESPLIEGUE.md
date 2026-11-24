# Ambiente de producción — RPV Construcciones

Esta carpeta contiene los artefactos validados para cPanel. `Web/` se publica
en la raíz pública; `modulos/notificaciones/` se instala fuera de `public_html`.
No se guardan aquí contraseñas ni archivos reales de configuración.

Dominio de producción: `https://www.rpvconstrucciones.com/`.

## Preparación y publicación

1. Implementa los cambios en `DESARROLLO` conservando compatibilidad con las rutas y configuraciones de producción.
2. Ejecuta `DESARROLLO/scripts/prod.ps1`; antes de actualizar `PRODUCCION`, genera un ZIP completo del estado productivo actual en `respaldos/codigo/`, y luego sincroniza y valida ambos artefactos.
3. Genera respaldos de archivos y de la base de datos desde cPanel.
4. Sube el contenido de `PRODUCCION/Web` a `public_html`.
5. Instala `PRODUCCION/modulos/notificaciones` en `/home/rpvconst/modulos/notificaciones`, fuera de `public_html`.
6. Conserva la conexión PDO fuera de `public_html`, en `/home/rpvconst/conexion_rpv.php`.
7. Conserva la configuración SMTP fuera de `public_html`, en `/home/rpvconst/.config/rpv/notificaciones.php`, con permisos solo para el propietario.
8. Prueba la página principal, páginas de producto, formulario/API y envío de notificaciones.

No publiques `DESARROLLO`, `respaldos`, archivos `.local.php`, exportaciones SQL
ni configuraciones reales. La plantilla SMTP sin secretos está en
`DESARROLLO/modulos/notificaciones/configuracion.php.example`.

La preparación local no sube archivos a cPanel. Antes de publicar, confirma en
el hosting que el módulo privado y ambos archivos de configuración existen,
están fuera de `public_html` y son legibles por PHP.
