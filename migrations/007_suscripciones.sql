-- =====================================================================
-- DigiSecure - Migración 007: Suscripciones y Planes (Fase 11)
-- =====================================================================
-- Introduce el módulo SaaS de suscripciones:
--
--   planes                — Catálogo GLOBAL de planes comerciales
--   suscripcion_ciclos    — Historial de ciclos de suscripción por tenant
--                           (renovaciones, upgrades, downgrades, pagos)
--
-- La tabla `tenants` ya trae las columnas plan_codigo/plan_inicio/
-- plan_vencimiento/max_* desde 001. Aquí añadimos:
--   - Catálogo canónico de planes (JSON de características)
--   - Historial auditable de cambios/renovaciones
--   - Permisos de administración de suscripción
-- =====================================================================

-- @up

CREATE TABLE planes (
    id CHAR(26) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(500) NULL,
    nivel TINYINT UNSIGNED NOT NULL DEFAULT 0,
    precio_mensual_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    precio_anual_usd   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_usuarios INT UNSIGNED NOT NULL DEFAULT 5,
    max_guardias INT UNSIGNED NOT NULL DEFAULT 50,
    max_storage_mb INT UNSIGNED NOT NULL DEFAULT 1024,
    caracteristicas JSON NULL,
    es_publico TINYINT(1) NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_planes_codigo (codigo),
    KEY ix_planes_nivel (nivel),
    KEY ix_planes_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE suscripcion_ciclos (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    plan_codigo VARCHAR(50) NOT NULL,
    tipo ENUM('inicial','renovacion','upgrade','downgrade','cancelacion') NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    precio_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado_pago ENUM('pendiente','pagado','vencido','reembolsado','cortesia') NOT NULL DEFAULT 'pendiente',
    metodo_pago VARCHAR(50) NULL,
    referencia_pago VARCHAR(120) NULL,
    pagado_at DATETIME(6) NULL,
    notas TEXT NULL,
    created_by CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_ciclos_tenant_fecha (tenant_id, fecha_inicio),
    KEY ix_ciclos_tenant_estado (tenant_id, estado_pago),
    KEY ix_ciclos_plan (plan_codigo),
    CONSTRAINT fk_ciclos_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_ciclos_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- Seed: catálogo de planes estándar
-- ---------------------------------------------------------------------
INSERT INTO planes
    (id, codigo, nombre, descripcion, nivel, precio_mensual_usd, precio_anual_usd,
     max_usuarios, max_guardias, max_storage_mb, caracteristicas, es_publico, activo, orden,
     created_at, updated_at)
VALUES
    ('01JBW11PLAN0000000000TRIAL', 'trial', 'Trial',
     'Plan de prueba gratuito por 30 días. Ideal para evaluar la plataforma.',
     0, 0.00, 0.00, 2, 10, 256,
     JSON_OBJECT(
        'duracion_dias', 30,
        'soporte', 'email',
        'sla', 'ninguno',
        'marca_blanca', false,
        'exportaciones', true,
        'notificaciones_whatsapp', false
     ),
     1, 1, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),

    ('01JBW11PLAN000000000BASICO', 'basico', 'Básico',
     'Ideal para empresas pequeñas hasta 50 guardias.',
     10, 49.00, 490.00, 5, 50, 1024,
     JSON_OBJECT(
        'soporte', 'email',
        'sla', '48h',
        'marca_blanca', false,
        'exportaciones', true,
        'notificaciones_whatsapp', false,
        'pdf_oficiales', true
     ),
     1, 1, 2, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),

    ('01JBW11PLAN00000000PROFESI', 'profesional', 'Profesional',
     'Para empresas en crecimiento con hasta 200 guardias y múltiples operadores.',
     20, 119.00, 1190.00, 15, 200, 5120,
     JSON_OBJECT(
        'soporte', 'email+chat',
        'sla', '24h',
        'marca_blanca', false,
        'exportaciones', true,
        'notificaciones_whatsapp', true,
        'pdf_oficiales', true,
        'kpis_avanzados', true
     ),
     1, 1, 3, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),

    ('01JBW11PLAN000000EMPRESARI', 'empresarial', 'Empresarial',
     'Sin límites prácticos. Soporte prioritario y personalización avanzada.',
     30, 299.00, 2990.00, 50, 1000, 20480,
     JSON_OBJECT(
        'soporte', '24/7',
        'sla', '4h',
        'marca_blanca', true,
        'exportaciones', true,
        'notificaciones_whatsapp', true,
        'pdf_oficiales', true,
        'kpis_avanzados', true,
        'integraciones_api', true,
        'onboarding_dedicado', true
     ),
     1, 1, 4, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));

-- ---------------------------------------------------------------------
-- Permisos: suscripción
-- ---------------------------------------------------------------------
INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JBW11PERM0SUSC_VER000001', 'suscripcion.ver',      'suscripcion', 'ver',      'Ver estado de la suscripción y uso del tenant', 1, UTC_TIMESTAMP(6)),
    ('01JBW11PERM0SUSC_GEST00001', 'suscripcion.gestionar','suscripcion', 'gestionar','Cambiar plan, registrar pagos y renovaciones',   1, UTC_TIMESTAMP(6));

-- Asignar permisos de suscripción a los roles que ya tienen tenants.*
-- (admin tenant y super admin). Como los IDs de roles seed varían, lo
-- hacemos por JOIN con el permiso 'tenants.ver' que ya existe.
INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT rp.role_id, '01JBW11PERM0SUSC_VER000001', UTC_TIMESTAMP(6)
FROM roles_permisos rp
INNER JOIN permisos p ON p.id = rp.permiso_id
LEFT JOIN roles_permisos ya
       ON ya.role_id = rp.role_id AND ya.permiso_id = '01JBW11PERM0SUSC_VER000001'
WHERE p.codigo = 'tenants.ver' AND ya.role_id IS NULL;

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT rp.role_id, '01JBW11PERM0SUSC_GEST00001', UTC_TIMESTAMP(6)
FROM roles_permisos rp
INNER JOIN permisos p ON p.id = rp.permiso_id
LEFT JOIN roles_permisos ya
       ON ya.role_id = rp.role_id AND ya.permiso_id = '01JBW11PERM0SUSC_GEST00001'
WHERE p.codigo = 'tenants.editar' AND ya.role_id IS NULL;

-- @down

DELETE FROM roles_permisos WHERE permiso_id IN (
    '01JBW11PERM0SUSC_VER000001',
    '01JBW11PERM0SUSC_GEST00001'
);

DELETE FROM permisos WHERE id IN (
    '01JBW11PERM0SUSC_VER000001',
    '01JBW11PERM0SUSC_GEST00001'
);

DROP TABLE IF EXISTS suscripcion_ciclos;
DROP TABLE IF EXISTS planes;
