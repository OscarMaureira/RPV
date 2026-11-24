-- RPV-Target: development
-- Integra las opiniones públicas en la tabla que consulta el gestor local.
-- Ejecutar únicamente en rpvconst_DataMain_dev, con permisos de ALTER/INSERT.
ALTER TABLE testimonios
  ADD COLUMN IF NOT EXISTS correo_privado VARCHAR(254) NULL AFTER nombre_publico;

-- Conserva en testimonios cualquier opinión que ya se hubiera recibido antes
-- del cambio de almacenamiento. El correo sigue sin mostrarse al público.
INSERT INTO testimonios
  (nombre_publico, correo_privado, ciudad, empresa_pyme, categoria, calificacion,
   comentario, consentimiento_publicacion, estado, creado_en, publicado_en)
SELECT
  o.nombre_publico,
  o.correo_privado,
  NULL,
  NULL,
  o.producto,
  o.calificacion,
  o.comentario,
  o.consentimiento_publicacion,
  CASE WHEN o.estado = 'aprobado' THEN 'publicado' ELSE 'borrador' END,
  o.creado_en,
  CASE WHEN o.estado = 'aprobado' THEN COALESCE(o.revisado_en, o.creado_en) ELSE NULL END
FROM opiniones_productos AS o
WHERE NOT EXISTS (
  SELECT 1
  FROM testimonios AS t
  WHERE t.correo_privado = o.correo_privado
    AND t.nombre_publico = o.nombre_publico
    AND t.comentario = o.comentario
    AND t.creado_en = o.creado_en
);
