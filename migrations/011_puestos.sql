-- =====================================================================
-- DigiSecure - Migración 011: Modulo Puestos / Sitios (Fase 17)
-- =====================================================================
-- Ubicaciones fisicas asociadas a un cliente donde se prestan servicios
-- de seguridad. Es el bloque base para asignar guardias y construir turnos.
--
-- Modelo:
--   - Cada puesto pertenece a un cliente (FK clientes.id).
--   - Tipo: oficina, planta, residencial, comercial, evento, otro.
--   - Modalidad: fijo (24/7), rotativo (turnos), eventual (puntual).
--   - Coordenadas opcionales (lat/lng) para futuras integraciones de mapas.
--   - num_guardias_requeridos: cuantas plazas hay que cubrir.
--   - Estado: activo / inactivo / archivado (conservar historico).
-- =====================================================================

-- @up

CREATE TABLE puestos (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    cliente_id CHAR(26) NOT NULL,

    -- Identidad
    codigo VARCHAR(50) NULL,
    nombre VARCHAR(200) NOT NULL,
    tipo ENUM('oficina', 'planta', 'residencial', 'comercial', 'evento', 'otro') NOT NULL DEFAULT 'oficina',
    modalidad ENUM('fijo', 'rotativo', 'eventual') NOT NULL DEFAULT 'fijo',

    -- Ubicacion
    direccion VARCHAR(255) NULL,
    ciudad VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    pais CHAR(2) NOT NULL DEFAULT 'EC',
    latitud DECIMAL(10, 7) NULL,
    longitud DECIMAL(11, 7) NULL,

    -- Operacion
    horario_descripcion VARCHAR(255) NULL,
    num_guardias_requeridos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    contacto_sitio VARCHAR(200) NULL,
    telefono_sitio VARCHAR(30) NULL,
    observaciones TEXT NULL,

    -- Ciclo de vida
    estado ENUM('activo', 'inactivo', 'archivado') NOT NULL DEFAULT 'activo',
    archivado_at DATETIME(6) NULL,
    archivado_por CHAR(26) NULL,

    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_puestos_tenant (tenant_id),
    KEY ix_puestos_tenant_estado (tenant_id, estado),
    KEY ix_puestos_tenant_cliente (tenant_id, cliente_id),
    KEY ix_puestos_tenant_nombre (tenant_id, nombre),
    UNIQUE KEY uq_puestos_tenant_codigo (tenant_id, codigo),
    CONSTRAINT fk_puestos_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_puestos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- Permisos del modulo
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JCSA0PERM0PUESTOS_VR001', 'puestos.ver',      'puestos', 'ver',      'Ver el listado y detalle de puestos',  1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0PUESTOS_CR001', 'puestos.crear',    'puestos', 'crear',    'Registrar nuevos puestos',             1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0PUESTOS_ED001', 'puestos.editar',   'puestos', 'editar',   'Modificar datos de puestos',           1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0PUESTOS_AR001', 'puestos.archivar', 'puestos', 'archivar', 'Archivar/reactivar puestos',           1, UTC_TIMESTAMP(6));

-- admin_tenant: control total
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0PUESTOS_VR001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0PUESTOS_CR001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0PUESTOS_ED001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0PUESTOS_AR001', UTC_TIMESTAMP(6));

-- supervisor: ver, crear, editar (no archivar)
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0PUESTOS_VR001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0PUESTOS_CR001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0PUESTOS_ED001', UTC_TIMESTAMP(6));

-- operador y auditor: solo ver
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000004', '01JCSA0PERM0PUESTOS_VR001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000005', '01JCSA0PERM0PUESTOS_VR001', UTC_TIMESTAMP(6));

-- @down

DELETE FROM roles_permisos WHERE permiso_id LIKE '01JCSA0PERM0PUESTOS_%';
DELETE FROM permisos       WHERE id          LIKE '01JCSA0PERM0PUESTOS_%';
DROP TABLE IF EXISTS puestos;
