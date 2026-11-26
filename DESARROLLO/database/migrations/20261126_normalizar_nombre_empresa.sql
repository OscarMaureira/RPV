-- RPV-Target: production
-- Consolida el nombre comercial en testimonios.nombre_empresa.
-- Si ambos campos contienen datos, prevalece nombre_empresa; la autorizacion
-- promocional historica no se modifica ni se concede implicitamente.
ALTER TABLE testimonios
  ADD COLUMN IF NOT EXISTS empresa_pyme VARCHAR(120) NULL AFTER ciudad,
  ADD COLUMN IF NOT EXISTS nombre_empresa VARCHAR(120) NULL AFTER ciudad;

UPDATE testimonios
SET nombre_empresa=CASE
  WHEN NULLIF(TRIM(nombre_empresa),'') IS NOT NULL THEN TRIM(nombre_empresa)
  ELSE NULLIF(TRIM(empresa_pyme),'')
END;

ALTER TABLE testimonios
  DROP COLUMN IF EXISTS empresa_pyme,
  MODIFY COLUMN nombre_empresa VARCHAR(120) NULL AFTER ciudad;
