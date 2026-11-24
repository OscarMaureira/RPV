-- RPV-Target: development
-- Opiniones públicas de productos. Ejecutar únicamente en rpvconst_DataMain_dev.
CREATE TABLE IF NOT EXISTS opiniones_productos (
  id_opinion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_publico VARCHAR(80) NOT NULL,
  correo_privado VARCHAR(254) NOT NULL,
  producto VARCHAR(40) NOT NULL,
  calificacion TINYINT UNSIGNED NOT NULL,
  comentario VARCHAR(1200) NOT NULL,
  consentimiento_publicacion TINYINT(1) NOT NULL DEFAULT 0,
  estado ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revisado_en TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id_opinion),
  KEY idx_opiniones_estado_fecha (estado, creado_en),
  KEY idx_opiniones_producto_estado (producto, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
