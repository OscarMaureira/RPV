# Cuentas del panel

El panel reutiliza `admin_usuarios` en MariaDB. No hay roles administrables:
cada fila activa representa una cuenta administrativa y el acceso siempre exige
una sesion autenticada valida.

Las cuentas nuevas requieren usuario unico, correo valido y unico y una
contrasena inicial de 12 a 128 caracteres. La contrasena se guarda solo como
hash. Las cuentas distintas de `admin` permiten modificar el correo desde el
panel. La cuenta literal `admin` no permite cambiar nombre, correo, estado ni
eliminarse; la restriccion se valida en el servidor.

El sistema impide desactivar o eliminar la cuenta de la sesion y exige al menos
una cuenta activa. Los cambios se registran en `admin_log` sin secretos.

La cuenta web usa `SELECT`, `INSERT`, `UPDATE` y `DELETE`. Las migraciones deben
ejecutarse por separado con permisos `CREATE`, `ALTER` y `REFERENCES`.
