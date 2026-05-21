-- =====================================================================
-- DigiSecure - Migracion 016: Permisos superadmin para planes
-- =====================================================================
-- Objetivo:
--   1) Habilitar la administracion de catalogo global de planes
--      desde la seccion PLATAFORMA.
--   2) Asignar estos permisos a los roles que ya tienen
--      superadmin.panel (operador de plataforma).
-- =====================================================================

-- @up

INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01KSPN0PRM0SADMINPLNVR0001', 'superadmin.planes.ver', 'superadmin', 'planes.ver', 'Ver catalogo global de planes', 1, UTC_TIMESTAMP(6)),
    ('01KSPN0PRM0SADMINPLNGS0001', 'superadmin.planes.gestionar', 'superadmin', 'planes.gestionar', 'Crear y editar planes globales', 1, UTC_TIMESTAMP(6));

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT rp.role_id, '01KSPN0PRM0SADMINPLNVR0001', UTC_TIMESTAMP(6)
FROM roles_permisos rp
INNER JOIN permisos p ON p.id = rp.permiso_id
LEFT JOIN roles_permisos ya
       ON ya.role_id = rp.role_id
      AND ya.permiso_id = '01KSPN0PRM0SADMINPLNVR0001'
WHERE p.codigo = 'superadmin.panel'
  AND ya.role_id IS NULL;

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT rp.role_id, '01KSPN0PRM0SADMINPLNGS0001', UTC_TIMESTAMP(6)
FROM roles_permisos rp
INNER JOIN permisos p ON p.id = rp.permiso_id
LEFT JOIN roles_permisos ya
       ON ya.role_id = rp.role_id
      AND ya.permiso_id = '01KSPN0PRM0SADMINPLNGS0001'
WHERE p.codigo = 'superadmin.panel'
  AND ya.role_id IS NULL;

-- @down

DELETE FROM roles_permisos
WHERE permiso_id IN (
    '01KSPN0PRM0SADMINPLNVR0001',
    '01KSPN0PRM0SADMINPLNGS0001'
);

DELETE FROM permisos
WHERE id IN (
    '01KSPN0PRM0SADMINPLNVR0001',
    '01KSPN0PRM0SADMINPLNGS0001'
);

