-- =====================================================================
-- DigiSecure - Migración 008: Registro público de tenants (Fase 12)
-- =====================================================================
-- Habilita el auto-registro de nuevas empresas desde una página pública.
-- El flujo es:
--
--   1. Empresa completa formulario en /registro
--   2. Se crea el tenant con estado='pendiente' + admin user (inactivo)
--   3. Se envía email con enlace /activar/{token}
--   4. Al abrir el enlace: tenant pasa a 'activo', usuario a 'activo'
--   5. Se genera ciclo inicial de plan trial (30 días)
--
-- Añadimos al schema:
--   - tenants.token_activacion_hash (SHA256, nunca en claro)
--   - tenants.token_activacion_expira_at
--   - tenants.activado_at (DATETIME — NULL hasta que se activa)
--   - tabla registros_empresa (audit trail de intentos, por IP)
-- =====================================================================

-- @up

ALTER TABLE tenants
    ADD COLUMN token_activacion_hash CHAR(64) NULL AFTER plan_vencimiento,
    ADD COLUMN token_activacion_expira_at DATETIME(6) NULL AFTER token_activacion_hash,
    ADD COLUMN activado_at DATETIME(6) NULL AFTER token_activacion_expira_at,
    ADD KEY ix_tenants_token_activacion (token_activacion_hash);

-- Tabla de control: intentos de registro por IP (rate limit anti-spam).
-- No es la misma que auditoria porque los registros NO están asociados a un
-- tenant_id todavía (tenant_id=NULL está prohibido en auditoria).
CREATE TABLE registros_empresa (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id CHAR(26) NULL,
    email_admin VARCHAR(190) NOT NULL,
    ruc CHAR(13) NOT NULL,
    razon_social VARCHAR(255) NOT NULL,
    ip VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    evento ENUM('creado','activado','expirado','reenviado','fallo') NOT NULL,
    detalles_json JSON NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_registros_email (email_admin, created_at),
    KEY ix_registros_ruc (ruc),
    KEY ix_registros_ip_fecha (ip, created_at),
    KEY ix_registros_tenant (tenant_id),
    CONSTRAINT fk_registros_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- @down

DROP TABLE IF EXISTS registros_empresa;

ALTER TABLE tenants
    DROP KEY ix_tenants_token_activacion,
    DROP COLUMN activado_at,
    DROP COLUMN token_activacion_expira_at,
    DROP COLUMN token_activacion_hash;
