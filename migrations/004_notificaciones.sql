-- =====================================================================
-- DigiSecure - Migración 004: Sistema de Notificaciones
-- =====================================================================
-- Sistema de cola y log de envíos para alertas multicanal (email, sms,
-- whatsapp, push). El uso inicial es alertar vencimientos próximos de
-- documentos del expediente, pero el modelo es genérico para reutilizar
-- en cualquier evento que requiera notificar a usuarios.
--
-- Tablas creadas:
--   - notificaciones      (cola: pending → sent | failed)
--   - notificaciones_log  (registro inmutable de cada intento de envío)
--
-- Convenciones:
--   - PK CHAR(26) ULID
--   - tenant_id NOT NULL en todas las tablas tenant-aware
--   - DATETIME(6) UTC, ROW_FORMAT=DYNAMIC, utf8mb4_unicode_ci
--   - payload_json para datos arbitrarios renderizables por la plantilla
-- =====================================================================

-- @up

-- -----------------------------------------------------------------
-- COLA DE NOTIFICACIONES
-- -----------------------------------------------------------------
-- Una fila por notificación a entregar. La cron lee filas con
-- estado='PENDIENTE' y scheduled_at <= NOW() y las procesa.
-- -----------------------------------------------------------------
CREATE TABLE notificaciones (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,

    -- Tipo de evento (vinculado al template/asunto)
    evento VARCHAR(80) NOT NULL COMMENT 'p.ej. expediente.documento.por_vencer, expediente.documento.vencido',
    canal ENUM('EMAIL','SMS','WHATSAPP','PUSH') NOT NULL DEFAULT 'EMAIL',
    prioridad ENUM('BAJA','NORMAL','ALTA','URGENTE') NOT NULL DEFAULT 'NORMAL',

    -- Destinatario
    destinatario_usuario_id CHAR(26) NULL COMMENT 'Si está vinculado a un usuario del sistema',
    destinatario_email VARCHAR(190) NULL,
    destinatario_telefono VARCHAR(40) NULL,
    destinatario_nombre VARCHAR(160) NULL,

    -- Contenido
    asunto VARCHAR(255) NOT NULL,
    cuerpo_texto TEXT NULL COMMENT 'Versión texto plano',
    cuerpo_html MEDIUMTEXT NULL COMMENT 'Versión HTML (opcional)',
    payload_json JSON NULL COMMENT 'Variables/contexto del template',

    -- Vínculo opcional con entidades para deduplicar y trazar
    entidad_tipo VARCHAR(60) NULL COMMENT 'p.ej. expediente_documento, ficha',
    entidad_id CHAR(26) NULL,
    dedupe_key VARCHAR(190) NULL COMMENT 'Hash/clave para evitar duplicados (tenant_id + dedupe_key UNIQUE)',

    -- Programación / estado
    estado ENUM('PENDIENTE','ENVIADA','FALLIDA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
    scheduled_at DATETIME(6) NOT NULL COMMENT 'No procesar antes de esta fecha',
    enviada_at DATETIME(6) NULL,
    intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_intentos SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    ultimo_error VARCHAR(500) NULL,

    -- Auditoría
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_notif_dedupe (tenant_id, dedupe_key),
    KEY ix_notif_pending (tenant_id, estado, scheduled_at),
    KEY ix_notif_entidad (tenant_id, entidad_tipo, entidad_id),
    KEY ix_notif_destinatario (tenant_id, destinatario_usuario_id),
    KEY ix_notif_evento (tenant_id, evento, created_at),
    CONSTRAINT fk_notif_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_notif_usuario FOREIGN KEY (destinatario_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- LOG INMUTABLE DE ENTREGAS
-- -----------------------------------------------------------------
-- Cada intento de envío (exitoso o fallido) deja una fila aquí.
-- Permite auditar latencias, motivos de error, gateways, y mantener
-- evidencia LOPDP de notificaciones realizadas. BIGINT por volumen.
-- -----------------------------------------------------------------
CREATE TABLE notificaciones_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id CHAR(26) NOT NULL,
    notificacion_id CHAR(26) NOT NULL,

    canal ENUM('EMAIL','SMS','WHATSAPP','PUSH') NOT NULL,
    destinatario VARCHAR(255) NOT NULL COMMENT 'email/teléfono efectivo usado en este intento',
    resultado ENUM('OK','ERROR') NOT NULL,
    codigo_respuesta VARCHAR(40) NULL COMMENT 'Código del gateway (SMTP code, HTTP status, etc.)',
    mensaje VARCHAR(500) NULL COMMENT 'Mensaje de respuesta o error truncado',
    duracion_ms INT UNSIGNED NULL,

    intentado_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_notiflog_tenant_notif (tenant_id, notificacion_id),
    KEY ix_notiflog_tenant_fecha (tenant_id, intentado_at),
    KEY ix_notiflog_resultado (tenant_id, resultado, intentado_at),
    CONSTRAINT fk_notiflog_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_notiflog_notif FOREIGN KEY (notificacion_id) REFERENCES notificaciones(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- @down

DROP TABLE IF EXISTS notificaciones_log;
DROP TABLE IF EXISTS notificaciones;
