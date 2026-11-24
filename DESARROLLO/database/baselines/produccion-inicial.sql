-- Esquema inicial de PRODUCCION, importado del export cPanel adjunto el 2026-11-12.
-- Solo contiene estructura; se excluyeron sentencias INSERT y valores de datos.
-- Usar como referencia de partida para futuras migraciones SQL productivas.

CREATE TABLE `ubicaciones_chat` (
  `id_ubicacion` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_conversacion` VARCHAR(100) DEFAULT NULL,
  `comuna` VARCHAR(100) DEFAULT NULL,
  `provincia` VARCHAR(100) DEFAULT NULL,
  `region` VARCHAR(150) DEFAULT NULL,
  `pais` VARCHAR(100) NOT NULL DEFAULT 'Chile',
  `latitud` DECIMAL(10,8) NOT NULL,
  `longitud` DECIMAL(11,8) NOT NULL,
  `precision_metros` DECIMAL(10,2) DEFAULT NULL,
  `url_google_maps` VARCHAR(500) DEFAULT NULL,
  `fuente_ubicacion` VARCHAR(50) DEFAULT NULL,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `fecha_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_ubicacion`),
  KEY `idx_conversacion` (`id_conversacion`),
  KEY `idx_comuna` (`comuna`),
  KEY `idx_region` (`region`),
  KEY `idx_fecha` (`fecha_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
