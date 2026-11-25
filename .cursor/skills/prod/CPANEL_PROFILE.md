# Perfil productivo cPanel verificado

Última auditoría de solo lectura: 2026-11-24, zona horaria America/Santiago.

Este perfil evita repetir la auditoría de compatibilidad del hosting. Confía en
él mientras el usuario no informe cambios de proveedor, plan, cuenta, dominio o
configuración PHP. Nunca guardes aquí contraseñas, tokens de sesión ni secretos.

## Infraestructura comprobada

- Cuenta cPanel: `rpvconst`.
- Dominio principal: `rpvconstrucciones.com` con certificado SSL activo.
- Directorio principal: `/home/rpvconst`.
- Raíz pública: `/home/rpvconst/public_html`.
- cPanel: 138.0.28.
- Sistema: Linux x86_64, kernel CloudLinux/LVE 4.18.
- Apache: 2.4.71.
- PHP del dominio: 8.1 (`ea-php81`) con PHP-FPM activo.
- MariaDB: 11.4.14 en `localhost:3306` mediante socket local.
- Sendmail: `/usr/sbin/sendmail`.
- Tres cuentas de correo configuradas.
- Email Deliverability no reportó problemas para el dominio.

## Extensiones PHP

El selector PHP usa la versión nativa 8.1 y no permite enumerar módulos desde
cPanel. El endpoint PHP público sí ejecuta correctamente. phpMyAdmin confirmó
`mysqli`, `curl` y `mbstring` en su propio runtime PHP 8.4, pero ese dato no
garantiza por sí solo los módulos del runtime web 8.1.

No reabras cPanel solo para repetir esta comprobación. Si una nueva dependencia
PHP necesita una extensión adicional, valida específicamente esa extensión
antes de publicar el cambio que la introduce.

## Rutas privadas y permisos

- `/home/rpvconst/conexion_rpv.php` existe fuera de `public_html`, con permiso
  `0644`. No inspeccionar ni copiar sus secretos.
- `public_html` usa `0750`.
- Las carpetas públicas observadas usan `0755`.
- Los archivos públicos observados usan `0644`.
- El filesystem Linux distingue mayúsculas y minúsculas.
- Apache normalizó `/Calderas/` hacia `/calderas/`, confirmando el funcionamiento
  efectivo de las reglas de redirección. Las rutas nuevas deben conservar
  nombres y enlaces en minúsculas.

## Base de datos observada

- Base productiva: `rpvconst_DataMain`.
- Acceso autenticado desde phpMyAdmin confirmado.
- Estado observado: solo existe `ubicaciones_chat`, con una fila.
- No estaban presentes tablas de productos, precios, administración ni
  testimonios del desarrollo actual.
- `DESARROLLO/database/migraciones-cpanel-aplicadas.txt` no registraba
  migraciones productivas aplicadas.

Por tanto, el servidor es compatible, pero el esquema productivo requiere las
migraciones versionadas antes de publicar las funciones que dependen de esas
tablas. No ejecutes DDL sin autorización. Tras aplicar una migración, registra
su ruta y SHA256 y actualiza este apartado.

## Estado del despliegue observado

El 2026-11-24 el sitio público seguía mostrando la portada antigua «Sitio en
construcción». Las rutas `/calderas/`, `/tinajas/` y `/estufas-a-pellets/`
resolvían, pero no mostraban las fichas actuales. Los archivos remotos observados
procedían principalmente del 10 al 12 de noviembre y no coincidían con
`DESARROLLO` actualizado el 24 de noviembre.

Esto demuestra que el hosting es compatible, pero la publicación estaba
desactualizada. No reutilices este estado como paquete válido: regenera
`PRODUCCION` desde `DESARROLLO` antes de subir.

## Controles que sí se repiten por publicación

1. Generar respaldo y paquete con `/prod`.
2. Verificar paridad completa entre `DESARROLLO/Web` y `PRODUCCION/Web`.
3. Comprobar que el manifiesto no sea antiguo y que incluya las páginas,
   recursos, API y módulos privados actuales.
4. Subir íntegramente `PRODUCCION/Web` a `public_html` y los módulos privados a
   sus rutas externas, solo con autorización explícita.
5. Aplicar las migraciones productivas pendientes en orden y actualizar el
   registro SHA256.
6. Probar por HTTPS portada, Calderas, Tinajas, Estufas, recursos, precios y API
   mediante operaciones de lectura.
7. Confirmar que no reaparece la portada «Sitio en construcción» y que las
   rutas no entregan contenido de otra página por una regla de fallback.
8. Comparar inventario, tamaños o hashes remotos con el manifiesto de la
   publicación para confirmar integridad de la subida.

## Controles que no deben repetirse sin motivo

- Versiones base de cPanel, Apache, MariaDB, Linux y PHP.
- Existencia de PHP-FPM, Sendmail, SSL y la ruta privada de conexión.
- Auditoría general de permisos ya registrada.
- Revisión general de compatibilidad con cPanel.
- Prueba SMTP con envío real.

Repite uno de estos controles únicamente si el usuario informa un cambio del
hosting, una actualización del proveedor, un error relacionado o una nueva
dependencia técnica que lo justifique.
