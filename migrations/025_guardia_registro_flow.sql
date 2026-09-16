-- =====================================================================
-- DigiSecure - Migracion 025: flujo seguro de registro de guardias
-- =====================================================================
-- Refina el embudo del aspirante a guardia:
--   nuevo  -> (valida correo, link vigente <=48h) -> aspirante
--   El aspirante puede iniciar sesion PERO con acceso restringido SOLO a
--   /guardias/crear (gate por el rol 'aspirante').
--
-- Cambios:
--   1. estado: se agrega el valor 'aspirante'.
--   2. aspirante_eventos: bitacora append-only (sin tenant) para auditar el
--      ciclo: registro_creado, link_enviado, link_abierto, correo_validado,
--      registro_completado, token_expirado, reenvio_link.
--   3. Rol 'aspirante' en el tenant de plataforma, con SOLO el permiso
--      guardias.crear (para que pueda completar su ficha). El confinamiento a
--      esa unica vista lo aplica AspiranteGateMiddleware.
--
-- Seguridad: el token de verificacion ya se guarda hasheado (SHA-256) y es de
-- un solo uso (migracion 023). Aqui no se almacena ningun token en claro.
-- =====================================================================

-- @up

ALTER TABLE aspirantes
    MODIFY COLUMN estado ENUM('nuevo','aspirante','en_revision','contactado','aprobado','descartado','contratado') NOT NULL DEFAULT 'nuevo';

CREATE TABLE aspirante_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aspirante_id CHAR(26) NULL,
    email VARCHAR(190) NULL,
    evento ENUM('registro_creado','link_enviado','link_abierto','correo_validado','registro_completado','token_expirado','reenvio_link') NOT NULL,
    ip VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    detalles_json JSON NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_aspev_aspirante (aspirante_id),
    KEY ix_aspev_email (email, created_at),
    KEY ix_aspev_evento (evento, created_at),
    KEY ix_aspev_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

SET @ptid := (SELECT id FROM tenants WHERE platform_marker = 'Y' ORDER BY created_at ASC LIMIT 1);

SET @ptid := COALESCE(@ptid, (SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1));

INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at)
SELECT 'ASPIRANTEROLE0000000000001', @ptid, 'aspirante', 'Aspirante (registro de guardia)',
       'Acceso restringido: solo puede completar su propia ficha de guardia', 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
WHERE @ptid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM roles WHERE id = 'ASPIRANTEROLE0000000000001');

INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
SELECT 'ASPIRANTEROLE0000000000001', p.id, UTC_TIMESTAMP(6)
FROM permisos p
LEFT JOIN roles_permisos rp ON rp.role_id = 'ASPIRANTEROLE0000000000001' AND rp.permiso_id = p.id
WHERE p.codigo = 'guardias.crear'
  AND rp.role_id IS NULL
  AND EXISTS (SELECT 1 FROM roles WHERE id = 'ASPIRANTEROLE0000000000001');

-- @down

DELETE FROM roles_permisos WHERE role_id = 'ASPIRANTEROLE0000000000001';
DELETE FROM roles WHERE id = 'ASPIRANTEROLE0000000000001';
DROP TABLE IF EXISTS aspirante_eventos;
ALTER TABLE aspirantes
    MODIFY COLUMN estado ENUM('nuevo','en_revision','contactado','aprobado','descartado','contratado') NOT NULL DEFAULT 'nuevo';
