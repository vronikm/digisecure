-- =====================================================================
-- DigiSecure - Migración 002: Fichas del Guardia
-- =====================================================================
-- Agregado raíz: Ficha del Guardia (47 campos en 11 secciones)
--
-- Tablas creadas:
--   - fichas                        (agregado raíz, tenant-aware)
--   - fichas_trabajos_anteriores    (1:N, tenant-aware)
--   - fichas_hijos_menores          (1:N, tenant-aware)
--   - fichas_referencias            (1:N padre/madre/hermano, tenant-aware)
--   - catalogo_cursos               (global, catálogo de cursos de seguridad)
--   - fichas_cursos                 (N:M ficha ↔ curso, tenant-aware)
--
-- Convenciones:
--   - PK CHAR(26) ULID para entidades de negocio
--   - Datos sensibles cifrados: cedula_cifrada, cuenta_bancaria_cifrada
--   - cedula_hash SHA-256 para búsquedas sin descifrar
--   - Índices compuestos con tenant_id al inicio (siempre)
--   - Soft delete vía estado 'anulada' (no deleted_at en esta tabla)
-- =====================================================================

-- @up

-- -----------------------------------------------------------------
-- FICHA DEL GUARDIA (agregado raíz)
-- -----------------------------------------------------------------
CREATE TABLE fichas (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,

    -- Cabecera
    correlativo VARCHAR(30) NOT NULL,
    tipo_ficha ENUM('INGRESO','REINGRESO','ACTUALIZACION') NOT NULL DEFAULT 'INGRESO',
    estado ENUM('BORRADOR','EN_REVISION','COMPLETA','ANULADA') NOT NULL DEFAULT 'BORRADOR',
    fecha_emision DATE NOT NULL,
    fecha_revision DATE NULL,

    -- Datos personales
    nombres VARCHAR(120) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    cedula_hash CHAR(64) NOT NULL,
    cedula_cifrada VARBINARY(1024) NOT NULL,
    genero ENUM('MASCULINO','FEMENINO','OTRO','PREFIERO_NO_DECIR') NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    lugar_nacimiento_ciudad VARCHAR(100) NULL,
    lugar_nacimiento_provincia VARCHAR(100) NULL,
    tipo_sangre ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
    estatura DECIMAL(3,2) NULL COMMENT 'En metros, ej: 1.72',
    celular VARCHAR(30) NULL,
    email VARCHAR(190) NULL,

    -- Financiero (cifrado)
    banco VARCHAR(120) NULL,
    tipo_cuenta ENUM('AHORROS','CORRIENTE') NULL,
    cuenta_bancaria_cifrada VARBINARY(1024) NULL,

    -- Dirección domiciliaria
    domicilio_ciudad VARCHAR(100) NULL,
    domicilio_tipo_vivienda ENUM('PROPIA','ARRENDADA','FAMILIAR') NULL,
    domicilio_calle VARCHAR(255) NULL,
    domicilio_numero VARCHAR(30) NULL,
    domicilio_barrio VARCHAR(150) NULL,
    domicilio_referencia VARCHAR(255) NULL,
    domicilio_telefono VARCHAR(30) NULL,
    contacto_emergencia_nombre VARCHAR(200) NULL,
    contacto_emergencia_telefono VARCHAR(30) NULL,

    -- Estudios
    instruccion_primaria VARCHAR(200) NULL,
    instruccion_secundaria VARCHAR(200) NULL,
    instruccion_superior VARCHAR(200) NULL,
    fecha_ultimo_reentrenamiento DATE NULL,

    -- Estado civil
    estado_civil ENUM('SOLTERO','CASADO','UNION_LIBRE','DIVORCIADO','VIUDO') NULL,

    -- Croquis (referencia a archivo en storage cifrado)
    croquis_archivo_id CHAR(26) NULL,
    croquis_latitud DECIMAL(10,7) NULL,
    croquis_longitud DECIMAL(10,7) NULL,

    -- Foto del guardia (referencia a archivo en storage cifrado)
    foto_archivo_id CHAR(26) NULL,

    -- Relación con ficha anterior (para REINGRESO)
    ficha_anterior_id CHAR(26) NULL,

    -- Auditoría
    created_by CHAR(26) NULL,
    updated_by CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_fichas_tenant_correlativo (tenant_id, correlativo),
    KEY ix_fichas_tenant_estado (tenant_id, estado),
    KEY ix_fichas_tenant_cedula (tenant_id, cedula_hash),
    KEY ix_fichas_tenant_nombres (tenant_id, apellidos, nombres),
    KEY ix_fichas_tenant_tipo (tenant_id, tipo_ficha),
    KEY ix_fichas_tenant_created (tenant_id, created_at),
    KEY ix_fichas_ficha_anterior (ficha_anterior_id),
    CONSTRAINT fk_fichas_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_fichas_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT fk_fichas_editor FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT fk_fichas_anterior FOREIGN KEY (ficha_anterior_id) REFERENCES fichas(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- TRABAJOS ANTERIORES (1:N por ficha)
-- -----------------------------------------------------------------
CREATE TABLE fichas_trabajos_anteriores (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    ficha_id CHAR(26) NOT NULL,
    empresa VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) NULL,
    telefonos VARCHAR(100) NULL,
    tiempo VARCHAR(100) NULL COMMENT 'Ej: 2 años 3 meses',
    cargo VARCHAR(150) NULL,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_fta_tenant_ficha (tenant_id, ficha_id),
    CONSTRAINT fk_fta_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_fta_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- HIJOS MENORES DE 18 AÑOS (1:N por ficha)
-- -----------------------------------------------------------------
CREATE TABLE fichas_hijos_menores (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    ficha_id CHAR(26) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    edad TINYINT UNSIGNED NOT NULL,
    justifica_con_cedula TINYINT(1) NOT NULL DEFAULT 0,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_fhm_tenant_ficha (tenant_id, ficha_id),
    CONSTRAINT fk_fhm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_fhm_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- REFERENCIAS PERSONALES (padre, madre, hermano/a — 1:N por ficha)
-- -----------------------------------------------------------------
CREATE TABLE fichas_referencias (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    ficha_id CHAR(26) NOT NULL,
    parentesco ENUM('PADRE','MADRE','HERMANO','OTRO') NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(30) NULL,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_fref_tenant_ficha (tenant_id, ficha_id),
    CONSTRAINT fk_fref_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_fref_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- CATÁLOGO DE CURSOS DE SEGURIDAD (global, sin tenant_id)
-- -----------------------------------------------------------------
CREATE TABLE catalogo_cursos (
    id CHAR(26) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion VARCHAR(500) NULL,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_catalogo_cursos_codigo (codigo),
    KEY ix_catalogo_cursos_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- FICHAS ↔ CURSOS (N:M, tenant-aware)
-- -----------------------------------------------------------------
CREATE TABLE fichas_cursos (
    ficha_id CHAR(26) NOT NULL,
    curso_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    fecha_obtencion DATE NULL,
    observacion VARCHAR(255) NULL,

    PRIMARY KEY (ficha_id, curso_id),
    KEY ix_fc_tenant_ficha (tenant_id, ficha_id),
    CONSTRAINT fk_fc_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_fc_curso FOREIGN KEY (curso_id) REFERENCES catalogo_cursos(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_fc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- SEMILLAS: CATÁLOGO DE CURSOS DE SEGURIDAD
-- -----------------------------------------------------------------
INSERT INTO catalogo_cursos (id, codigo, nombre, descripcion, orden, activo, created_at) VALUES
    ('SEEDCUR0000000000000000001', 'NIVEL_1',            'Nivel 1 (120 horas)',         'Curso básico de guardia de seguridad privada',       1, 1, NOW(6)),
    ('SEEDCUR0000000000000000002', 'NIVEL_2',            'Nivel 2',                     'Curso avanzado de guardia de seguridad privada',      2, 1, NOW(6)),
    ('SEEDCUR0000000000000000003', 'REENTRENAMIENTO',    'Reentrenamiento',             'Curso de actualización periódica (vigencia 2 años)',  3, 1, NOW(6)),
    ('SEEDCUR0000000000000000004', 'SUPERVISOR',         'Supervisor',                  'Curso de supervisión de seguridad',                   4, 1, NOW(6)),
    ('SEEDCUR0000000000000000005', 'CONSOLAS',           'Consolas / Monitoreo',        'Curso de operación de consolas y videovigilancia',    5, 1, NOW(6));


-- @down

DROP TABLE IF EXISTS fichas_cursos;
DROP TABLE IF EXISTS catalogo_cursos;
DROP TABLE IF EXISTS fichas_referencias;
DROP TABLE IF EXISTS fichas_hijos_menores;
DROP TABLE IF EXISTS fichas_trabajos_anteriores;
DROP TABLE IF EXISTS fichas;
