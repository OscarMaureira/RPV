-- RPV-Target: development
-- Catálogo administrado localmente. El sitio publicado consume sus artefactos generados.
CREATE TABLE IF NOT EXISTS catalogo_productos (
  id_producto VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  categoria ENUM('calderas', 'tinajas', 'estufas') NOT NULL,
  precio_clp INT UNSIGNED NOT NULL,
  modo_precio ENUM('referencial', 'vigente', 'consultar') NOT NULL DEFAULT 'referencial',
  rendimiento TEXT NOT NULL,
  incluye TEXT NOT NULL,
  instalacion TEXT NOT NULL,
  despacho TEXT NOT NULL,
  garantia TEXT NOT NULL,
  aprobado TINYINT(1) NOT NULL DEFAULT 0,
  revision CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actualizado_el DATETIME NULL COMMENT 'Fecha UTC de la última edición',
  PRIMARY KEY (id_producto),
  KEY idx_catalogo_categoria (categoria),
  CONSTRAINT chk_catalogo_precio CHECK (precio_clp BETWEEN 1 AND 2000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
