-- =====================================================================
-- DigiSecure - Migracion 014: Limpieza de rol superadmin en tenant
-- =====================================================================
-- Objetivo:
--   1) Evitar escalamiento: el rol superadmin solo debe existir a nivel
--      sistema (tenant_id IS NULL).
--   2) Reasignar usuarios que tengan superadmin de tenant al rol
--      admin_tenant (preferir rol del tenant; fallback a rol sistema).
--   3) Eliminar roles superadmin de tenant.
-- =====================================================================

-- @up

-- Paso 1: mover asignaciones superadmin de tenant -> admin_tenant.
UPDATE usuarios_roles ur
INNER JOIN roles r_super
        ON r_super.id = ur.role_id
       AND r_super.codigo = 'superadmin'
       AND r_super.tenant_id IS NOT NULL
INNER JOIN usuarios u
        ON u.id = ur.usuario_id
LEFT JOIN roles r_admin_tenant
       ON r_admin_tenant.tenant_id = u.tenant_id
      AND r_admin_tenant.codigo = 'admin_tenant'
LEFT JOIN roles r_admin_system
       ON r_admin_system.tenant_id IS NULL
      AND r_admin_system.codigo = 'admin_tenant'
SET ur.role_id = COALESCE(r_admin_tenant.id, r_admin_system.id),
    ur.assigned_at = UTC_TIMESTAMP(6)
WHERE COALESCE(r_admin_tenant.id, r_admin_system.id) IS NOT NULL;

-- Paso 2: si por colision PK el usuario ya tiene admin_tenant, borrar
-- la fila superadmin de tenant remanente.
DELETE ur
FROM usuarios_roles ur
INNER JOIN roles r_super
        ON r_super.id = ur.role_id
       AND r_super.codigo = 'superadmin'
       AND r_super.tenant_id IS NOT NULL
INNER JOIN usuarios u
        ON u.id = ur.usuario_id
LEFT JOIN roles r_admin_tenant
       ON r_admin_tenant.tenant_id = u.tenant_id
      AND r_admin_tenant.codigo = 'admin_tenant'
LEFT JOIN roles r_admin_system
       ON r_admin_system.tenant_id IS NULL
      AND r_admin_system.codigo = 'admin_tenant'
INNER JOIN usuarios_roles ur_admin
        ON ur_admin.usuario_id = ur.usuario_id
       AND ur_admin.role_id = COALESCE(r_admin_tenant.id, r_admin_system.id);

-- Paso 3: eliminar roles superadmin del ambito empresa.
DELETE FROM roles
WHERE codigo = 'superadmin'
  AND tenant_id IS NOT NULL;

-- @down
-- No reversible automaticamente: se eliminaron roles tenant superadmin
-- por seguridad.

