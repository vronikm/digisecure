-- =====================================================================
-- DigiSecure - Migración 010: Modulo Clientes (Fase 16)
-- =====================================================================
-- Introduce la tabla `clientes`: empresas o personas a las que el tenant
-- presta servicios de seguridad. Es el cimiento operativo sobre el que
-- se monta puestos / turnos / asignaciones en fases siguientes.
--
-- Propiedades clave:
--   - Multi-tenant aislado (todas las queries filtran por tenant_id)
--   - Identificacion por RUC (sociedad) o cedula (persona natural)
--   - Estado (activo / inactivo / archivado) para conservar historico
--   - Audit trail via auditoria.eventos (cliente.creado / actualizado / archivado)
-- =====================================================================

-- @up

CREATE TABLE clientes (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,

    -- Identidad
    razon_social VARCHAR(255) NOT NULL,
    nombre_comercial VARCHAR(255) NULL,
    tipo_documento ENUM('RUC', 'CEDULA') NOT NULL DEFAULT 'RUC',
    documento VARCHAR(13) NOT NULL,

    -- Contacto
    contacto_nombre VARCHAR(200) NULL,
    contacto_cargo VARCHAR(120) NULL,
    contacto_email VARCHAR(190) NULL,
    contacto_telefono VARCHAR(30) NULL,

    -- Domicilio
    direccion VARCHAR(255) NULL,
    ciudad VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    pais CHAR(2) NOT NULL DEFAULT 'EC',

    -- Operacion
    sector VARCHAR(80) NULL,
    notas TEXT NULL,

    -- Ciclo de vida
    estado ENUM('activo', 'inactivo', 'archivado') NOT NULL DEFAULT 'activo',
    archivado_at DATETIME(6) NULL,
    archivado_por CHAR(26) NULL,

    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_clientes_tenant (tenant_id),
    KEY ix_clientes_tenant_estado (tenant_id, estado),
    UNIQUE KEY uq_clientes_tenant_documento (tenant_id, documento),
    KEY ix_clientes_tenant_razon (tenant_id, razon_social),
    CONSTRAINT fk_clientes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- Permisos del modulo
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JCSA0PERM0CLIENTES_VR01', 'clientes.ver',      'clientes', 'ver',      'Ver el listado y detalle de clientes', 1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0CLIENTES_CR01', 'clientes.crear',    'clientes', 'crear',    'Registrar nuevos clientes',            1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0CLIENTES_ED01', 'clientes.editar',   'clientes', 'editar',   'Modificar datos de clientes',          1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0CLIENTES_AR01', 'clientes.archivar', 'clientes', 'archivar', 'Archivar/reactivar clientes',          1, UTC_TIMESTAMP(6));

-- Asignar al admin_tenant (SEEDRBC0000000000000000002): control total
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0CLIENTES_VR01', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0CLIENTES_CR01', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0CLIENTES_ED01', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0CLIENTES_AR01', UTC_TIMESTAMP(6));

-- Supervisor (SEEDRBC0000000000000000003): solo ver / crear / editar (no archivar)
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0CLIENTES_VR01', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0CLIENTES_CR01', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0CLIENTES_ED01', UTC_TIMESTAMP(6));

-- Operador (SEEDRBC0000000000000000004): solo ver
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000004', '01JCSA0PERM0CLIENTES_VR01', UTC_TIMESTAMP(6));

-- Auditor (SEEDRBC0000000000000000005): solo ver
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000005', '01JCSA0PERM0CLIENTES_VR01', UTC_TIMESTAMP(6));

-- @down

DELETE FROM roles_permisos WHERE permiso_id LIKE '01JCSA0PERM0CLIENTES_%';
DELETE FROM permisos       WHERE id          LIKE '01JCSA0PERM0CLIENTES_%';
DROP TABLE IF EXISTS clientes;
