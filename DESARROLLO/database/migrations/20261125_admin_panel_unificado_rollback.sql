-- RPV-Target: production
-- REVERSA DE EMERGENCIA. Respaldar la base y desactivar TOTP antes de ejecutar.
DROP TABLE IF EXISTS AdmPanel_recuperacion_totp;
DROP TABLE IF EXISTS AdmPanel_intentos;
DROP TABLE IF EXISTS AdmPanel_sesiones;

ALTER TABLE admin_usuarios
  DROP COLUMN IF EXISTS credenciales_version,
  DROP COLUMN IF EXISTS totp_habilitado,
  DROP COLUMN IF EXISTS totp_secreto,
  DROP COLUMN IF EXISTS correo;
