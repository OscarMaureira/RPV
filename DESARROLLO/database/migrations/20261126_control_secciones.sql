-- Control extensible de disponibilidad publica de secciones comerciales.
-- Migracion no destructiva: las secciones existentes quedan activas por defecto.
CREATE TABLE IF NOT EXISTS secciones_sitio (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  usuario_actualizacion BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_secciones_sitio_clave (clave),
  KEY idx_secciones_sitio_orden (orden, id),
  KEY idx_secciones_sitio_usuario (usuario_actualizacion),
  CONSTRAINT fk_secciones_sitio_usuario
    FOREIGN KEY (usuario_actualizacion) REFERENCES admin_usuarios(id)
    ON UPDATE RESTRICT ON DELETE SET NULL,
  CONSTRAINT chk_secciones_sitio_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO secciones_sitio (clave, nombre, activo, orden)
VALUES
  ('tinajas', 'Tinajas', 1, 10),
  ('calderas', 'Calderas', 1, 20),
  ('estufas_pellets', 'Estufas a pellets', 1, 30)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  orden = VALUES(orden);
