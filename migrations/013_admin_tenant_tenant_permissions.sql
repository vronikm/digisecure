-- =====================================================================
-- DigiSecure - Migración 013: Permisos tenants.* para admin_tenant
-- =====================================================================
-- Corrige la asignación de permisos del rol admin_tenant para que pueda
-- ver/editar la información del tenant propio en el menú Configuración.
--
-- Impacto:
--   - Asigna tenants.ver y tenants.editar al rol admin_tenant
--   - Aplica tanto al rol de sistema como a clones por tenant
-- =====================================================================

-- @up

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, p.id, UTC_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p
        ON p.codigo IN ('tenants.ver', 'tenants.editar')
LEFT JOIN roles_permisos rp
       ON rp.role_id = r.id
      AND rp.permiso_id = p.id
WHERE r.codigo = 'admin_tenant'
  AND rp.role_id IS NULL;

-- @down

DELETE rp
FROM roles_permisos rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE r.codigo = 'admin_tenant'
  AND p.codigo IN ('tenants.ver', 'tenants.editar');

