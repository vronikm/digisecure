-- ============================================================================
-- DigiSecure - Fase 17: Normalizacion de collation en tenants
-- ----------------------------------------------------------------------------
-- Contexto:
--   En algunos entornos legacy la tabla `tenants` quedo en
--   `utf8mb4_spanish2_ci`, mientras el resto del esquema usa
--   `utf8mb4_unicode_ci`.
--
-- Impacto:
--   Provoca errores 1267 "Illegal mix of collations" en joins y comparaciones
--   (ej. login y resolucion de tenant).
--
-- Accion:
--   Homogeneizar tabla `tenants` al estandar del proyecto.
-- ============================================================================

-- @up

ALTER TABLE tenants
    CONVERT TO CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- @down
-- No reversible sin conocer collation previa del entorno.
