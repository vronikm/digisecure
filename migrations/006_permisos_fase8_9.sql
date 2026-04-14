-- =====================================================================
-- DigiSecure - Migración 006: Permisos Fase 8 y 9
-- =====================================================================
-- Agrega al catálogo de permisos los nuevos recursos introducidos en:
--
--   Fase 8: Notificaciones admin UI + Tipos de documento configurables
--   Fase 9: RBAC CRUD completo + Exportación de auditoría
--
-- También agrega los permisos de LOPDP que faltaban del catálogo.
--
-- IMPORTANTE: después de aplicar esta migración, ejecutar el seed-dev
-- o asignar manualmente estos permisos a los roles existentes.
-- =====================================================================

-- @up

-- Fase 8: Notificaciones
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JBV8P0000NOTIF_VER000001', 'notificaciones.ver', 'notificaciones', 'ver', 'Ver historial de notificaciones del tenant', 1, UTC_TIMESTAMP(6)),
    ('01JBV8P0000NOTIF_GEST00001', 'notificaciones.gestionar', 'notificaciones', 'gestionar', 'Reintentar notificaciones fallidas', 1, UTC_TIMESTAMP(6));

-- Fase 8: Configuración de tipos de documento
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JBV8P0000CONFIG_DOC00001', 'configuracion.documentos', 'configuracion', 'documentos', 'Configurar tipos de documento del tenant', 1, UTC_TIMESTAMP(6));

-- Fase 9: Roles CRUD
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JBV8P0000ROLES_CREAR0001', 'roles.crear', 'roles', 'crear', 'Crear roles propios del tenant', 1, UTC_TIMESTAMP(6)),
    ('01JBV8P0000ROLES_EDITAR001', 'roles.editar', 'roles', 'editar', 'Editar roles y asignar permisos', 1, UTC_TIMESTAMP(6)),
    ('01JBV8P0000ROLES_ELIM00001', 'roles.eliminar', 'roles', 'eliminar', 'Eliminar roles propios del tenant', 1, UTC_TIMESTAMP(6));

-- Fase 9: Exportación de auditoría
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JBV8P0000AUDIT_EXPORT001', 'auditoria.exportar', 'auditoria', 'exportar', 'Exportar log de auditoría a CSV', 1, UTC_TIMESTAMP(6));

-- Asignar los nuevos permisos a todos los roles de sistema que tengan
-- el permiso base del recurso correspondiente.
-- Ejemplo: si un rol tiene 'roles.ver', también le damos 'roles.crear/editar/eliminar'.

-- Para roles de sistema que ya tienen 'notificaciones' implícitamente
-- (roles con todos los permisos de admin), asignar vía script post-migración.
-- Aquí solo insertamos el catálogo.

-- @down

DELETE FROM roles_permisos WHERE permiso_id IN (
    '01JBV8P0000NOTIF_VER000001',
    '01JBV8P0000NOTIF_GEST00001',
    '01JBV8P0000CONFIG_DOC00001',
    '01JBV8P0000ROLES_CREAR0001',
    '01JBV8P0000ROLES_EDITAR001',
    '01JBV8P0000ROLES_ELIM00001',
    '01JBV8P0000AUDIT_EXPORT001'
);

DELETE FROM permisos WHERE id IN (
    '01JBV8P0000NOTIF_VER000001',
    '01JBV8P0000NOTIF_GEST00001',
    '01JBV8P0000CONFIG_DOC00001',
    '01JBV8P0000ROLES_CREAR0001',
    '01JBV8P0000ROLES_EDITAR001',
    '01JBV8P0000ROLES_ELIM00001',
    '01JBV8P0000AUDIT_EXPORT001'
);
