-- RPV-Target: development
-- Valores de tinaja 7 y estufa confirmados por el usuario: 1 CLP cada uno.
UPDATE catalogo_productos SET precio_clp = 1 WHERE id_producto IN ('tinaja-7-personas','estufa-pellet');
UPDATE precios SET precio_clp = 1 WHERE id_producto IN ('tinaja-7-personas','estufa-pellet');
ALTER TABLE catalogo_productos
  DROP COLUMN vigente_hasta,
  DROP COLUMN condiciones,
  MODIFY COLUMN precio_clp INT UNSIGNED NOT NULL,
  ADD CONSTRAINT chk_catalogo_precio_nuevo CHECK (precio_clp BETWEEN 1 AND 2000000);
ALTER TABLE precios
  DROP COLUMN vigente_hasta,
  MODIFY COLUMN precio_clp INT UNSIGNED NOT NULL,
  ADD CONSTRAINT chk_precios_monto CHECK (precio_clp BETWEEN 1 AND 2000000);