-- RPV-Target: production
-- Aplicar una sola vez desde cPanel/phpMyAdmin despues de respaldar la base.
-- Agrega seguridad de sesiones sin introducir roles administrables.
ALTER TABLE admin_usuarios
  ADD COLUMN IF NOT EXISTS correo VARCHAR(254) NULL AFTER usuario,
  ADD COLUMN IF NOT EXISTS totp_secreto TEXT NULL AFTER activo,
  ADD COLUMN IF NOT EXISTS totp_habilitado TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secreto,
  ADD COLUMN IF NOT EXISTS credenciales_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER totp_habilitado;

CREATE UNIQUE INDEX IF NOT EXISTS uq_admin_usuarios_correo ON admin_usuarios(correo);

CREATE TABLE IF NOT EXISTS AdmPanel_sesiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultima_actividad TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en TIMESTAMP NOT NULL,
  revocada_en TIMESTAMP NULL,
  KEY idx_AdmPanel_sesion_usuario (usuario_id, revocada_en),
  CONSTRAINT fk_AdmPanel_sesion_usuario FOREIGN KEY (usuario_id) REFERENCES admin_usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS AdmPanel_intentos (
  clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  usuario_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_intento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bloqueado_hasta TIMESTAMP NULL,
  KEY idx_AdmPanel_intentos_limpieza (ultimo_intento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS AdmPanel_recuperacion_totp (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  codigo_hash VARCHAR(255) NOT NULL,
  usado_en TIMESTAMP NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_AdmPanel_recuperacion_usuario (usuario_id, usado_en),
  CONSTRAINT fk_AdmPanel_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES admin_usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
