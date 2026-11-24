-- RPV-Target: production
-- Crea el almacenamiento requerido por las opiniones y asegura sus columnas adicionales.
CREATE TABLE IF NOT EXISTS testimonios (
  id_testimonio BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_publico VARCHAR(80) NOT NULL,
  correo_privado VARCHAR(254) NULL,
  ciudad VARCHAR(80) NULL,
  empresa_pyme VARCHAR(120) NULL,
  categoria VARCHAR(30) NOT NULL DEFAULT 'General',
  calificacion TINYINT UNSIGNED NULL,
  comentario TEXT NOT NULL,
  consentimiento_publicacion TINYINT(1) NOT NULL DEFAULT 0,
  estado ENUM('borrador', 'publicado') NOT NULL DEFAULT 'borrador',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  publicado_en TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id_testimonio),
  KEY idx_testimonios_estado_fecha (estado, publicado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE testimonios
  ADD COLUMN IF NOT EXISTS empresa_pyme VARCHAR(120) NULL AFTER ciudad;

ALTER TABLE testimonios
  ADD COLUMN IF NOT EXISTS correo_privado VARCHAR(254) NULL AFTER nombre_publico;
