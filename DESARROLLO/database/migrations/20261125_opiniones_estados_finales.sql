-- RPV-Target: production
-- Transicion no destructiva de los estados historicos de testimonios.
ALTER TABLE testimonios
  MODIFY COLUMN estado ENUM('borrador','publicado','rechazado','publicada','eliminada') NOT NULL DEFAULT 'borrador';

UPDATE testimonios SET estado='publicada' WHERE estado='publicado';
UPDATE testimonios SET estado='eliminada', publicado_en=NULL WHERE estado='rechazado';

ALTER TABLE testimonios
  MODIFY COLUMN estado ENUM('borrador','publicada','eliminada') NOT NULL DEFAULT 'borrador';

CREATE INDEX IF NOT EXISTS idx_testimonios_estado_creado
  ON testimonios (estado, creado_en, id_testimonio);
