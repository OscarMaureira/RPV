-- RPV-Target: development-and-production
-- Extiende testimonios sin alterar ni eliminar registros existentes.
ALTER TABLE testimonios
  ADD COLUMN IF NOT EXISTS nombre_empresa VARCHAR(120) NULL AFTER empresa_pyme,
  ADD COLUMN IF NOT EXISTS url_empresa VARCHAR(2048) NULL AFTER nombre_empresa,
  ADD COLUMN IF NOT EXISTS autoriza_promocion TINYINT(1) NOT NULL DEFAULT 0 AFTER url_empresa;

-- Las opiniones históricas no se autorizan implícitamente para promoción.
UPDATE testimonios
SET autoriza_promocion=0
WHERE autoriza_promocion IS NULL;
