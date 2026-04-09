-- =====================================================================
-- DigiSecure - Migración 003: Expediente Documental del Guardia
-- =====================================================================
-- Segundo agregado: gestión de los 30 tipos de documento (26 Bloque A
-- pre-empleo + 4 Bloque B post-vinculación), con vigencias, validación
-- externa, screenshots y catálogo configurable por tenant.
--
-- Tablas creadas:
--   - documento_tipos        (catálogo configurable por tenant)
--   - expedientes            (agregado raíz, 1:1 con fichas)
--   - expediente_documentos  (1:N por expediente, archivo + revisión)
--
-- Convenciones:
--   - PK CHAR(26) ULID
--   - Archivos referenciados por nombre aleatorio en storage cifrado
--   - tenant_id en todas las tablas tenant-aware
--   - Bloque B se desbloquea solo cuando Bloque A está 100% ACEPTADO
-- =====================================================================

-- @up

-- -----------------------------------------------------------------
-- CATÁLOGO DE TIPOS DE DOCUMENTO (configurable por tenant)
-- -----------------------------------------------------------------
-- Cada tenant hereda los 30 tipos semilla y puede desactivar o agregar.
-- Global seed + tenant override: si tenant_id IS NULL → es la plantilla
-- global (semilla). Si tenant_id NOT NULL → override del tenant.
-- -----------------------------------------------------------------
CREATE TABLE documento_tipos (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NULL COMMENT 'NULL = plantilla global, NOT NULL = override tenant',

    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion VARCHAR(500) NULL,
    bloque ENUM('A','B') NOT NULL DEFAULT 'A' COMMENT 'A=pre-empleo, B=post-vinculación',
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- Flags de comportamiento
    obligatorio TINYINT(1) NOT NULL DEFAULT 1,
    requiere_validacion_externa TINYINT(1) NOT NULL DEFAULT 0,
    tiene_vencimiento TINYINT(1) NOT NULL DEFAULT 0,
    dias_vigencia SMALLINT UNSIGNED NULL COMMENT 'NULL=sin vencimiento, >0 días de vigencia desde fecha_emision_documento',

    -- Restricciones de archivo
    formatos_permitidos VARCHAR(100) NOT NULL DEFAULT 'pdf,jpg,jpeg,png,webp',
    tamano_maximo_mb DECIMAL(5,2) NOT NULL DEFAULT 10.00,

    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_doctipo_tenant_codigo (tenant_id, codigo),
    KEY ix_doctipo_bloque (tenant_id, bloque, orden),
    CONSTRAINT fk_doctipo_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- EXPEDIENTE (agregado raíz, 1:1 con ficha)
-- -----------------------------------------------------------------
CREATE TABLE expedientes (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    ficha_id CHAR(26) NOT NULL,

    -- Estado general calculado
    estado ENUM('INCOMPLETO','BLOQUE_A_COMPLETO','COMPLETO') NOT NULL DEFAULT 'INCOMPLETO',

    -- Progreso Bloque A
    bloque_a_total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloque_a_aceptados SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- Progreso Bloque B (desbloqueado cuando bloque_a_aceptados = bloque_a_total)
    bloque_b_total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloque_b_aceptados SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloque_b_desbloqueado TINYINT(1) NOT NULL DEFAULT 0,

    -- Auditoría
    created_by CHAR(26) NULL,
    updated_by CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_expediente_tenant_ficha (tenant_id, ficha_id),
    KEY ix_expediente_tenant_estado (tenant_id, estado),
    CONSTRAINT fk_expediente_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_expediente_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_expediente_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT fk_expediente_editor FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- DOCUMENTOS DEL EXPEDIENTE (1:N por expediente)
-- -----------------------------------------------------------------
CREATE TABLE expediente_documentos (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    expediente_id CHAR(26) NOT NULL,
    documento_tipo_id CHAR(26) NOT NULL,

    -- Archivo principal
    archivo_nombre_original VARCHAR(255) NULL,
    archivo_nombre_storage VARCHAR(100) NULL COMMENT 'Nombre aleatorio en disco cifrado',
    archivo_mime VARCHAR(100) NULL,
    archivo_tamano_bytes INT UNSIGNED NULL,

    -- Estado y revisión
    estado ENUM('PENDIENTE','EN_REVISION','ACEPTADO','RECHAZADO','VENCIDO') NOT NULL DEFAULT 'PENDIENTE',
    observacion_revisor TEXT NULL,
    revisado_por CHAR(26) NULL,
    revisado_at DATETIME(6) NULL,

    -- Validación externa (screenshots + URL)
    validacion_url VARCHAR(500) NULL COMMENT 'URL del sitio donde se verificó',
    screenshot_1_storage VARCHAR(100) NULL COMMENT 'Screenshot verificación 1',
    screenshot_2_storage VARCHAR(100) NULL COMMENT 'Screenshot verificación 2',

    -- Vigencia
    fecha_emision_documento DATE NULL COMMENT 'Fecha del documento/certificado',
    fecha_vencimiento DATE NULL COMMENT 'Calculada o manual',
    fecha_objetivo_actualizacion DATE NULL COMMENT 'Fecha para recordatorio de renovación',

    -- Auditoría de subida
    subido_por CHAR(26) NULL,
    subido_at DATETIME(6) NULL,

    -- Timestamps
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_expdoc_tenant_expediente (tenant_id, expediente_id),
    KEY ix_expdoc_tenant_tipo (tenant_id, documento_tipo_id),
    KEY ix_expdoc_tenant_estado (tenant_id, estado),
    KEY ix_expdoc_vencimiento (tenant_id, fecha_vencimiento),
    KEY ix_expdoc_objetivo (tenant_id, fecha_objetivo_actualizacion),
    CONSTRAINT fk_expdoc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_expdoc_expediente FOREIGN KEY (expediente_id) REFERENCES expedientes(id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_expdoc_tipo FOREIGN KEY (documento_tipo_id) REFERENCES documento_tipos(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_expdoc_revisor FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT fk_expdoc_subidor FOREIGN KEY (subido_por) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;


-- -----------------------------------------------------------------
-- SEMILLAS: CATÁLOGO DE TIPOS DE DOCUMENTO (plantilla global)
-- -----------------------------------------------------------------
-- tenant_id = NULL → plantilla que cada tenant hereda al crearse.
-- Los 26 documentos Bloque A + 4 Bloque B.
-- -----------------------------------------------------------------

INSERT INTO documento_tipos (id, tenant_id, codigo, nombre, descripcion, bloque, orden, obligatorio, requiere_validacion_externa, tiene_vencimiento, dias_vigencia, formatos_permitidos, tamano_maximo_mb, activo, created_at, updated_at) VALUES
-- ===== BLOQUE A: PRE-EMPLEO (26 documentos) =====
('SEEDDOC0000000000000000001', NULL, 'LOPDP_CONSENT',        'Autorización tratamiento datos personales (LOPDP)',  'Consentimiento informado — crítico legal. Sin vencimiento (vinculado a versión del texto).', 'A',  1, 1, 0, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000002', NULL, 'HOJA_VIDA',            'Hoja de vida (encuentraempleo.gob.ec)',              'Hoja de vida descargada del portal público de empleo.',                                      'A',  2, 1, 1, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000003', NULL, 'CEDULA_COLOR',         'Cédula de identidad vigente a color',                'Copia a color de cédula. Vencimiento = fecha caducidad cédula.',                             'A',  3, 1, 0, 1, 1460, 'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000004', NULL, 'PAPELETA_VOTACION',    'Papeleta de votación a color',                       'Vence en cada proceso electoral.',                                                           'A',  4, 1, 0, 1, 730,  'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000005', NULL, 'FOTOS_CARNET',         '2 fotografías tamaño carnet',                        'Fotos recientes para credencial.',                                                           'A',  5, 1, 0, 0, NULL, 'jpg,jpeg,png,webp', 10.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000006', NULL, 'PLANILLA_BASICO',      'Planilla de servicio básico',                        'Recomendable < 3 meses de antigüedad.',                                                      'A',  6, 1, 0, 1, 90,   'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000007', NULL, 'TITULO_BACHILLER',     'Título de bachiller',                                'Requisito mínimo de instrucción. Sin vencimiento.',                                          'A',  7, 1, 0, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000008', NULL, 'CERT_NIVEL1',          'Certificado Nivel 1 (120 horas)',                    'Curso básico de guardia de seguridad.',                                                      'A',  8, 1, 0, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000009', NULL, 'CRED_NIVEL1',          'Credencial Nivel 1',                                 'Credencial emitida tras aprobar Nivel 1.',                                                   'A',  9, 1, 0, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000010', NULL, 'CERT_NIVEL2',          'Certificado Nivel 2',                                'Curso avanzado de guardia.',                                                                  'A', 10, 1, 0, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000011', NULL, 'CRED_NIVEL2',          'Credencial Nivel 2',                                 'Credencial emitida tras aprobar Nivel 2.',                                                   'A', 11, 1, 0, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000012', NULL, 'CERT_REENTRENAMIENTO', 'Certificado reentrenamiento',                        'Vigencia 2 años desde emisión.',                                                             'A', 12, 1, 0, 1, 730,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000013', NULL, 'DIPLOMA_ARMAS',        'Diploma manejo de armas de fuego',                   'Vigencia variable según regulación.',                                                        'A', 13, 1, 0, 1, 730,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000014', NULL, 'CERT_AUXILIOS_INCENDIOS','Certificados primeros auxilios y contra incendios','RIG-SICISEP. Vigencia variable.',                                                            'A', 14, 1, 0, 1, 365,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000015', NULL, 'EXAMEN_SALUD',         'Exámenes salud física',                              'Vigencia aprox. 1 año.',                                                                     'A', 15, 1, 0, 1, 365,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000016', NULL, 'EXAMEN_DROGAS',        'Exámenes drogas y alcohol',                          'Vigencia aprox. 1 año.',                                                                     'A', 16, 1, 0, 1, 365,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000017', NULL, 'CERT_PSICOLOGICO',     'Certificado Psicológico (MSP actual)',               'Vigencia aprox. 1 año.',                                                                     'A', 17, 1, 0, 1, 365,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000018', NULL, 'TIPIFICACION_SANGRE',  'Tipificación sanguínea',                             'Sin vencimiento.',                                                                           'A', 18, 1, 0, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000019', NULL, 'CARNET_VACUNACION',    'Carnet vacunación MSP',                              'Registro de vacunas del Ministerio de Salud.',                                               'A', 19, 1, 0, 0, NULL, 'pdf,jpg,jpeg,png', 5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000020', NULL, 'CERT_AFIS',            'Certificado AFIS (Policía Judicial)',                'Vigencia variable.',                                                                          'A', 20, 1, 1, 1, 180,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000021', NULL, 'ANTECEDENTES_PENALES', 'Antecedentes penales (record policial)',             'Vigencia aprox. 3 meses.',                                                                   'A', 21, 1, 1, 1, 90,   'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000022', NULL, 'CERT_NO_VIOLENCIA',    'Certificado no violencia intrafamiliar',             'Vigencia variable.',                                                                          'A', 22, 1, 1, 1, 180,  'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000023', NULL, 'HISTORIAL_IESS',       'Historial laboral IESS',                             'Sin vencimiento estricto, actualizable.',                                                     'A', 23, 1, 1, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000024', NULL, 'CERT_TRABAJO',         '2 certificados de trabajo',                          'Experiencia laboral previa.',                                                                 'A', 24, 1, 0, 0, NULL, 'pdf',          10.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000025', NULL, 'CERT_HONORABILIDAD',   '1 certificado de honorabilidad y conducta',          'Referencia personal.',                                                                        'A', 25, 1, 0, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000026', NULL, 'FOTOS_DOMICILIO',      '3 fotografías del domicilio',                        'Frente casa, calle y vista alejada.',                                                        'A', 26, 1, 0, 0, NULL, 'jpg,jpeg,png,webp', 15.00, 1, NOW(6), NOW(6)),

-- ===== BLOQUE B: POST-VINCULACIÓN (4 documentos) =====
('SEEDDOC0000000000000000027', NULL, 'CONTRATO_FIRMADO',     'Contrato firmado y digitalizado',                    'Vigente mientras dure la relación laboral.',                                                  'B',  1, 1, 0, 0, NULL, 'pdf',          10.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000028', NULL, 'AVISO_ENTRADA_IESS',   'Aviso de entrada al IESS',                           'Comprobante puntual de registro.',                                                           'B',  2, 1, 0, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000029', NULL, 'REGISTRO_SUT',         'Registro en SUT (Ministerio de Trabajo)',            'Comprobante puntual de registro.',                                                           'B',  3, 1, 0, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6)),
('SEEDDOC0000000000000000030', NULL, 'ACTA_UNIFORMES',       'Acta entrega-recepción de uniformes',                'Puede haber múltiples actas a lo largo del tiempo.',                                         'B',  4, 1, 0, 0, NULL, 'pdf',          5.00, 1, NOW(6), NOW(6));


-- @down

DROP TABLE IF EXISTS expediente_documentos;
DROP TABLE IF EXISTS expedientes;
DROP TABLE IF EXISTS documento_tipos;
