-- =====================================================================
-- DigiSecure - Migracion 019: Cobertura importacion Excel GEMESEG
-- =====================================================================
-- Objetivo:
--   Cubrir campos detectados en PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx
--   que no tenian almacenamiento estructurado claro:
--   procedencia, cargas familiares resumidas, tallas, remuneracion,
--   clase de trabajo, vacunas y cursos/capacitaciones adicionales.
--
-- Tambien agrega una tabla de staging cifrado para preservar filas
-- originales del Excel durante importaciones controladas sin dejar datos
-- personales en claro.
-- =====================================================================

-- @up

-- Normalizacion previa para ambientes legacy:
-- en algunos despliegues `tenants` quedo en MyISAM/utf8mb4_spanish2_ci
-- y las FK nuevas fallan si las columnas CHAR(26) referenciadas no comparten
-- motor/collation con las tablas nuevas.
ALTER TABLE tenants ENGINE=InnoDB;
ALTER TABLE tenants CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE expediente_documentos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE fichas
    ADD COLUMN procedencia VARCHAR(150) NULL AFTER lugar_nacimiento_provincia;

ALTER TABLE fichas
    ADD COLUMN cargas_familiares SMALLINT UNSIGNED NULL AFTER estado_civil;

ALTER TABLE fichas
    ADD COLUMN talla_uniforme VARCHAR(50) NULL AFTER foto_archivo_id;

ALTER TABLE fichas
    ADD COLUMN talla_zapatos VARCHAR(30) NULL AFTER talla_uniforme;

ALTER TABLE asignaciones
    ADD COLUMN clase_trabajo VARCHAR(120) NULL AFTER rol_en_puesto;

ALTER TABLE asignaciones
    ADD COLUMN remuneracion DECIMAL(10,2) NULL AFTER clase_trabajo;

ALTER TABLE asignaciones
    ADD COLUMN remuneracion_moneda CHAR(3) NOT NULL DEFAULT 'USD' AFTER remuneracion;

ALTER TABLE asignaciones
    ADD COLUMN contrato_referencia VARCHAR(120) NULL AFTER remuneracion_moneda;

CREATE TABLE catalogo_vacunas (
    id CHAR(26) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    descripcion VARCHAR(500) NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_catalogo_vacunas_codigo (codigo),
    KEY ix_catalogo_vacunas_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE fichas_vacunas (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    ficha_id CHAR(26) NOT NULL,
    vacuna_id CHAR(26) NOT NULL,
    fecha_aplicacion DATE NULL,
    evidencia_documento_id CHAR(26) NULL,
    observacion VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_fichas_vacunas_ficha_vacuna (tenant_id, ficha_id, vacuna_id),
    KEY ix_fichas_vacunas_tenant_ficha (tenant_id, ficha_id),
    KEY ix_fichas_vacunas_vacuna (vacuna_id),
    CONSTRAINT fk_fv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_fv_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_fv_vacuna FOREIGN KEY (vacuna_id) REFERENCES catalogo_vacunas(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_fv_documento FOREIGN KEY (evidencia_documento_id) REFERENCES expediente_documentos(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE importacion_excel_registros (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    fuente VARCHAR(120) NOT NULL,
    hoja VARCHAR(120) NOT NULL,
    fila INT UNSIGNED NOT NULL,
    cedula_hash CHAR(64) NULL,
    raw_json_cifrado LONGBLOB NOT NULL,
    raw_json_hash CHAR(64) NOT NULL,
    entidad_tipo ENUM('ficha','asignacion','expediente','curso','vacuna','salida','otro') NOT NULL DEFAULT 'otro',
    entidad_id CHAR(26) NULL,
    estado ENUM('pendiente','procesado','error','omitido') NOT NULL DEFAULT 'pendiente',
    error_mensaje VARCHAR(500) NULL,
    importado_por CHAR(26) NULL,
    importado_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_import_excel_fuente_hoja_fila (tenant_id, fuente, hoja, fila),
    KEY ix_import_excel_tenant_estado (tenant_id, estado),
    KEY ix_import_excel_cedula (tenant_id, cedula_hash),
    KEY ix_import_excel_entidad (tenant_id, entidad_tipo, entidad_id),
    CONSTRAINT fk_import_excel_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_import_excel_usuario FOREIGN KEY (importado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

INSERT IGNORE INTO catalogo_vacunas (id, codigo, nombre, descripcion, orden, activo, created_at, updated_at) VALUES
    ('SEEDVACGEMESEG000000000001', 'HB', 'Hepatitis B', 'Registro de vacuna Hepatitis B detectado en plantilla GEMESEG.', 10, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDVACGEMESEG000000000002', 'DT', 'Difteria y Tetano', 'Registro de vacuna Difteria y Tetano detectado en plantilla GEMESEG.', 20, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDVACGEMESEG000000000003', 'SR', 'Sarampion y Rubeola', 'Registro de vacuna Sarampion y Rubeola detectado en plantilla GEMESEG.', 30, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDVACGEMESEG000000000004', 'FA', 'Fiebre Amarilla', 'Registro de vacuna Fiebre Amarilla detectado en plantilla GEMESEG.', 40, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDVACGEMESEG000000000005', 'INFLUENZA', 'Influenza', 'Registro de vacuna Influenza detectado en plantilla GEMESEG.', 50, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDVACGEMESEG000000000006', 'COVID', 'COVID', 'Registro de vacuna COVID detectado en plantilla GEMESEG.', 60, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));

INSERT IGNORE INTO catalogo_cursos (id, codigo, nombre, descripcion, orden, activo, created_at) VALUES
    ('SEEDCURGEMESEG000000000001', 'ATENCION_CLIENTE', 'Atencion y Servicio al Cliente', 'Capacitacion detectada en plantilla GEMESEG.', 21, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000002', 'COMPUTACION_BASICA', 'Computacion Basica', 'Capacitacion detectada en plantilla GEMESEG.', 22, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000003', 'EQUIPOS_PROTECCION', 'Conocimientos de equipos de proteccion', 'Capacitacion detectada en plantilla GEMESEG.', 23, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000004', 'ARMAS_POLIGONO_VIRTUAL', 'Conocimiento y manejo de armas poligono virtual', 'Capacitacion detectada en plantilla GEMESEG.', 24, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000005', 'CONOCIMIENTOS_TECNICOS', 'Conocimientos tecnicos', 'Capacitacion detectada en plantilla GEMESEG.', 25, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000006', 'DEFENSA_PERSONAL', 'Defensa personal', 'Capacitacion detectada en plantilla GEMESEG.', 26, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000007', 'DEFENSA_AUX_ARMAS', 'Defensa personal, primeros auxilios y manejo de armas', 'Capacitacion detectada en plantilla GEMESEG.', 27, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000008', 'DESARROLLO_HUMANO', 'Desarrollo humano', 'Capacitacion detectada en plantilla GEMESEG.', 28, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000009', 'EMPLEO_TACTICO_ARMAS', 'Empleo tactico de armas', 'Capacitacion detectada en plantilla GEMESEG.', 29, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000010', 'BARES_RESTAURANTE', 'Especializacion de bares y restaurante', 'Capacitacion detectada en plantilla GEMESEG.', 30, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000011', 'ETICA_RELACIONES_HUMANAS', 'Etica y relaciones humanas', 'Capacitacion detectada en plantilla GEMESEG.', 31, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000012', 'LEGISLACION', 'Legislacion', 'Capacitacion detectada en plantilla GEMESEG.', 32, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000013', 'CRISIS_EMERGENCIAS', 'Manejo crisis y control emergencias', 'Capacitacion detectada en plantilla GEMESEG.', 33, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000014', 'MANEJO_RIESGOS', 'Manejo de Riesgos', 'Capacitacion detectada en plantilla GEMESEG.', 34, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000015', 'NORMATIVA_LEGAL', 'Normativa legal', 'Capacitacion detectada en plantilla GEMESEG.', 35, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000016', 'NORMATIVA_SEG_TRANSITO', 'Normativa vigente en seguridad y transito', 'Capacitacion detectada en plantilla GEMESEG.', 36, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000017', 'OPERACIONES_SEGURIDAD', 'Operaciones de seguridad', 'Capacitacion detectada en plantilla GEMESEG.', 37, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000018', 'CCTV_CONSOLA', 'Operador de consola CCTV', 'Capacitacion detectada en plantilla GEMESEG.', 38, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000019', 'PREVENCION_RIESGOS', 'Prevencion de riesgos', 'Capacitacion detectada en plantilla GEMESEG.', 39, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000020', 'PRIMEROS_AUXILIOS', 'Primeros auxilios', 'Capacitacion detectada en plantilla GEMESEG.', 40, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000021', 'PROCEDIMIENTOS_SEGURIDAD', 'Procedimientos de Seguridad', 'Capacitacion detectada en plantilla GEMESEG.', 41, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000022', 'PROTECCION_EJECUTIVOS_CARGA', 'Proteccion a ejecutivos, carga critica, especies monetarias', 'Capacitacion detectada en plantilla GEMESEG.', 42, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000023', 'RELACIONES_HUMANAS', 'Relaciones Humanas', 'Capacitacion detectada en plantilla GEMESEG.', 43, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000024', 'SEG_COMUNICACION_INFO', 'Seguridad de la comunicacion e informacion', 'Capacitacion detectada en plantilla GEMESEG.', 44, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000025', 'SEGURIDAD_CIUDADANA', 'Seguridad ciudadana', 'Capacitacion detectada en plantilla GEMESEG.', 45, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000026', 'SEGURIDAD_ELECTRONICA', 'Seguridad electronica', 'Capacitacion detectada en plantilla GEMESEG.', 46, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000027', 'SEGURIDAD_FISICA', 'Seguridad fisica', 'Capacitacion detectada en plantilla GEMESEG.', 47, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000028', 'SEG_PRIVADA_CIUDADANA', 'Seguridad privada aplicada a la seguridad ciudadana', 'Capacitacion detectada en plantilla GEMESEG.', 48, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000029', 'SISTEMAS_COM_MONITOREO', 'Sistemas de comunicacion y monitoreo', 'Capacitacion detectada en plantilla GEMESEG.', 49, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000030', 'TECNICAS_MONITOREO', 'Tecnicas de monitoreo', 'Capacitacion detectada en plantilla GEMESEG.', 50, 1, UTC_TIMESTAMP(6)),
    ('SEEDCURGEMESEG000000000031', 'TIRO_VIRTUAL', 'Tiro Virtual', 'Capacitacion detectada en plantilla GEMESEG.', 51, 1, UTC_TIMESTAMP(6));

INSERT IGNORE INTO documento_tipos (id, tenant_id, codigo, nombre, descripcion, bloque, orden, obligatorio, requiere_validacion_externa, tiene_vencimiento, dias_vigencia, formatos_permitidos, tamano_maximo_mb, activo, created_at, updated_at) VALUES
    ('SEEDDOCGEMESEG000000000001', NULL, 'HOJA_INGRESO', 'Hoja de ingreso', 'Documento detectado en plantilla GEMESEG.', 'A', 31, 0, 0, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDDOCGEMESEG000000000002', NULL, 'VISITA_DOMICILIARIA', 'Visita domiciliaria', 'Documento o evidencia detectada en plantilla GEMESEG.', 'A', 32, 0, 0, 0, NULL, 'pdf,jpg,jpeg,png', 10.00, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDDOCGEMESEG000000000003', NULL, 'SICOSEP', 'Registro SICOSEP', 'Documento detectado en plantilla GEMESEG.', 'B', 5, 0, 1, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
    ('SEEDDOCGEMESEG000000000004', NULL, 'CERT_FUERZAS_ARMADAS', 'Certificado Fuerzas Armadas', 'Documento detectado en plantilla GEMESEG.', 'A', 33, 0, 0, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));

-- @down

DELETE FROM documento_tipos WHERE id LIKE 'SEEDDOCGEMESEG%';
DELETE FROM catalogo_cursos WHERE id LIKE 'SEEDCURGEMESEG%';
DELETE FROM fichas_vacunas WHERE vacuna_id IN (SELECT id FROM catalogo_vacunas WHERE id LIKE 'SEEDVACGEMESEG%');
DELETE FROM catalogo_vacunas WHERE id LIKE 'SEEDVACGEMESEG%';

DROP TABLE IF EXISTS importacion_excel_registros;
DROP TABLE IF EXISTS fichas_vacunas;
DROP TABLE IF EXISTS catalogo_vacunas;

ALTER TABLE asignaciones
    DROP COLUMN contrato_referencia;

ALTER TABLE asignaciones
    DROP COLUMN remuneracion_moneda;

ALTER TABLE asignaciones
    DROP COLUMN remuneracion;

ALTER TABLE asignaciones
    DROP COLUMN clase_trabajo;

ALTER TABLE fichas
    DROP COLUMN talla_zapatos;

ALTER TABLE fichas
    DROP COLUMN talla_uniforme;

ALTER TABLE fichas
    DROP COLUMN cargas_familiares;

ALTER TABLE fichas
    DROP COLUMN procedencia;
