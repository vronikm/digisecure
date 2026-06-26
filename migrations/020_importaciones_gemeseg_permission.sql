-- =====================================================================
-- DigiSecure - Migracion 020: Permiso importacion Excel GEMESEG
-- =====================================================================
-- Habilita la opcion web de importacion de Excel GEMESEG.
-- Es una operacion masiva con datos personales, por eso se asigna solo a
-- administradores del tenant y superadmin.
-- =====================================================================

-- @up

INSERT IGNORE INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01KIMP0GEMESEG000000000001', 'importaciones.gemeseg', 'importaciones', 'gemeseg', 'Importar Excel GEMESEG a staging cifrado', 1, UTC_TIMESTAMP(6));

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, '01KIMP0GEMESEG000000000001', UTC_TIMESTAMP(6)
FROM roles r
LEFT JOIN roles_permisos rp
       ON rp.role_id = r.id
      AND rp.permiso_id = '01KIMP0GEMESEG000000000001'
WHERE r.codigo IN ('admin_tenant', 'superadmin')
  AND rp.role_id IS NULL;

-- @down

DELETE FROM roles_permisos WHERE permiso_id = '01KIMP0GEMESEG000000000001';
DELETE FROM permisos WHERE id = '01KIMP0GEMESEG000000000001';
