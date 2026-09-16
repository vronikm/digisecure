-- =====================================================================
-- DigiSecure - Migracion 026: Catalogo de instituciones financieras
-- =====================================================================
-- Catalogo global administrable (CRUD) de bancos y cooperativas, usado
-- en la ficha del guardia (campo Banco). El orden estandar lista primero
-- los bancos y luego las cooperativas.
--
-- Es un catalogo global (sin tenant_id), igual que catalogo_cursos: son
-- entidades financieras nacionales compartidas por todos los tenants.
-- Se administra con el permiso configuracion.instituciones.
-- =====================================================================

-- @up

CREATE TABLE IF NOT EXISTS instituciones_financieras (
    id CHAR(26) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('BANCO','COOPERATIVA') NOT NULL DEFAULT 'BANCO',
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_inst_fin_nombre (nombre),
    KEY ix_inst_fin_orden (tipo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Semilla: primero bancos, luego cooperativas.
INSERT IGNORE INTO instituciones_financieras (id, nombre, tipo, orden, activo, created_at) VALUES
    ('01KINSTITUCIONFINANCIERA01', 'Banco de Loja',        'BANCO',       1, 1, NOW(6)),
    ('01KINSTITUCIONFINANCIERA02', 'Banco de Guayaquil',   'BANCO',       2, 1, NOW(6)),
    ('01KINSTITUCIONFINANCIERA03', 'Banco Pichincha',      'BANCO',       3, 1, NOW(6)),
    ('01KINSTITUCIONFINANCIERA04', 'Cooperativa CoopMego', 'COOPERATIVA', 1, 1, NOW(6)),
    ('01KINSTITUCIONFINANCIERA05', 'Cooperativa JEP',      'COOPERATIVA', 2, 1, NOW(6));

-- Permiso de administracion del catalogo.
INSERT IGNORE INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01KINSTFINPERM000000000001', 'configuracion.instituciones', 'configuracion', 'instituciones', 'Gestionar catalogo de instituciones financieras', 1, NOW(6));

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, '01KINSTFINPERM000000000001', NOW(6)
FROM roles r
LEFT JOIN roles_permisos rp
       ON rp.role_id = r.id
      AND rp.permiso_id = '01KINSTFINPERM000000000001'
WHERE r.codigo IN ('admin_tenant', 'superadmin')
  AND rp.role_id IS NULL;

-- @down

DELETE FROM roles_permisos WHERE permiso_id = '01KINSTFINPERM000000000001';
DELETE FROM permisos WHERE id = '01KINSTFINPERM000000000001';
DROP TABLE IF EXISTS instituciones_financieras;
