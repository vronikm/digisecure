-- =====================================================================
-- DigiSecure - Migracion 035: permiso suscripcion.ver para quien
--                             administra la empresa
-- =====================================================================
-- La migracion 007 concedio 'suscripcion.ver' a los roles que en ese
-- momento tenian 'tenants.ver'. Los roles que recibieron 'tenants.ver'
-- despues (admin_tenant entre ellos) quedaron sin el permiso: el
-- administrador de la empresa no podia ver su propio plan ni recibia el
-- aviso de suscripcion vencida.
--
-- Se vuelve a aplicar la misma regla, de forma idempotente. Solo lectura:
-- 'suscripcion.gestionar' (cambiar plan, registrar pagos) sigue siendo
-- del administrador de la plataforma.
-- =====================================================================

-- @up

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT rp.role_id, p_ver.id, UTC_TIMESTAMP(6)
  FROM roles_permisos rp
  INNER JOIN permisos p ON p.id = rp.permiso_id AND p.codigo = 'tenants.ver'
  INNER JOIN permisos p_ver ON p_ver.codigo = 'suscripcion.ver'
  LEFT JOIN roles_permisos ya
         ON ya.role_id = rp.role_id AND ya.permiso_id = p_ver.id
 WHERE ya.role_id IS NULL;

-- @down

-- Se revoca solo donde esta migracion pudo concederlo (roles con
-- tenants.ver), dejando intactos superadmin y auditor de la 007.
DELETE rp_ver
  FROM roles_permisos rp_ver
  INNER JOIN permisos p_ver ON p_ver.id = rp_ver.permiso_id AND p_ver.codigo = 'suscripcion.ver'
  INNER JOIN roles r ON r.id = rp_ver.role_id
 WHERE r.codigo = 'admin_tenant';
