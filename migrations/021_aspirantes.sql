-- =====================================================================
-- DigiSecure - Migracion 021: Aspirantes / postulantes a guardia
-- =====================================================================
-- Registro publico de postulantes desde la pantalla de login ("Registro").
-- Es la puerta de entrada del embudo de reclutamiento de GEMESEG:
--
--   1. La persona completa el modal de registro en /login.
--   2. Se crea un aspirante en estado 'nuevo' (sin tenant todavia).
--   3. Reclutamiento revisa, contacta y, si avanza, lo asocia a un tenant
--      y eventualmente se le crea su Ficha de Guardia.
--
-- Notas de diseno:
--   - tenant_id es NULL al registrarse (igual que registros_empresa): el
--     aspirante no pertenece a ninguna empresa hasta que es contratado.
--     FK ON DELETE SET NULL para no perder el historial del postulante.
--   - documento y correo_normalizado son UNIQUE: evitan postulaciones
--     duplicadas y sirven de blind-lookup para validar unicidad.
--   - estatura se persiste en centimetros (SMALLINT) para evitar floats.
--   - ip se guarda como VARBINARY(16) (inet_pton) para rate-limit anti-spam.
--   - LOPDP: solo se capturan datos minimos necesarios para el contacto de
--     reclutamiento; acepta_politicas + politicas_version dejan constancia
--     del consentimiento informado.
-- =====================================================================

-- @up

CREATE TABLE aspirantes (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NULL,

    -- Identificacion
    tipo_documento ENUM('cedula', 'ruc', 'pasaporte') NOT NULL,
    documento VARCHAR(20) NOT NULL,

    -- Datos personales
    apellidos VARCHAR(120) NOT NULL,
    nombres VARCHAR(120) NOT NULL,
    celular VARCHAR(20) NOT NULL,
    correo VARCHAR(190) NOT NULL,
    correo_normalizado VARCHAR(190) NOT NULL,
    estatura_cm SMALLINT UNSIGNED NOT NULL,
    fecha_nacimiento DATE NOT NULL,

    -- Pipeline de reclutamiento
    estado ENUM('nuevo', 'en_revision', 'contactado', 'descartado', 'contratado') NOT NULL DEFAULT 'nuevo',
    origen VARCHAR(40) NOT NULL DEFAULT 'login_publico',

    -- Consentimiento y trazabilidad
    acepta_politicas TINYINT(1) NOT NULL DEFAULT 0,
    politicas_version VARCHAR(40) NULL,
    ip VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,

    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY ux_aspirantes_documento (documento),
    UNIQUE KEY ux_aspirantes_correo (correo_normalizado),
    KEY ix_aspirantes_estado (estado, created_at),
    KEY ix_aspirantes_ip_fecha (ip, created_at),
    KEY ix_aspirantes_tenant (tenant_id),
    CONSTRAINT fk_aspirantes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- @down

DROP TABLE IF EXISTS aspirantes;
