-- RPV-Target: development
CREATE TABLE IF NOT EXISTS admin_log (
  id_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  evento VARCHAR(40) NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  actor_usuario VARCHAR(80) NOT NULL,
  entidad VARCHAR(30) NOT NULL,
  entidad_id VARCHAR(80) NULL,
  detalle TEXT NULL,
  ip VARCHAR(45) NULL,
  KEY idx_admin_log_fecha (fecha),
  KEY idx_admin_log_actor (actor_usuario, fecha),
  KEY idx_admin_log_entidad (entidad, entidad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;