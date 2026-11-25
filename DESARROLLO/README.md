# Ambiente de desarrollo — RPV Construcciones

Aquí se edita y prueba el sitio antes de generar una versión para cPanel.

## Inicio único

1. Inicia el servicio **MariaDB 11.4** (puerto `4444`). XAMPP sigue usando el puerto `3306` para su instancia anterior.
2. Ejecuta `powershell -ExecutionPolicy Bypass -File .\scripts\inicializar-base.ps1` desde PowerShell. MariaDB solicitará la contraseña de `root` en la consola; no se guarda en el proyecto.
3. La conexión local existente usa MariaDB 11.4 en `127.0.0.1:4444`, con el usuario `rpv_local` y una contraseña guardada solo en `BaseDatos\conexion_rpv.local.php` (archivo ignorado por Git).
4. Si reconstruyes la configuración desde `BaseDatos\conexion_rpv.local.php.example`, define una contraseña local nueva para `rpv_local` y escríbela únicamente en el archivo `.local.php` o en `RPV_DB_PASSWORD`.
5. Ejecuta `powershell -ExecutionPolicy Bypass -File .\scripts\iniciar-servidor.ps1`.
6. Abre `http://localhost:8000`.

Para revisar exactamente la versión preparada para publicar, primero ejecuta
`scripts\preparar-produccion.ps1` y después
`powershell -ExecutionPolicy Bypass -File .\scripts\vista-produccion.ps1`.
La vista final queda disponible en `http://localhost:8081`.

También puedes ejecutar `VER_VISTA_PRODUCCION.cmd` desde la raíz del proyecto.
Abrirá el navegador directamente en la vista previa. No abras `index.html` con
doble clic: la dirección debe comenzar con `http://localhost:8081`, no con
`file:///`.

El archivo `conexion_rpv.local.php` contiene datos locales y está excluido del control de versiones.

## Base de datos

La base local es `rpvconst_DataMain_dev`. Tiene las tablas `ubicaciones_chat` y
`testimonios`; el esquema de ubicaciones fue reconstruido desde la API y no contiene
información de clientes. El punto de partida conocido de PRODUCCION está guardado
como esquema solamente en `database/baselines/produccion-inicial.sql`, a partir del
export de cPanel recibido el 2026-11-12. Ese export incluía `ubicaciones_chat`; se
omitieron sus datos. El baseline es una referencia histórica y no se compara en
línea con cPanel. Desde este punto, cada cambio productivo se controla mediante una
migración versionada nueva y su registro SHA256 en
`database/migraciones-cpanel-aplicadas.txt`.

Para verificar dónde se guardan las ubicaciones, ejecuta desde la raíz del proyecto
`C:\xampp\php\php.exe .\DESARROLLO\BaseDatos\test_conexion.php`. Debe indicar el
puerto `4444` y la cantidad de registros. XAMPP también tiene una instancia de
MariaDB en `3306`; su base de igual nombre es independiente y phpMyAdmin de XAMPP
apunta a esa instancia por defecto.

En HeidiSQL, selecciona la sesión `RPV Desarrollo 11.4` para consultar los datos de
esta web. Usa la contraseña local de `root` al conectar. Es la única sesión
configurada en HeidiSQL y apunta al puerto `4444`.

## Opiniones públicas de productos

La página está en `http://localhost:8000/opiniones/` durante desarrollo y en
`https://www.rpvconstrucciones.com/opiniones/` en producción. Acepta envíos sin
cuenta y los guarda en `testimonios` como borradores pendientes de revisión.
Ciudad y empresa/pyme son opcionales; `correo_privado` nunca se muestra en la
vista pública. La API selecciona la conexión y el módulo de notificaciones según
el host: rutas locales en desarrollo y rutas externas preconfiguradas en cPanel.

El gestor local `http://localhost:8002/` trabaja solo con la base de desarrollo.
La administración de la base productiva sigue separada de los archivos públicos.

La estructura requerida por producción se declara en
`database/migrations/20261112_testimonios_cpanel.sql`; `/prod` la incorpora en un
SQL ejecutable desde cPanel si aún no está registrada como aplicada. No subas la
archivos SQL a `public_html`.

Los cambios futuros de tablas, columnas o índices se declaran en archivos SQL
nuevos dentro de `database/migrations/`, con primera línea
`-- RPV-Target: production`. `/prod` agrupa las migraciones productivas
pendientes en `PRODUCCION/BaseDatos/migracion-cpanel-*.sql`; se ejecutan una vez
en la base seleccionada desde cPanel/phpMyAdmin. Registra ruta y SHA256 en
`database/migraciones-cpanel-aplicadas.txt` solo después de confirmar que el SQL
terminó correctamente. No edites migraciones ya aplicadas.

## Notificaciones por correo

El módulo reutilizable está en `modulos/notificaciones`, fuera de la raíz web.
La API de opiniones lo llama después de que la base correspondiente confirma
el registro; envía una confirmación al correo del cliente. Cada aviso usa una
plantilla propia y las mismas piezas de cabecera y pie.

El módulo lee los parámetros SMTP desde `%USERPROFILE%\.config\rpv\notificaciones.php`
en Windows o `$HOME/.config/rpv/notificaciones.php` en cPanel. Copia
`modulos/notificaciones/configuracion.php.example` a esa ubicación y completa la
contraseña. Mantén el archivo real fuera de la carpeta sincronizada del proyecto.
El módulo se publica en `PRODUCCION\modulos\notificaciones`, separado de
`PRODUCCION\Web`; en cPanel debe ubicarse fuera de `public_html`.

## Testimonios administrados

Desde la raíz del proyecto ejecuta
`powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\abrir-admin-testimonios.ps1`.
Se abrirá `http://localhost:8002/`, una página local para registrar, editar y
previsualizar testimonios. Esta instalación ya tiene una clave de administrador;
ingresa y cámbiala desde «Cambiar clave de administrador». En una instalación nueva,
el primer ingreso permite crearla. Solo quien inicie sesión podrá consultar o editar registros.
Antes de guardar es obligatorio marcar la autorización del cliente y confirmar
el registro en la ventana emergente. Cada comentario se guarda primero como
borrador en `testimonios` y el administrador puede marcarlo como listo para web.
La empresa o pyme del cliente y la calificación son opcionales; esta última se
elige con un control de cinco estrellas o se deja sin calificación.
La clave se guarda solo como hash en `BaseDatos/admin_testimonios.local.php`, fuera
de la carpeta pública e ignorada por Git. El gestor está fuera de `DESARROLLO/Web`,
por lo que no forma parte del paquete publicable de cPanel.

## Regla de seguridad

Nunca copies la contraseña de producción en archivos de `DESARROLLO`. La API
local solo acepta solicitudes desde `localhost:8000` o `127.0.0.1:8000`.

## Precios y condiciones comerciales

Abre `http://localhost:8002/precios.php` con la misma clave del gestor de testimonios.
Para iniciar el panel ejecuta `powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\abrir-admin-precios.ps1` desde la raíz del proyecto. El enlace **Administrar precios** también está en testimonios. El panel funciona sin conexión a la base de testimonios.

Cada producto tiene un identificador fijo: `caldera-tipo-a`, `caldera-tipo-b`, `caldera-tipo-c`, `tinaja-4-personas`, `tinaja-7-personas` y `estufa-pellet`.
Administra precio en CLP, modo (referencial, vigente o a consultar), fecha límite opcional, referencia técnica, incluidos, condiciones, instalación, despacho y garantía.
Marca la confirmación y pulsa **Guardar y sincronizar producto**. Se actualizan las fichas HTML, el catálogo que consultan las páginas y ambos asistentes, y `Web/assets/data/conocimiento-rpv.md`. Los asistentes consultan una revisión nueva en cada pregunta; no hace falta cerrar la conversación para obtener un precio actualizado. Las respuestas ya escritas permanecen como historial de la conversación.

Los valores iniciales se tomaron de las fichas actuales de calderas (A $1.150.000, B $830.000, C $730.000) y de las referencias anteriores de tinajas (4 personas $1.450.000; 7 personas $2.850.000). Estufas continúa a consultar. Son referencias iniciales pendientes de revisión por el administrador, no una validación comercial externa. Los incluidos, garantía y otras condiciones sin confirmación única se dejan sujetos a cotización. Los antiguos Equipos 1/2/3 no se equiparan automáticamente a A/B/C.

La fuente editable es `catalogo/productos.json`, fuera de la raíz web. `modulos/catalogo/conocimiento-base.md` conserva la información editorial sin precios ni modelos numerados. Los archivos de `Web/assets/data` y los textos comerciales marcados en las fichas se generan automáticamente: no los edites a mano. Los montos solo pueden ingresarse en el campo de precio, para evitar valores duplicados en condiciones.

Cada guardado respalda la revisión anterior en `catalogo/historial` y rechaza formularios que partan de una revisión antigua. La sincronización conserva la revisión previa si falla la escritura de un artefacto. Los archivos de catálogo requieren permiso de escritura local; no contienen contraseñas ni datos de clientes.

Para regenerar artefactos desde la fuente, ejecuta:

`C:\xampp\php\php.exe .\DESARROLLO\scripts\sincronizar-catalogo.php`

Para comprobarlos sin modificarlos:

`C:\xampp\php\php.exe .\DESARROLLO\scripts\sincronizar-catalogo.php --check`

El flujo `/prod` verifica esta sincronización antes de preparar el paquete. El panel es exclusivamente local y no se copia a cPanel; el sitio publicado consume los HTML, el JSON comercial, el Markdown y el JavaScript generados. No necesita ejecutar el módulo privado del catálogo en cPanel. Subir una versión requiere el flujo explícito `/prod` y la autorización de publicación habitual. Los cambios de precios en desarrollo no modifican por sí solos el hosting.

Las imágenes de calderas que contenían precios incrustados dejaron de utilizarse en las tarjetas: los valores ahora se muestran como texto generado. Las fichas de tinajas y estufas incorporan su catálogo y condiciones.

### Pruebas del catálogo

- `C:\xampp\php\php.exe .\DESARROLLO\tests\catalogo.php`: persistencia, sincronización, aprobación, validación, revisión concurrente y recuperación de fallos en una carpeta temporal.
- `node .\DESARROLLO\tests\catalogo-js.cjs`: resolución A/B/C, productos desconocidos, actualización y fallo de carga. Se puede usar el Node existente de Cursor si no está en PATH.
- `python .\DESARROLLO\tests\catalogo_web.py`: prueba integral con Playwright y Edge instalados, en una copia temporal sin secretos, con credenciales propias de prueba. Verifica el panel, CSRF, guardado, diseño móvil y propagación a conversaciones abiertas. No cambia el catálogo real ni inicia sesión con la clave del usuario.
