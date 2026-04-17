-- =====================================================================
-- DigiSecure - Migración 009: Panel superadmin global (Fase 13)
-- =====================================================================
-- Añade permisos exclusivos para el operador de la plataforma (superadmin).
-- Estos permisos protegen las rutas /admin/* que muestran datos globales
-- (todos los tenants, registros, métricas cross-tenant).
--
-- Permisos nuevos:
--   superadmin.panel            → acceso al dashboard global
--   superadmin.tenants.ver      → listado / detalle de cualquier tenant
--   superadmin.tenants.gestionar → cambiar plan, suspender/reactivar
--   superadmin.registros.ver    → ver intentos de registro público
-- =====================================================================

-- @up

INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JCSA0PERM0SADMIN_PNL001', 'superadmin.panel',             'superadmin', 'panel',     'Acceso al panel global del operador',   1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0SADMIN_TVR01', 'superadmin.tenants.ver',       'superadmin', 'tenants.ver',      'Ver listado y detalle de todos los tenants', 1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0SADMIN_TGS01', 'superadmin.tenants.gestionar', 'superadmin', 'tenants.gestionar','Cambiar plan, suspender o reactivar tenants', 1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0SADMIN_RGV01', 'superadmin.registros.ver',     'superadmin', 'registros.ver',    'Ver log de registros públicos de empresa', 1, UTC_TIMESTAMP(6));

-- Asignar al rol superadmin (SEEDRBC0000000000000000001)
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000001', '01JCSA0PERM0SADMIN_PNL001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000001', '01JCSA0PERM0SADMIN_TVR01', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000001', '01JCSA0PERM0SADMIN_TGS01', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000001', '01JCSA0PERM0SADMIN_RGV01', UTC_TIMESTAMP(6));

-- @down

DELETE FROM roles_permisos WHERE permiso_id IN (
    '01JCSA0PERM0SADMIN_PNL001',
    '01JCSA0PERM0SADMIN_TVR01',
    '01JCSA0PERM0SADMIN_TGS01',
    '01JCSA0PERM0SADMIN_RGV01'
);

DELETE FROM permisos WHERE id IN (
    '01JCSA0PERM0SADMIN_PNL001',
    '01JCSA0PERM0SADMIN_TVR01',
    '01JCSA0PERM0SADMIN_TGS01',
    '01JCSA0PERM0SADMIN_RGV01'
);
