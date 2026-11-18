-- =====================================================================
-- DigiSecure - Migracion 037: sistema a la medida de GEMESEG
-- =====================================================================
-- El sistema deja de ser un SaaS multiempresa con planes: es a la medida
-- de GEMESEG y el superadministrador tiene acceso total.
--
-- 1. El superadministrador del sistema (rol global 'superadmin') pasa a la
--    empresa GEMESEG. Antes vivia en la empresa de plataforma, sin datos,
--    y no veia guardias, expedientes, clientes ni aspirantes. Conserva su
--    rol global; las demas cuentas no se tocan.
-- 2. El rol global 'superadmin' recibe todos los permisos del catalogo
--    (el codigo ya se los concede; esto mantiene coherente la pantalla de
--    roles).
-- 3. Se retiran los permisos de planes, suscripcion y panel multiempresa,
--    que ya no tienen codigo que los use.
--
-- Las tablas de planes, ciclos y registros de empresa se conservan con sus
-- datos; solo dejan de usarse. Idempotente.
-- =====================================================================

-- @up

SET @gemeseg_tenant_id := (
    SELECT id
      FROM tenants
     WHERE UPPER(razon_social) LIKE '%GEMESEG%'
        OR UPPER(COALESCE(nombre_comercial, '')) LIKE '%GEMESEG%'
     ORDER BY created_at ASC
     LIMIT 1
);

-- Si GEMESEG ya tuviera una cuenta con el mismo correo que un superadmin, no
-- se mueve nada (lo impediria uq_usuarios_tenant_email): se resuelve a mano.
SET @colision_email := (
    SELECT COUNT(*)
      FROM usuarios sa
      INNER JOIN usuarios_roles ur ON ur.usuario_id = sa.id
      INNER JOIN roles r ON r.id = ur.role_id AND r.codigo = 'superadmin' AND r.tenant_id IS NULL
      INNER JOIN usuarios otro
              ON BINARY otro.tenant_id = BINARY @gemeseg_tenant_id
             AND otro.email_normalizado = sa.email_normalizado
             AND otro.id <> sa.id
);

UPDATE usuarios u
   SET u.tenant_id = @gemeseg_tenant_id,
       u.updated_at = UTC_TIMESTAMP(6)
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND @colision_email = 0
   AND BINARY u.tenant_id <> BINARY @gemeseg_tenant_id
   AND EXISTS (
        SELECT 1
          FROM usuarios_roles ur
          INNER JOIN roles r ON r.id = ur.role_id
         WHERE ur.usuario_id = u.id
           AND r.codigo = 'superadmin'
           AND r.tenant_id IS NULL
   );

DELETE rp
  FROM roles_permisos rp
  INNER JOIN permisos p ON p.id = rp.permiso_id
 WHERE p.codigo LIKE 'superadmin.%'
    OR p.codigo LIKE 'suscripcion.%';

DELETE FROM permisos
 WHERE codigo LIKE 'superadmin.%'
    OR codigo LIKE 'suscripcion.%';

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, p.id, UTC_TIMESTAMP(6)
  FROM roles r
  CROSS JOIN permisos p
  LEFT JOIN roles_permisos ya
         ON ya.role_id = r.id AND ya.permiso_id = p.id
 WHERE r.codigo = 'superadmin'
   AND r.tenant_id IS NULL
   AND ya.role_id IS NULL;

-- @down

-- Reversion aproximada: recrea los permisos retirados, se los concede al
-- superadmin global (y suscripcion.ver a quien tenga tenants.ver, como la
-- 035) y devuelve al superadmin a la empresa de plataforma.
INSERT IGNORE INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at) VALUES
    ('01JCSA0PERM0SADMIN_PNL001', 'superadmin.panel', 'superadmin', 'panel', 'Acceso al panel global del operador', 1, UTC_TIMESTAMP(6)),
    ('01KSPN0PRM0SADMINPLNGS0001', 'superadmin.planes.gestionar', 'superadmin', 'planes.gestionar', 'Crear y editar planes globales', 1, UTC_TIMESTAMP(6)),
    ('01KSPN0PRM0SADMINPLNVR0001', 'superadmin.planes.ver', 'superadmin', 'planes.ver', 'Ver catalogo global de planes', 1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0SADMIN_RGV01', 'superadmin.registros.ver', 'superadmin', 'registros.ver', 'Ver log de registros publicos de empresa', 1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0SADMIN_TGS01', 'superadmin.tenants.gestionar', 'superadmin', 'tenants.gestionar', 'Cambiar plan, suspender o reactivar tenants', 1, UTC_TIMESTAMP(6)),
    ('01JCSA0PERM0SADMIN_TVR01', 'superadmin.tenants.ver', 'superadmin', 'tenants.ver', 'Ver listado y detalle de todos los tenants', 1, UTC_TIMESTAMP(6)),
    ('01JBW11PERM0SUSC_GEST00001', 'suscripcion.gestionar', 'suscripcion', 'gestionar', 'Cambiar plan, registrar pagos y renovaciones', 1, UTC_TIMESTAMP(6)),
    ('01JBW11PERM0SUSC_VER000001', 'suscripcion.ver', 'suscripcion', 'ver', 'Ver estado de la suscripcion y uso del tenant', 1, UTC_TIMESTAMP(6));

INSERT IGNORE INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, p.id, UTC_TIMESTAMP(6)
  FROM roles r
  CROSS JOIN permisos p
 WHERE r.codigo = 'superadmin'
   AND r.tenant_id IS NULL
   AND (p.codigo LIKE 'superadmin.%' OR p.codigo LIKE 'suscripcion.%');

INSERT IGNORE INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT rp.role_id, p_ver.id, UTC_TIMESTAMP(6)
  FROM roles_permisos rp
  INNER JOIN permisos p ON p.id = rp.permiso_id AND p.codigo = 'tenants.ver'
  INNER JOIN permisos p_ver ON p_ver.codigo = 'suscripcion.ver';

SET @plataforma_tenant_id := (SELECT id FROM tenants WHERE platform_marker = 'Y' LIMIT 1);

UPDATE usuarios u
   SET u.tenant_id = @plataforma_tenant_id,
       u.updated_at = UTC_TIMESTAMP(6)
 WHERE @plataforma_tenant_id IS NOT NULL
   AND EXISTS (
        SELECT 1
          FROM usuarios_roles ur
          INNER JOIN roles r ON r.id = ur.role_id
         WHERE ur.usuario_id = u.id
           AND r.codigo = 'superadmin'
           AND r.tenant_id IS NULL
   );
