-- =====================================================================
-- DigiSecure - Migracion 027: permiso Tipos de documentos para admin_tenant
-- =====================================================================
-- Asegura que el rol admin_tenant pueda acceder al menu y funcionalidad:
--   Configuracion -> Tipos de documentos
-- Permiso: configuracion.documentos
-- =====================================================================

-- @up

INSERT IGNORE INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JBV8P0000CONFIG_DOC00001', 'configuracion.documentos', 'configuracion', 'documentos', 'Configurar tipos de documento del tenant', 1, UTC_TIMESTAMP(6));

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, p.id, UTC_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo = 'configuracion.documentos'
LEFT JOIN roles_permisos rp
       ON rp.role_id = r.id
      AND rp.permiso_id = p.id
WHERE r.codigo IN ('admin_tenant', 'superadmin')
  AND rp.role_id IS NULL;

-- @down

DELETE rp
FROM roles_permisos rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo = 'configuracion.documentos'
  AND r.codigo IN ('admin_tenant', 'superadmin');
