-- =====================================================================
-- DigiSecure - Migracion 024: aprobacion de aspirantes (Fase 4)
-- =====================================================================
-- Un reclutador/admin revisa los aspirantes con email verificado y los
-- aprueba o rechaza:
--   - Aprobar: se crea un `usuario` activo (reutilizando email + password_hash
--     del aspirante) en el tenant del aprobador; el aspirante pasa a 'aprobado'
--     y queda enlazado al usuario creado (usuario_id).
--   - Rechazar: el aspirante pasa a 'descartado' (con motivo opcional).
--
-- Cambios de schema:
--   - estado: se agrega el valor 'aprobado'.
--   - usuario_id: enlace al usuario creado al aprobar (FK -> usuarios).
--   - aprobado_at / revisado_por / motivo_descarte: traza de la revision.
--   - permiso aspirantes.gestionar + grant a admin_tenant y supervisor.
-- =====================================================================

-- @up

ALTER TABLE aspirantes
    MODIFY COLUMN estado ENUM('nuevo','en_revision','contactado','aprobado','descartado','contratado') NOT NULL DEFAULT 'nuevo',
    ADD COLUMN usuario_id CHAR(26) NULL AFTER tenant_id,
    ADD COLUMN aprobado_at DATETIME(6) NULL AFTER email_verificado_at,
    ADD COLUMN revisado_por CHAR(26) NULL AFTER aprobado_at,
    ADD COLUMN motivo_descarte VARCHAR(255) NULL AFTER revisado_por,
    ADD KEY ix_aspirantes_usuario (usuario_id),
    ADD CONSTRAINT fk_aspirantes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT;

INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('SEEDPERMASPIRANTGESTION001', 'aspirantes.gestionar', 'aspirantes', 'gestionar', 'Revisar, aprobar y rechazar aspirantes (postulantes)', 1, UTC_TIMESTAMP(6));

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, p.id, UTC_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo = 'aspirantes.gestionar'
LEFT JOIN roles_permisos rp ON rp.role_id = r.id AND rp.permiso_id = p.id
WHERE r.codigo IN ('admin_tenant', 'supervisor')
  AND rp.role_id IS NULL;

-- @down

DELETE rp FROM roles_permisos rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo = 'aspirantes.gestionar';

DELETE FROM permisos WHERE codigo = 'aspirantes.gestionar';

ALTER TABLE aspirantes DROP FOREIGN KEY fk_aspirantes_usuario;

ALTER TABLE aspirantes
    DROP KEY ix_aspirantes_usuario,
    DROP COLUMN motivo_descarte,
    DROP COLUMN revisado_por,
    DROP COLUMN aprobado_at,
    DROP COLUMN usuario_id,
    MODIFY COLUMN estado ENUM('nuevo','en_revision','contactado','descartado','contratado') NOT NULL DEFAULT 'nuevo';
