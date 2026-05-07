-- =====================================================================
-- DigiSecure - Migración 012: Asignaciones guardia <-> puesto (Fase 18)
-- =====================================================================
-- Vincula una ficha de guardia (COMPLETA) a un puesto durante un periodo.
-- Es el puente operativo: sin asignaciones no se puede planificar turnos
-- ni saber quien cubre cada plaza.
--
-- Modelo:
--   - 1 ficha puede tener varias asignaciones a lo largo del tiempo.
--   - 1 puesto puede tener varias fichas asignadas simultaneamente
--     (hasta `num_guardias_requeridos` plazas).
--   - fecha_fin NULL = asignacion vigente (sin fecha de salida).
--   - Estados: activa / finalizada / cancelada (no se elimina, se marca).
--   - motivo_fin documenta el cierre (renuncia, cambio de puesto, etc.).
-- =====================================================================

-- @up

CREATE TABLE asignaciones (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,

    -- Relaciones
    ficha_id CHAR(26) NOT NULL,
    puesto_id CHAR(26) NOT NULL,

    -- Periodo
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,

    -- Estado y cierre
    estado ENUM('activa', 'finalizada', 'cancelada') NOT NULL DEFAULT 'activa',
    motivo_fin VARCHAR(255) NULL,
    finalizada_at DATETIME(6) NULL,
    finalizada_por CHAR(26) NULL,

    -- Otros datos
    rol_en_puesto VARCHAR(80) NULL,
    notas TEXT NULL,

    created_by CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),
    KEY ix_asign_tenant (tenant_id),
    KEY ix_asign_tenant_estado (tenant_id, estado),
    KEY ix_asign_tenant_ficha (tenant_id, ficha_id, estado),
    KEY ix_asign_tenant_puesto (tenant_id, puesto_id, estado),
    KEY ix_asign_fecha_fin (tenant_id, fecha_fin),
    CONSTRAINT fk_asign_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_asign_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asign_puesto FOREIGN KEY (puesto_id) REFERENCES puestos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- Permisos del modulo
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JCSA0PERM0ASIGN_VR0001',  'asignaciones.ver',       'asignaciones', 'ver',       'Ver listado y detalle de asignaciones',     1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0ASIGN_CR0001',  'asignaciones.crear',     'asignaciones', 'crear',     'Crear asignaciones guardia-puesto',         1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0ASIGN_ED0001',  'asignaciones.editar',    'asignaciones', 'editar',    'Editar datos no criticos de asignaciones',  1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0ASIGN_FN0001',  'asignaciones.finalizar', 'asignaciones', 'finalizar', 'Finalizar o cancelar asignaciones',         1, UTC_TIMESTAMP(6));

-- admin_tenant: control total
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0ASIGN_VR0001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0ASIGN_CR0001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0ASIGN_ED0001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000002', '01JCSA0PERM0ASIGN_FN0001', UTC_TIMESTAMP(6));

-- supervisor: ver/crear/editar/finalizar
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0ASIGN_VR0001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0ASIGN_CR0001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0ASIGN_ED0001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000003', '01JCSA0PERM0ASIGN_FN0001', UTC_TIMESTAMP(6));

-- operador y auditor: solo ver
INSERT INTO roles_permisos (role_id, permiso_id, granted_at) VALUES
    ('SEEDRBC0000000000000000004', '01JCSA0PERM0ASIGN_VR0001', UTC_TIMESTAMP(6)),
    ('SEEDRBC0000000000000000005', '01JCSA0PERM0ASIGN_VR0001', UTC_TIMESTAMP(6));

-- @down

DELETE FROM roles_permisos WHERE permiso_id LIKE '01JCSA0PERM0ASIGN_%';
DELETE FROM permisos       WHERE id          LIKE '01JCSA0PERM0ASIGN_%';
DROP TABLE IF EXISTS asignaciones;
