---
name: prod
description: Prepara y valida el paquete publicable de RPV Construcciones desde DESARROLLO hacia PRODUCCION para cPanel. Ejecutar únicamente cuando el usuario invoque explícitamente /prod.
disable-model-invocation: true
---

# Prod — RPV Construcciones

Ejecuta esta skill y actualiza `PRODUCCION` solo cuando el usuario invoque explícitamente `/prod`. Una solicitud de vista previa, QA o preparación general no autoriza su ejecución.

## Regla permanente del proyecto

Todos los cambios del proyecto deben poder trasladarse al ambiente productivo.
Al implementarlos, conserva las rutas, ramas de ambiente y dependencias privadas
que usa cPanel. Incluye en la preparación cualquier código privado que necesite
el sitio, aunque no pertenezca a `Web`. Los secretos y archivos reales de
configuración deben seguir fuera del repositorio y fuera de `public_html`.

## Dominio de producción

El dominio actual es `https://www.rpvconstrucciones.com/`. Úsalo para las
pruebas posteriores al despliegue en cPanel.

Ejecuta desde la raíz del proyecto:

```powershell
powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\prod.ps1
```

## Respaldo previo obligatorio

Antes de actualizar cualquier archivo de `PRODUCCION` desde `DESARROLLO`, crea
un ZIP con todo el contenido del estado productivo actual. El flujo lo hace al
inicio de `DESARROLLO/scripts/preparar-produccion.ps1`, antes de ejecutar
`robocopy`; si no puede crear y abrir un ZIP con archivos, debe detenerse sin
sincronizar nada. Guarda cada respaldo fuera de `PRODUCCION` en
`respaldos/codigo/produccion-<fecha>-<id>.zip`, incluyendo la carpeta `PRODUCCION`
como raíz del archivo. Nunca sobrescribas respaldos anteriores.

## Cambios de estructura de base de datos

- El estado inicial conocido de PRODUCCION es `DESARROLLO/database/baselines/produccion-inicial.sql`, esquema solamente importado del export cPanel entregado el 2026-11-12. Es una referencia histórica; no contiene filas ni se compara automáticamente con cPanel.
- Desde ese baseline, `/prod` detecta cambios de esquema a través de migraciones SQL versionadas y el registro de migraciones aplicadas; no se conecta a la base de datos ni ejecuta DDL.
- Declara cada cambio de tablas, columnas o índices en un archivo nuevo `DESARROLLO/database/migrations/*.sql`; no edites una migración ya aplicada.
- Diseña las migraciones para que se puedan reintentar con seguridad, especialmente cuando cPanel ejecute un archivo y falle a mitad de camino.
- Si se cambia `DESARROLLO/database/schema.sql` para reflejar una estructura que se publica, añade también su migración productiva correspondiente.
- La primera línea de cada migración debe ser `-- RPV-Target: production` o `-- RPV-Target: development`.
- Antes de preparar `PRODUCCION`, `/prod` valida los marcadores y los SHA256 del registro `DESARROLLO/database/migraciones-cpanel-aplicadas.txt`. Si una migración aplicada cambió o desapareció, detiene el flujo.
- Si hay migraciones nuevas con destino `production`, las reúne en un solo archivo `PRODUCCION/BaseDatos/migracion-cpanel-<fecha>-<id>.sql`. El archivo incluye todas las migraciones pendientes con su nombre y SHA256, no contiene credenciales y no selecciona ni cambia el nombre de la base de datos.
- El SQL se ejecuta manualmente una vez desde cPanel/phpMyAdmin, seleccionando la base productiva ya configurada. Después de una ejecución exitosa, agrega cada ruta y SHA256 al registro de aplicadas. No marques migraciones como aplicadas antes de ejecutarlas.
- Si no hay migraciones productivas pendientes, no se genera SQL. La publicación queda marcada como pendiente mientras exista un script SQL por ejecutar.
- Las migraciones destinadas solo a desarrollo se excluyen del script de cPanel.

## Alcance

- Sincroniza todos los archivos de `DESARROLLO/Web` hacia `PRODUCCION/Web`, excluyendo únicamente las carpetas `BKP` y `docs` en cualquier nivel. Elimina del destino copias previas de esas carpetas.
- Conserva antes de la sincronización un respaldo ZIP completo del ambiente `PRODUCCION` en `respaldos/codigo/`.
- Sincroniza `DESARROLLO/modulos/notificaciones` hacia `PRODUCCION/modulos/notificaciones`; este módulo se instala fuera de `public_html`.
- No incorpora bases de datos ni configuraciones locales al paquete web; las configuraciones privadas de producción permanecen en sus rutas externas ya establecidas.
- Verifica sintaxis de PHP tanto en `PRODUCCION/Web` como en los módulos PHP privados preparados, además de paridad de archivos y rutas de producción.
- Detecta migraciones de esquema productivas pendientes y genera un único archivo SQL para cPanel antes de declarar la publicación lista.
- Comprueba que los recursos, páginas internas y rutas API referenciados existan dentro de `PRODUCCION/Web`.
- Rechaza credenciales dentro de los artefactos preparados para publicación.
- Entrega un resumen de las carpetas afectadas y de cada archivo agregado, modificado o eliminado.
- Crea un archivo plano único por ejecución en `PRODUCCION/Log-Paso-Produccion` con fecha de generación y el resumen completo.
- No sube archivos a cPanel; la publicación requiere una instrucción explícita.

## cPanel

Sube el contenido de `PRODUCCION/Web` a `public_html`. Instala el contenido de
`PRODUCCION/modulos/notificaciones` en `/home/rpvconst/modulos/notificaciones`,
fuera de `public_html`, para que los controladores puedan cargarlo desde el
directorio padre de la raíz web.

La conexión PDO de producción debe existir fuera de `public_html` en
`/home/rpvconst/conexion_rpv.php`; usa la plantilla
`PRODUCCION/CONFIGURACION_CPANEL/conexion_rpv.php.example` solo como referencia.
La configuración SMTP global se guarda por separado en
`/home/rpvconst/.config/rpv/notificaciones.php`, también fuera de `public_html`.
No copies secretos ni archivos reales de configuración al paquete.

La conexión PDO, la tabla `testimonios` con el campo `correo_privado` y la
configuración SMTP se consideran ya instaladas en cPanel. El flujo conserva sus
rutas y valida que el código use esas rutas, sin solicitar ni registrar valores
secretos.

La preparación deja `PRODUCCION/Web` listo para `public_html` y el módulo privado
listo para `/home/rpvconst/modulos/notificaciones`. No publica ni modifica el
hosting; sube únicamente esos dos artefactos cuando se autorice la publicación.

## Resultado esperado

Reporta el manifiesto generado y confirma si el paquete quedó listo para subir a
cPanel. Incluye el dominio de producción. Si falla cualquier control, no lo
declares publicable.
