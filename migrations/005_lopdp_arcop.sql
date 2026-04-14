-- =====================================================================
-- DigiSecure - Migración 005: LOPDP — Solicitudes ARCO-P y retención
-- =====================================================================
-- Implementa los requisitos operativos de la Ley Orgánica de Protección
-- de Datos Personales del Ecuador (LOPDP):
--
--   1. Solicitudes de derechos ARCO-P (Acceso, Rectificación,
--      Cancelación, Oposición, Portabilidad) con plazo legal de 15 días.
--   2. Campos de baja y anonimización en `fichas` para el job de
--      retención de 5 años (decisión vinculante #9).
--   3. Índice extra en `consentimientos_lopdp` por titular+propósito
--      para el chequeo rápido "¿este titular ya consintió X?".
--
-- Convenciones:
--   - PK CHAR(26) ULID
--   - tenant-aware (tenant_id NOT NULL)
--   - DATETIME(6) UTC, ROW_FORMAT=DYNAMIC, utf8mb4_0900_ai_ci
-- =====================================================================

-- @up

-- -----------------------------------------------------------------
-- SOLICITUDES ARCO-P
-- -----------------------------------------------------------------
-- Una fila por solicitud recibida de un titular de datos (guardia o
-- usuario). Plazo legal: 15 días calendario para responder.
-- El ciclo de vida es: RECIBIDA → EN_PROCESO → RESPONDIDA | DENEGADA.
-- -----------------------------------------------------------------
CREATE TABLE lopdp_solicitudes_arcop (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,

    -- Titular
    titular_tipo ENUM('guardia','usuario','externo') NOT NULL,
    titular_id CHAR(26) NULL COMMENT 'NULL si titular externo (no tiene cuenta)',
    titular_nombre VARCHAR(200) NOT NULL,
    titular_cedula VARCHAR(20) NULL,
    titular_email VARCHAR(190) NULL,
    titular_telefono VARCHAR(30) NULL,

    -- Solicitud
    tipo ENUM('acceso','rectificacion','cancelacion','oposicion','portabilidad') NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_recepcion DATE NOT NULL,
    fecha_limite DATE NOT NULL COMMENT 'recepcion + 15 días calendario',
    canal_recepcion ENUM('email','telefono','presencial','formulario_web') NOT NULL DEFAULT 'email',
    estado ENUM('RECIBIDA','EN_PROCESO','RESPONDIDA','DENEGADA') NOT NULL DEFAULT 'RECIBIDA',

    -- Respuesta
    respondida_at DATETIME(6) NULL,
    respondida_por CHAR(26) NULL,
    respuesta_texto TEXT NULL,
    denegada_motivo VARCHAR(500) NULL,

    -- Auditoría
    created_by CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_lopdp_solicitudes_tenant_estado (tenant_id, estado),
    KEY ix_lopdp_solicitudes_tenant_fecha_limite (tenant_id, fecha_limite),
    KEY ix_lopdp_solicitudes_tenant_tipo (tenant_id, tipo),
    KEY ix_lopdp_solicitudes_titular (tenant_id, titular_tipo, titular_id),
    CONSTRAINT fk_lopdp_solicitudes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_lopdp_solicitudes_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT fk_lopdp_solicitudes_respondedor FOREIGN KEY (respondida_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- FICHAS: campos de baja y anonimización
-- -----------------------------------------------------------------
-- Necesarios para el job de retención LOPDP (5 años post-baja).
-- `fecha_baja` marca el inicio del conteo. `anonimizada_at` indica
-- que el registro ya pasó por el proceso de anonimización (los campos
-- con PII fueron neutralizados, pero la fila se conserva para integridad
-- referencial y auditoría estadística).
-- -----------------------------------------------------------------
ALTER TABLE fichas
    ADD COLUMN fecha_baja DATE NULL COMMENT 'Fecha de baja del guardia, inicia conteo de retención LOPDP' AFTER fecha_revision,
    ADD COLUMN motivo_baja VARCHAR(255) NULL AFTER fecha_baja,
    ADD COLUMN anonimizada_at DATETIME(6) NULL COMMENT 'Momento en que el job ejecutó la anonimización' AFTER updated_at,
    ADD KEY ix_fichas_tenant_baja (tenant_id, fecha_baja),
    ADD KEY ix_fichas_anonimizada (anonimizada_at);


-- -----------------------------------------------------------------
-- CONSENTIMIENTOS LOPDP: índice por titular+propósito
-- -----------------------------------------------------------------
-- Optimiza la query "¿este titular tiene consentimiento vigente para X?"
-- que el ConsentManager ejecuta en cada flujo sensible.
-- -----------------------------------------------------------------
CREATE INDEX ix_consentimientos_tenant_titular_proposito
    ON consentimientos_lopdp (tenant_id, titular_tipo, titular_id, proposito);


-- @down

ALTER TABLE fichas
    DROP KEY ix_fichas_anonimizada,
    DROP KEY ix_fichas_tenant_baja,
    DROP COLUMN anonimizada_at,
    DROP COLUMN motivo_baja,
    DROP COLUMN fecha_baja;

DROP INDEX ix_consentimientos_tenant_titular_proposito ON consentimientos_lopdp;

DROP TABLE IF EXISTS lopdp_solicitudes_arcop;
