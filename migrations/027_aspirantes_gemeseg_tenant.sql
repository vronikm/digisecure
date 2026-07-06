-- =====================================================================
-- DigiSecure - Migracion 027: aspirantes publicos al tenant GEMESEG
-- =====================================================================
-- El modal publico "Postula con GEMESEG" debe crear aspirantes y usuarios
-- restringidos directamente en el tenant GEMESEG, no en el tenant de
-- plataforma. Esta migracion prepara el rol necesario y corrige registros
-- pendientes/heredados del flujo publico.
-- =====================================================================

-- @up

SET @gemeseg_tenant_id := (
    SELECT id
      FROM tenants
     WHERE UPPER(razon_social) LIKE '%GEMESEG%'
        OR UPPER(COALESCE(nombre_comercial, '')) LIKE '%GEMESEG%'
     ORDER BY (estado = 'activo') DESC, created_at ASC
     LIMIT 1
);

INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at)
SELECT CONCAT('ASP', LEFT(REPLACE(UUID(), '-', ''), 23)),
       @gemeseg_tenant_id,
       'aspirante',
       'Aspirante (registro de guardia)',
       'Acceso restringido: solo puede completar su propia ficha de guardia',
       0,
       UTC_TIMESTAMP(6),
       UTC_TIMESTAMP(6)
WHERE @gemeseg_tenant_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM roles WHERE BINARY tenant_id = BINARY @gemeseg_tenant_id AND codigo = 'aspirante'
  );

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT r.id, p.id, UTC_TIMESTAMP(6)
  FROM roles r
 INNER JOIN permisos p ON p.codigo = 'guardias.crear'
  LEFT JOIN roles_permisos rp ON rp.role_id = r.id AND rp.permiso_id = p.id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND BINARY r.tenant_id = BINARY @gemeseg_tenant_id
   AND r.codigo = 'aspirante'
   AND rp.role_id IS NULL;

UPDATE aspirantes
   SET tenant_id = @gemeseg_tenant_id,
       updated_at = UTC_TIMESTAMP(6)
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND origen = 'login_publico'
   AND estado IN ('nuevo', 'aspirante')
   AND (tenant_id IS NULL OR BINARY tenant_id <> BINARY @gemeseg_tenant_id);

UPDATE usuarios u
 INNER JOIN aspirantes a ON a.usuario_id = u.id
   SET u.tenant_id = @gemeseg_tenant_id,
       u.updated_at = UTC_TIMESTAMP(6)
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY u.tenant_id <> BINARY @gemeseg_tenant_id;

INSERT INTO usuarios_roles (usuario_id, role_id, assigned_at, assigned_by)
SELECT a.usuario_id, r.id, UTC_TIMESTAMP(6), NULL
  FROM aspirantes a
 INNER JOIN roles r ON BINARY r.tenant_id = BINARY @gemeseg_tenant_id AND r.codigo = 'aspirante'
  LEFT JOIN usuarios_roles ur ON ur.usuario_id = a.usuario_id AND ur.role_id = r.id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND a.usuario_id IS NOT NULL
   AND ur.usuario_id IS NULL;

UPDATE fichas f
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET f.tenant_id = @gemeseg_tenant_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY f.tenant_id <> BINARY @gemeseg_tenant_id;

UPDATE fichas_trabajos_anteriores d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = @gemeseg_tenant_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY d.tenant_id <> BINARY @gemeseg_tenant_id;

UPDATE fichas_hijos_menores d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = @gemeseg_tenant_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY d.tenant_id <> BINARY @gemeseg_tenant_id;

UPDATE fichas_referencias d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = @gemeseg_tenant_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY d.tenant_id <> BINARY @gemeseg_tenant_id;

UPDATE fichas_cursos d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = @gemeseg_tenant_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY d.tenant_id <> BINARY @gemeseg_tenant_id;

UPDATE expedientes d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = @gemeseg_tenant_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY d.tenant_id <> BINARY @gemeseg_tenant_id;

UPDATE asignaciones d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = @gemeseg_tenant_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.origen = 'login_publico'
   AND a.estado = 'aspirante'
   AND BINARY d.tenant_id <> BINARY @gemeseg_tenant_id;

-- @down

SET @gemeseg_tenant_id := (
    SELECT id
      FROM tenants
     WHERE UPPER(razon_social) LIKE '%GEMESEG%'
        OR UPPER(COALESCE(nombre_comercial, '')) LIKE '%GEMESEG%'
     ORDER BY (estado = 'activo') DESC, created_at ASC
     LIMIT 1
);

DELETE rp
  FROM roles_permisos rp
 INNER JOIN roles r ON r.id = rp.role_id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND BINARY r.tenant_id = BINARY @gemeseg_tenant_id
   AND r.codigo = 'aspirante'
   AND NOT EXISTS (
       SELECT 1 FROM usuarios_roles ur WHERE ur.role_id = r.id
   );

DELETE r
  FROM roles r
  LEFT JOIN usuarios_roles ur ON ur.role_id = r.id
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND BINARY r.tenant_id = BINARY @gemeseg_tenant_id
   AND r.codigo = 'aspirante'
   AND r.es_sistema = 0
   AND ur.usuario_id IS NULL;
