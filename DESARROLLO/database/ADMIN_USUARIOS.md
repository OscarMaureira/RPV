# Cuentas del panel local

El panel de productos, precios y testimonios comparte la tabla admin_usuarios en rpvconst_DataMain_dev.

La gestión de cuentas está disponible en http://localhost:8002/cuentas.php para usuarios autenticados. Desde allí se pueden crear, editar, activar, desactivar y eliminar cuentas. Una contraseña nueva debe tener entre 7 y 128 caracteres; se guarda únicamente su hash. Dejar ese campo vacío al editar conserva la contraseña anterior.

El sistema impide borrar o desactivar la cuenta en uso y exige que quede al menos una cuenta activa. Al cambiar una contraseña, las sesiones que usaban la contraseña anterior dejan de ser válidas.

La cuenta de conexión rpvconst_conectBD necesita SELECT, INSERT, UPDATE y DELETE sobre admin_usuarios para habilitar todas las acciones. El acceso al panel está limitado a localhost:8002.
## Registro de auditoría

La tabla admin_log guarda inicio de sesión, cada entrada a Cuentas o Productos y los cambios de cuentas y precios. Registra actor, fecha, entidad y valores comerciales anteriores y nuevos. No registra contraseñas ni hashes. Las eliminaciones conservan el nombre de la cuenta en el LOG aunque la cuenta ya no exista.
