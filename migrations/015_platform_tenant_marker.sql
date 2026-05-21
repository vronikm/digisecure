-- =====================================================================
-- DigiSecure - Migracion 015: Tenant de plataforma explicito
-- =====================================================================
-- Objetivo:
--   1) Marcar de forma explicita el tenant de plataforma/sistema.
--   2) Garantizar que solo exista un tenant marcado como plataforma.
--
-- Regla:
--   - tenants.platform_marker = 'Y' identifica el tenant de plataforma.
--   - El campo es unico y nullable: varios NULL, un solo 'Y'.
-- =====================================================================

-- @up

ALTER TABLE tenants
    ADD COLUMN platform_marker CHAR(1) NULL AFTER locale;

ALTER TABLE tenants
    ADD CONSTRAINT chk_tenants_platform_marker
    CHECK (platform_marker IS NULL OR platform_marker = 'Y');

ALTER TABLE tenants
    ADD UNIQUE KEY uq_tenants_platform_marker (platform_marker);

-- Seleccionar tenant de plataforma:
-- 1) tenant del usuario con rol global superadmin
-- 2) tenant con razon social "DigiSecure Plataforma S.A."
-- 3) primer tenant por created_at
SET @platform_tid := (
    SELECT u.tenant_id
      FROM usuarios u
      INNER JOIN usuarios_roles ur ON ur.usuario_id = u.id
      INNER JOIN roles r ON r.id = ur.role_id
     WHERE r.codigo = 'superadmin'
       AND r.tenant_id IS NULL
     ORDER BY u.created_at ASC
     LIMIT 1
);

SET @platform_tid := COALESCE(
    @platform_tid,
    (
        SELECT id
          FROM tenants
         WHERE razon_social = 'DigiSecure Plataforma S.A.'
         ORDER BY created_at ASC
         LIMIT 1
    ),
    (
        SELECT id
          FROM tenants
         ORDER BY created_at ASC
         LIMIT 1
    )
);

UPDATE tenants SET platform_marker = NULL;
UPDATE tenants
   SET platform_marker = 'Y'
 WHERE id = @platform_tid;

-- @down

ALTER TABLE tenants DROP INDEX uq_tenants_platform_marker;
ALTER TABLE tenants DROP CHECK chk_tenants_platform_marker;
ALTER TABLE tenants DROP COLUMN platform_marker;

