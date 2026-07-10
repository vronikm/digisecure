-- =====================================================================
-- DigiSecure - Migracion 031: sincronizacion de contratados en Guardias
-- =====================================================================
-- Corrige registros contratados antes del flujo definitivo:
--   - aspirantes contratados sin tenant se asignan a GEMESEG si aplica.
--   - usuarios contratados pasan a cargo Empleado en el tenant operativo.
--   - fichas, expediente y tablas dependientes quedan en el mismo tenant.
--   - se retira el rol aspirante para levantar el acceso restringido.
--
-- La migracion evita forzar cambios si existe un duplicado que romperia
-- indices unicos del tenant destino.
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

UPDATE aspirantes a
   SET a.tenant_id = @gemeseg_tenant_id,
       a.updated_at = UTC_TIMESTAMP(6)
 WHERE @gemeseg_tenant_id IS NOT NULL
   AND a.estado = 'contratado'
   AND a.usuario_id IS NOT NULL
   AND a.tenant_id IS NULL;

UPDATE usuarios u
 INNER JOIN aspirantes a ON a.usuario_id = u.id
  LEFT JOIN usuarios ux_email
         ON CAST(ux_email.tenant_id AS BINARY) = CAST(a.tenant_id AS BINARY)
        AND ux_email.id <> u.id
        AND ux_email.email_normalizado = u.email_normalizado
  LEFT JOIN usuarios ux_cedula
         ON CAST(ux_cedula.tenant_id AS BINARY) = CAST(a.tenant_id AS BINARY)
        AND ux_cedula.id <> u.id
        AND u.cedula_hash IS NOT NULL
        AND ux_cedula.cedula_hash = u.cedula_hash
   SET u.tenant_id = a.tenant_id,
       u.cargo = 'Empleado',
       u.updated_at = UTC_TIMESTAMP(6)
 WHERE a.estado = 'contratado'
   AND a.tenant_id IS NOT NULL
   AND ux_email.id IS NULL
   AND ux_cedula.id IS NULL;

UPDATE fichas f
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
  LEFT JOIN fichas fx
         ON CAST(fx.tenant_id AS BINARY) = CAST(a.tenant_id AS BINARY)
        AND fx.id <> f.id
        AND fx.correlativo = f.correlativo
   SET f.tenant_id = a.tenant_id,
       f.updated_at = UTC_TIMESTAMP(6)
 WHERE a.estado = 'contratado'
   AND a.tenant_id IS NOT NULL
   AND fx.id IS NULL
   AND CAST(f.tenant_id AS BINARY) <> CAST(a.tenant_id AS BINARY);

UPDATE fichas_trabajos_anteriores d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = f.tenant_id
 WHERE a.estado = 'contratado'
   AND CAST(d.tenant_id AS BINARY) <> CAST(f.tenant_id AS BINARY);

UPDATE fichas_hijos_menores d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = f.tenant_id
 WHERE a.estado = 'contratado'
   AND CAST(d.tenant_id AS BINARY) <> CAST(f.tenant_id AS BINARY);

UPDATE fichas_referencias d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = f.tenant_id
 WHERE a.estado = 'contratado'
   AND CAST(d.tenant_id AS BINARY) <> CAST(f.tenant_id AS BINARY);

UPDATE fichas_cursos d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = f.tenant_id
 WHERE a.estado = 'contratado'
   AND CAST(d.tenant_id AS BINARY) <> CAST(f.tenant_id AS BINARY);

SET @has_fichas_vacunas := (
    SELECT COUNT(*)
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'fichas_vacunas'
);

SET @sync_fichas_vacunas_sql := IF(
    @has_fichas_vacunas > 0,
    "UPDATE fichas_vacunas d
      INNER JOIN fichas f ON f.id = d.ficha_id
      INNER JOIN aspirantes a ON a.usuario_id = f.created_by
        SET d.tenant_id = f.tenant_id
      WHERE a.estado = 'contratado'
        AND CAST(d.tenant_id AS BINARY) <> CAST(f.tenant_id AS BINARY)",
    "SET @sync_fichas_vacunas_noop := 1"
);

PREPARE sync_fichas_vacunas_stmt FROM @sync_fichas_vacunas_sql;
EXECUTE sync_fichas_vacunas_stmt;
DEALLOCATE PREPARE sync_fichas_vacunas_stmt;

UPDATE expedientes e
 INNER JOIN fichas f ON f.id = e.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET e.tenant_id = f.tenant_id,
       e.updated_at = UTC_TIMESTAMP(6)
 WHERE a.estado = 'contratado'
   AND CAST(e.tenant_id AS BINARY) <> CAST(f.tenant_id AS BINARY);

UPDATE expediente_documentos d
 INNER JOIN expedientes e ON e.id = d.expediente_id
 INNER JOIN fichas f ON f.id = e.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = e.tenant_id,
       d.updated_at = UTC_TIMESTAMP(6)
 WHERE a.estado = 'contratado'
   AND CAST(d.tenant_id AS BINARY) <> CAST(e.tenant_id AS BINARY);

UPDATE asignaciones d
 INNER JOIN fichas f ON f.id = d.ficha_id
 INNER JOIN aspirantes a ON a.usuario_id = f.created_by
   SET d.tenant_id = f.tenant_id,
       d.updated_at = UTC_TIMESTAMP(6)
 WHERE a.estado = 'contratado'
   AND CAST(d.tenant_id AS BINARY) <> CAST(f.tenant_id AS BINARY);

DELETE ur
  FROM usuarios_roles ur
 INNER JOIN roles r ON r.id = ur.role_id
 INNER JOIN aspirantes a ON a.usuario_id = ur.usuario_id
 WHERE a.estado = 'contratado'
   AND r.codigo = 'aspirante';

-- @down

-- No reversible de forma segura: esta migracion corrige consistencia de datos
-- historicos derivados del flujo de contratacion.
