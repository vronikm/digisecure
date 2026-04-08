-- =====================================================================
-- DigiSecure - Migración 001: Núcleo de Seguridad
-- =====================================================================
-- Crea las tablas base sobre las que se apoya TODO el resto del sistema:
--
--   Globales (sin tenant_id):
--     - tenants                (empresa cliente del SaaS)
--     - permisos               (catálogo de acciones del sistema)
--
--   Tenant-aware (con tenant_id):
--     - usuarios               (credenciales y perfil del usuario del sistema)
--     - roles                  (rol asignable; NULL tenant = rol del sistema)
--     - roles_permisos         (N:N rol ↔ permiso)
--     - usuarios_roles         (N:N usuario ↔ rol)
--     - intentos_login         (rate limit + forensics)
--     - auditoria              (log de eventos sensibles para LOPDP/forensics)
--     - consentimientos_lopdp  (consentimientos firmados por titulares)
--
-- Convenciones aplicadas:
--   - PK CHAR(26) (ULID) para todas las entidades de negocio
--   - id BIGINT autoincrement solo en tablas de log de alto volumen
--   - timestamps DATETIME(6) en UTC (PdoConnection fija +00:00)
--   - InnoDB + utf8mb4 + utf8mb4_0900_ai_ci
--   - ROW_FORMAT=DYNAMIC para soportar índices grandes y JSON eficiente
--   - FK ON DELETE RESTRICT por defecto (no borrado físico — anonimización)
--   - índices preparados para los WHERE más frecuentes que usaremos
--
-- IMPORTANTE: una vez aplicada esta migración, NO se modifica.
-- Cualquier cambio futuro va en una migración nueva (002, 003, ...).
-- =====================================================================

-- @up

CREATE TABLE tenants (
    id CHAR(26) NOT NULL,
    razon_social VARCHAR(255) NOT NULL,
    ruc CHAR(13) NOT NULL,
    nombre_comercial VARCHAR(255) NULL,
    email_contacto VARCHAR(190) NOT NULL,
    telefono VARCHAR(30) NULL,
    direccion VARCHAR(255) NULL,
    ciudad VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    pais CHAR(2) NOT NULL DEFAULT 'EC',
    estado ENUM('activo','suspendido','cancelado','pendiente') NOT NULL DEFAULT 'pendiente',
    plan_codigo VARCHAR(50) NOT NULL DEFAULT 'trial',
    plan_inicio DATE NULL,
    plan_vencimiento DATE NULL,
    max_usuarios INT UNSIGNED NOT NULL DEFAULT 5,
    max_guardias INT UNSIGNED NOT NULL DEFAULT 50,
    max_storage_mb INT UNSIGNED NOT NULL DEFAULT 1024,
    timezone VARCHAR(50) NOT NULL DEFAULT 'America/Guayaquil',
    locale VARCHAR(10) NOT NULL DEFAULT 'es_EC',
    notas TEXT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenants_ruc (ruc),
    KEY ix_tenants_estado (estado),
    KEY ix_tenants_plan_vencimiento (plan_vencimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE usuarios (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    email VARCHAR(190) NOT NULL,
    email_normalizado VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombres VARCHAR(120) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    cedula_hash CHAR(64) NULL,
    cedula_cifrada VARBINARY(1024) NULL,
    telefono VARCHAR(30) NULL,
    cargo VARCHAR(120) NULL,
    estado ENUM('activo','inactivo','bloqueado','pendiente_verificacion') NOT NULL DEFAULT 'pendiente_verificacion',
    mfa_habilitado TINYINT(1) NOT NULL DEFAULT 0,
    mfa_secret_cifrado VARBINARY(1024) NULL,
    ultimo_login_at DATETIME(6) NULL,
    ultimo_login_ip VARBINARY(16) NULL,
    intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME(6) NULL,
    password_changed_at DATETIME(6) NULL,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0,
    token_recuperacion_hash CHAR(64) NULL,
    token_recuperacion_expira_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    created_by CHAR(26) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_tenant_email (tenant_id, email_normalizado),
    UNIQUE KEY uq_usuarios_tenant_cedula_hash (tenant_id, cedula_hash),
    KEY ix_usuarios_tenant_estado (tenant_id, estado),
    KEY ix_usuarios_token_recuperacion (token_recuperacion_hash),
    CONSTRAINT fk_usuarios_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_usuarios_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE permisos (
    id CHAR(26) NOT NULL,
    codigo VARCHAR(100) NOT NULL,
    recurso VARCHAR(50) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NULL,
    es_sistema TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permisos_codigo (codigo),
    KEY ix_permisos_recurso (recurso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE roles (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    es_sistema TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_tenant_codigo (tenant_id, codigo),
    KEY ix_roles_es_sistema (es_sistema),
    CONSTRAINT fk_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE roles_permisos (
    role_id CHAR(26) NOT NULL,
    permiso_id CHAR(26) NOT NULL,
    granted_at DATETIME(6) NOT NULL,
    PRIMARY KEY (role_id, permiso_id),
    KEY ix_roles_permisos_permiso (permiso_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE usuarios_roles (
    usuario_id CHAR(26) NOT NULL,
    role_id CHAR(26) NOT NULL,
    assigned_at DATETIME(6) NOT NULL,
    assigned_by CHAR(26) NULL,
    PRIMARY KEY (usuario_id, role_id),
    KEY ix_ur_role (role_id),
    KEY ix_ur_assigned_by (assigned_by),
    CONSTRAINT fk_ur_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_ur_assigned_by FOREIGN KEY (assigned_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE intentos_login (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id CHAR(26) NULL,
    usuario_id CHAR(26) NULL,
    email_intentado VARCHAR(190) NOT NULL,
    email_normalizado VARCHAR(190) NOT NULL,
    ip VARBINARY(16) NOT NULL,
    user_agent VARCHAR(500) NULL,
    exitoso TINYINT(1) NOT NULL,
    motivo_fallo VARCHAR(50) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_intentos_email_created (email_normalizado, created_at),
    KEY ix_intentos_ip_created (ip, created_at),
    KEY ix_intentos_tenant_created (tenant_id, created_at),
    KEY ix_intentos_usuario_created (usuario_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id CHAR(26) NULL,
    usuario_id CHAR(26) NULL,
    evento VARCHAR(80) NOT NULL,
    entidad_tipo VARCHAR(80) NULL,
    entidad_id CHAR(26) NULL,
    ip VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    detalles_json JSON NULL,
    nivel ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_auditoria_tenant_created (tenant_id, created_at),
    KEY ix_auditoria_usuario_created (usuario_id, created_at),
    KEY ix_auditoria_entidad (entidad_tipo, entidad_id),
    KEY ix_auditoria_evento_created (evento, created_at),
    KEY ix_auditoria_nivel_created (nivel, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE consentimientos_lopdp (
    id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    titular_tipo ENUM('usuario','guardia') NOT NULL,
    titular_id CHAR(26) NOT NULL,
    proposito VARCHAR(150) NOT NULL,
    base_legal ENUM('consentimiento','contrato','obligacion_legal','interes_vital','interes_publico','interes_legitimo') NOT NULL DEFAULT 'consentimiento',
    texto_legal_version VARCHAR(20) NOT NULL,
    texto_legal_hash CHAR(64) NOT NULL,
    aceptado TINYINT(1) NOT NULL,
    ip_aceptacion VARBINARY(16) NULL,
    user_agent_aceptacion VARCHAR(500) NULL,
    aceptado_at DATETIME(6) NOT NULL,
    revocado_at DATETIME(6) NULL,
    revocado_motivo VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_consentimientos_tenant_titular (tenant_id, titular_tipo, titular_id),
    KEY ix_consentimientos_proposito (proposito),
    KEY ix_consentimientos_revocado (revocado_at),
    CONSTRAINT fk_consentimientos_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- SEMILLAS CATÁLOGO DE PERMISOS (sistema, todas con es_sistema=1)
-- Se identifican por código (recurso.accion). Los IDs son CHAR(26) fijos
-- de Crockford Base32 reservados para semillas (prefijo SEEDPRM...).
-- Si en el futuro se añaden permisos, va en una migración nueva.
-- ---------------------------------------------------------------------

INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
  ('SEEDPRM0000000000000000001', 'tenants.ver',          'tenants',     'ver',       'Ver datos del tenant propio',                  1, NOW(6)),
  ('SEEDPRM0000000000000000002', 'tenants.editar',       'tenants',     'editar',    'Editar datos del tenant propio',               1, NOW(6)),
  ('SEEDPRM0000000000000000003', 'usuarios.ver',         'usuarios',    'ver',       'Ver listado y detalle de usuarios',            1, NOW(6)),
  ('SEEDPRM0000000000000000004', 'usuarios.crear',       'usuarios',    'crear',     'Crear usuarios del sistema',                   1, NOW(6)),
  ('SEEDPRM0000000000000000005', 'usuarios.editar',      'usuarios',    'editar',    'Editar usuarios del sistema',                  1, NOW(6)),
  ('SEEDPRM0000000000000000006', 'usuarios.bloquear',    'usuarios',    'bloquear',  'Bloquear o desbloquear usuarios',              1, NOW(6)),
  ('SEEDPRM0000000000000000007', 'usuarios.eliminar',    'usuarios',    'eliminar',  'Anonimizar usuarios (LOPDP)',                  1, NOW(6)),
  ('SEEDPRM0000000000000000008', 'roles.ver',            'roles',       'ver',       'Ver roles y permisos',                         1, NOW(6)),
  ('SEEDPRM0000000000000000009', 'roles.gestionar',      'roles',       'gestionar', 'Crear, editar y asignar roles',                1, NOW(6)),
  ('SEEDPRM000000000000000000A', 'guardias.ver',         'guardias',    'ver',       'Ver fichas de guardias',                       1, NOW(6)),
  ('SEEDPRM000000000000000000B', 'guardias.crear',       'guardias',    'crear',     'Crear fichas de guardias',                     1, NOW(6)),
  ('SEEDPRM000000000000000000C', 'guardias.editar',      'guardias',    'editar',    'Editar fichas de guardias',                    1, NOW(6)),
  ('SEEDPRM000000000000000000D', 'guardias.eliminar',    'guardias',    'eliminar',  'Anonimizar fichas de guardias (LOPDP)',        1, NOW(6)),
  ('SEEDPRM000000000000000000E', 'expedientes.ver',      'expedientes', 'ver',       'Ver expedientes documentales',                 1, NOW(6)),
  ('SEEDPRM000000000000000000F', 'expedientes.cargar',   'expedientes', 'cargar',    'Cargar documentos al expediente',              1, NOW(6)),
  ('SEEDPRM000000000000000000G', 'expedientes.validar',  'expedientes', 'validar',   'Validar documentos del expediente',            1, NOW(6)),
  ('SEEDPRM000000000000000000H', 'reportes.ver',         'reportes',    'ver',       'Ver reportes y KPIs',                          1, NOW(6)),
  ('SEEDPRM000000000000000000J', 'reportes.exportar',    'reportes',    'exportar',  'Exportar reportes (PDF/Excel)',                1, NOW(6)),
  ('SEEDPRM000000000000000000K', 'auditoria.ver',        'auditoria',   'ver',       'Consultar el log de auditoría',                1, NOW(6)),
  ('SEEDPRM000000000000000000M', 'lopdp.gestionar',      'lopdp',       'gestionar', 'Gestionar consentimientos y solicitudes ARCO', 1, NOW(6));

-- ---------------------------------------------------------------------
-- SEMILLAS CATÁLOGO DE ROLES DEL SISTEMA (tenant_id NULL, es_sistema=1)
-- Estos roles son plantillas: cuando se crea un tenant, se clonan
-- (vía código de aplicación) como roles del tenant. Aquí solo se
-- define el catálogo base.
-- ---------------------------------------------------------------------

INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at) VALUES
  ('SEEDRBC0000000000000000001', NULL, 'superadmin',   'Super Administrador',       'Acceso total al sistema. Solo para staff DigiSecure.',   1, NOW(6), NOW(6)),
  ('SEEDRBC0000000000000000002', NULL, 'admin_tenant', 'Administrador del tenant',  'Administra usuarios, roles y configuración del tenant.', 1, NOW(6), NOW(6)),
  ('SEEDRBC0000000000000000003', NULL, 'supervisor',   'Supervisor de operaciones', 'Gestiona guardias, expedientes y reportes.',             1, NOW(6), NOW(6)),
  ('SEEDRBC0000000000000000004', NULL, 'operador',     'Operador',                  'Captura datos de guardias y expedientes.',               1, NOW(6), NOW(6)),
  ('SEEDRBC0000000000000000005', NULL, 'auditor',      'Auditor (solo lectura)',    'Acceso de solo lectura para auditoría interna/externa.', 1, NOW(6), NOW(6));

-- ---------------------------------------------------------------------
-- ASIGNACIÓN DE PERMISOS A ROLES DEL SISTEMA
-- superadmin → todos los permisos
-- admin_tenant → todos excepto tenants.* (que va en superadmin)
-- supervisor → guardias, expedientes, reportes (sin gestión usuarios/roles)
-- operador → guardias.ver/crear/editar, expedientes.ver/cargar
-- auditor → solo *.ver + auditoria.ver
-- ---------------------------------------------------------------------

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT 'SEEDRBC0000000000000000001', id, NOW(6) FROM permisos WHERE es_sistema = 1;

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT 'SEEDRBC0000000000000000002', id, NOW(6) FROM permisos
WHERE es_sistema = 1
  AND codigo IN (
    'usuarios.ver','usuarios.crear','usuarios.editar','usuarios.bloquear','usuarios.eliminar',
    'roles.ver','roles.gestionar',
    'guardias.ver','guardias.crear','guardias.editar','guardias.eliminar',
    'expedientes.ver','expedientes.cargar','expedientes.validar',
    'reportes.ver','reportes.exportar',
    'auditoria.ver','lopdp.gestionar'
  );

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT 'SEEDRBC0000000000000000003', id, NOW(6) FROM permisos
WHERE es_sistema = 1
  AND codigo IN (
    'usuarios.ver',
    'guardias.ver','guardias.crear','guardias.editar',
    'expedientes.ver','expedientes.cargar','expedientes.validar',
    'reportes.ver','reportes.exportar'
  );

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT 'SEEDRBC0000000000000000004', id, NOW(6) FROM permisos
WHERE es_sistema = 1
  AND codigo IN (
    'guardias.ver','guardias.crear','guardias.editar',
    'expedientes.ver','expedientes.cargar'
  );

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT 'SEEDRBC0000000000000000005', id, NOW(6) FROM permisos
WHERE es_sistema = 1
  AND accion = 'ver';

-- @down

DROP TABLE IF EXISTS consentimientos_lopdp;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS intentos_login;
DROP TABLE IF EXISTS usuarios_roles;
DROP TABLE IF EXISTS roles_permisos;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS tenants;
