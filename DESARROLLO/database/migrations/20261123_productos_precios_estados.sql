-- RPV-Target: development
CREATE TABLE IF NOT EXISTS catalogo_productos (
  id_producto VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  categoria ENUM('calderas','tinajas','estufas') NOT NULL,
  precio_clp INT UNSIGNED NOT NULL,
  modo_precio ENUM('referencial','vigente','consultar') NOT NULL DEFAULT 'referencial',
  rendimiento TEXT NOT NULL,
  incluye TEXT NOT NULL,
  instalacion TEXT NOT NULL,
  despacho TEXT NOT NULL,
  garantia TEXT NOT NULL,
  aprobado TINYINT(1) NOT NULL DEFAULT 0,
  revision CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actualizado_el DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS estados (
  codigo VARCHAR(20) NOT NULL PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO estados (codigo,nombre) VALUES ('referencial','Referencial'),('vigente','Vigente'),('consultar','A consultar');
CREATE TABLE IF NOT EXISTS productos (
  id_producto VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  categoria ENUM('calderas','tinajas','estufas') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS precios (
  id_producto VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  precio_clp INT UNSIGNED NOT NULL,
  estado VARCHAR(20) NOT NULL,
  link_pago VARCHAR(2048) NULL,
  CONSTRAINT fk_precios_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto),
  CONSTRAINT fk_precios_estado FOREIGN KEY (estado) REFERENCES estados(codigo),
  CONSTRAINT chk_precios_monto CHECK (precio_clp BETWEEN 1 AND 2000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;