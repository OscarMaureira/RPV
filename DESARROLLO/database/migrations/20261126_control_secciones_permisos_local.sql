-- SOLO DESARROLLO LOCAL.
-- Ejecutar con la cuenta administradora de MariaDB despues de crear secciones_sitio.
-- Se conceden exclusivamente los permisos usados por el sitio y el panel.
GRANT SELECT, UPDATE
ON `rpvconst_datamain_dev`.`secciones_sitio`
TO 'rpvconst_conectBD'@'127.0.0.1';
