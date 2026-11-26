# Panel administrativo unificado

La entrada local es `http://localhost:8002/` y la ruta prevista en cPanel es
`https://www.rpvconstrucciones.com/admin/`. El panel usa PHP, PDO/MariaDB,
sesiones `HttpOnly`, CSRF y consultas preparadas.

## Modulos

- Dashboard: indicadores operativos.
- Opiniones: consulta, publicacion, despublicacion y eliminacion logica. No crea
  ni edita opiniones.
- Usuarios: alta de cuentas, correo, activacion, contrasena propia y sesiones.
- Precios: conserva el flujo del catalogo existente.
- Auditoria: filtros, orden estable, detalle legible y 20 filas por pagina.

No existe un modulo Configuracion ni administracion de roles. Todas las cuentas
de `admin_usuarios` son administrativas y solo acceden despues de autenticarse.
Las piezas internas de TOTP se conservan para validar cuentas que ya lo tengan
habilitado, pero no forman un modulo visible.

## Auditoria transversal

Toda solicitud autenticada a Inicio, Opiniones, Usuarios, Precios y Auditoria
genera un evento `panel_consulta` o `panel_accion`. Se registran modulo, accion,
resultado, duracion, codigo HTTP, filtros y nombres de campos no sensibles.
Las acciones de negocio conservan ademas su evento especifico. Los errores
generan una referencia correlacionable, causa, tipo, SQLSTATE y codigo del motor;
CSRF, contrasenas, correos, tokens y secretos nunca se guardan.

## Politica de cuentas

- `admin` es permanente; su nombre, correo y estado no se pueden modificar.
- Las otras cuentas requieren usuario unico, correo valido y unico y contrasena
  inicial de 12 a 128 caracteres almacenada mediante `password_hash`.
- La cuenta de la sesion se destaca como `Cuenta actual`.
- Cada cuenta cambia solo su propia contrasena confirmando la actual.
- Los cambios se auditan sin contrasenas, hashes, tokens ni correos completos.

## Instalacion

1. Respalda la base.
2. Ejecuta, en orden, `20261125_admin_panel_unificado.sql`,
   `20261125_opiniones_estados_finales.sql` y
   `20261125_opiniones_promocion_empresas.sql` con una cuenta autorizada para DDL.
3. Conserva la conexion y la clave privada fuera del webroot.
4. Inicia el document root `Admin/` en el puerto local documentado.
5. Verifica login, opiniones, usuarios, precios y auditoria antes de publicar.

No se modifica `PRODUCCION` manualmente. El despliegue se genera con el flujo
documentado del repositorio despues de completar QA.
