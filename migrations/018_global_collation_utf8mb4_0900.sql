-- ============================================================================
-- DigiSecure - Migracion 018: Normalizacion global de collation
-- ============================================================================
-- Objetivo:
--   Estandarizar TODO el esquema de DigiSecure a:
--     - CHARACTER SET: utf8mb4
--     - COLLATION    : utf8mb4_unicode_ci
--
-- Contexto:
--   En algunos entornos se detecto mezcla de collations
--   (ej. utf8mb4_spanish2_ci vs utf8mb4_unicode_ci) que causaba:
--   SQLSTATE[HY000] 1267 Illegal mix of collations
--   especialmente en login y joins entre tablas nucleares.
--
-- Nota:
--   Esta migracion es idempotente en la practica para motores MySQL 8+:
--   si la tabla ya esta en la collation objetivo, no afecta datos.
-- ============================================================================

-- @up

-- Con FOREIGN_KEY_CHECKS=1 MySQL 8 rechaza (error 3780) convertir una tabla
-- cuya columna participa en una FK hacia otra tabla aun no convertida.
-- Se desactivan solo durante la conversion y se reactivan al final.
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE asignaciones CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE auditoria CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE catalogo_cursos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE clientes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE consentimientos_lopdp CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE documento_tipos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE expediente_documentos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE expedientes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas_cursos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas_hijos_menores CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas_referencias CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE fichas_trabajos_anteriores CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE intentos_login CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE lopdp_solicitudes_arcop CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE migraciones CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notificaciones CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notificaciones_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE permisos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE planes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE puestos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE registros_empresa CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE roles_permisos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE suscripcion_ciclos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE tenants CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios_roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tablas de migraciones posteriores (021+) que pueden existir ya si el
-- entorno aplico migraciones fuera de orden. Si no existen, no-op.
-- Sin esto, 027/031 fallan con 1267 al cruzar aspirantes con usuarios.
SET @ds_sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aspirantes') > 0,
    'ALTER TABLE aspirantes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'SET @ds_noop := 1'
);
PREPARE ds_stmt FROM @ds_sql;
EXECUTE ds_stmt;
DEALLOCATE PREPARE ds_stmt;

SET @ds_sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aspirante_eventos') > 0,
    'ALTER TABLE aspirante_eventos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'SET @ds_noop := 1'
);
PREPARE ds_stmt FROM @ds_sql;
EXECUTE ds_stmt;
DEALLOCATE PREPARE ds_stmt;

SET @ds_sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'instituciones_financieras') > 0,
    'ALTER TABLE instituciones_financieras CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'SET @ds_noop := 1'
);
PREPARE ds_stmt FROM @ds_sql;
EXECUTE ds_stmt;
DEALLOCATE PREPARE ds_stmt;

SET @ds_sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fichas_familiares') > 0,
    'ALTER TABLE fichas_familiares CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'SET @ds_noop := 1'
);
PREPARE ds_stmt FROM @ds_sql;
EXECUTE ds_stmt;
DEALLOCATE PREPARE ds_stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- @down
-- No reversible sin conocer la collation historica de cada entorno.
