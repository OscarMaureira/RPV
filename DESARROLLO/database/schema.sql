-- Esquema local de desarrollo para RPV Construcciones.
-- Reconstruido desde la API guardar-ubicacion.php; reemplazar por un export de
-- cPanel antes de una réplica exacta de producción.

CREATE DATABASE IF NOT EXISTS rpvconst_DataMain_dev
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rpvconst_DataMain_dev;

CREATE TABLE IF NOT EXISTS ubicaciones_chat (
  id_ubicacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_conversacion VARCHAR(100) NULL,
  comuna VARCHAR(100) NULL,
  provincia VARCHAR(100) NULL,
  region VARCHAR(150) NULL,
  pais VARCHAR(100) NOT NULL DEFAULT 'Chile',
  latitud DECIMAL(10,7) NOT NULL,
  longitud DECIMAL(10,7) NOT NULL,
  precision_metros DECIMAL(12,2) NULL,
  url_google_maps VARCHAR(500) NOT NULL,
  fuente_ubicacion ENUM('navegador', 'manual') NOT NULL DEFAULT 'navegador',
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ubicacion),
  KEY idx_ubicaciones_chat_conversacion_fecha (id_conversacion, fecha_registro),
  KEY idx_ubicaciones_chat_fecha (fecha_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Conserva los testimonios existentes al actualizar una base ya inicializada.
ALTER TABLE testimonios
  ADD COLUMN IF NOT EXISTS empresa_pyme VARCHAR(120) NULL AFTER ciudad;

ALTER TABLE testimonios
  ADD COLUMN IF NOT EXISTS correo_privado VARCHAR(254) NULL AFTER nombre_publico;

-- Catálogo comercial local, fuente de las páginas y los asistentes.
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

-- Cuentas del panel local. Administrar usuarios desde el panel local.
CREATE TABLE IF NOT EXISTS admin_usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS estados (
  codigo VARCHAR(20) NOT NULL PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
