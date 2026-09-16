-- =====================================================================
-- DigiSecure - Migracion 033: collation de tablas posteriores a la 018
-- =====================================================================
-- Objetivo:
--   Completar la normalizacion a utf8mb4_unicode_ci (estandar del proyecto,
--   compatible con MariaDB/cPanel) para las tablas creadas despues de la
--   migracion 018, y fijar el default de la base para tablas futuras.
--
-- Contexto:
--   En entornos donde 021-032 se aplicaron antes que 018 (orden no lineal),
--   `aspirantes` y `aspirante_eventos` quedaron en utf8mb4_0900_ai_ci y la
--   026 declaraba 0900_ai_ci. La mezcla provoca 1267 "Illegal mix of
--   collations" en joins con usuarios/fichas y 3780 al convertir tablas
--   con FK entre si.
--
-- Idempotente: en tablas que ya estan en la collation objetivo no cambia
-- datos. FK checks apagados solo durante la conversion (ver 018).
-- =====================================================================

-- @up

SET FOREIGN_KEY_CHECKS = 0;

ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE aspirantes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE aspirante_eventos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE instituciones_financieras CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE catalogo_vacunas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas_vacunas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE importacion_excel_registros CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas_familiares CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- @down
-- No reversible sin conocer la collation historica de cada entorno.
