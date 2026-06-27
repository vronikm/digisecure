-- =====================================================================
-- DigiSecure - Sincronización IDEMPOTENTE del schema de Aspirantes (022-025)
-- =====================================================================
-- Para ejecutar en phpMyAdmin (pestaña SQL) sobre la BD de DigiSecure.
-- Aplica SOLO lo que falte. Es seguro ejecutarlo varias veces.
-- Útil cuando la tabla `migraciones` dice que 021-025 están aplicadas pero
-- el schema real NO fue ejecutado (filas insertadas manualmente).
-- =====================================================================

-- Hora local de Ecuador (America/Guayaquil, UTC-5 sin horario de verano) para
-- que NOW() registre los timestamps en hora local.
SET time_zone = '-05:00';

DELIMITER $$
DROP PROCEDURE IF EXISTS _ds_sync_aspirante $$
CREATE PROCEDURE _ds_sync_aspirante()
BEGIN
    DECLARE v_db VARCHAR(64) DEFAULT DATABASE();

    -- 022: password_hash
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = v_db AND TABLE_NAME = 'aspirantes' AND COLUMN_NAME = 'password_hash') THEN
        ALTER TABLE aspirantes ADD COLUMN password_hash VARCHAR(255) NULL AFTER correo_normalizado;
    END IF;

    -- 023: verificación de email
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = v_db AND TABLE_NAME = 'aspirantes' AND COLUMN_NAME = 'email_verificado') THEN
        ALTER TABLE aspirantes
            ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado,
            ADD COLUMN email_verificado_at DATETIME(6) NULL AFTER email_verificado,
            ADD COLUMN verificacion_token_hash CHAR(64) NULL AFTER email_verificado_at,
            ADD COLUMN verificacion_token_expira_at DATETIME(6) NULL AFTER verificacion_token_hash,
            ADD KEY ix_aspirantes_verif_token (verificacion_token_hash);
    END IF;

    -- 024: aprobación (columnas)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = v_db AND TABLE_NAME = 'aspirantes' AND COLUMN_NAME = 'usuario_id') THEN
        ALTER TABLE aspirantes
            ADD COLUMN usuario_id CHAR(26) NULL AFTER tenant_id,
            ADD COLUMN aprobado_at DATETIME(6) NULL AFTER email_verificado_at,
            ADD COLUMN revisado_por CHAR(26) NULL AFTER aprobado_at,
            ADD COLUMN motivo_descarte VARCHAR(255) NULL AFTER revisado_por,
            ADD KEY ix_aspirantes_usuario (usuario_id);
    END IF;

    -- estado: incluir 'aspirante' y 'aprobado' (idempotente: modifica al conjunto completo)
    ALTER TABLE aspirantes MODIFY COLUMN estado
        ENUM('nuevo','aspirante','en_revision','contactado','aprobado','descartado','contratado') NOT NULL DEFAULT 'nuevo';

    -- 024: FK aspirantes.usuario_id -> usuarios(id) (best-effort: si las collations no coinciden, se omite)
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = v_db AND CONSTRAINT_NAME = 'fk_aspirantes_usuario') THEN
        BEGIN
            DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
            ALTER TABLE aspirantes ADD CONSTRAINT fk_aspirantes_usuario
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT;
        END;
    END IF;

    -- 025: tabla aspirante_eventos
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = v_db AND TABLE_NAME = 'aspirante_eventos') THEN
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
    END IF;

    -- 024: permiso aspirantes.gestionar
    IF NOT EXISTS (SELECT 1 FROM permisos WHERE codigo = 'aspirantes.gestionar') THEN
        INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at)
        VALUES ('SEEDPERMASPIRANTGESTION001','aspirantes.gestionar','aspirantes','gestionar','Revisar, aprobar y rechazar aspirantes (postulantes)',1,NOW(6));
    END IF;
    INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
        SELECT r.id, p.id, NOW(6) FROM roles r
        INNER JOIN permisos p ON p.codigo = 'aspirantes.gestionar'
        LEFT JOIN roles_permisos rp ON rp.role_id = r.id AND rp.permiso_id = p.id
        WHERE r.codigo IN ('admin_tenant','supervisor') AND rp.role_id IS NULL;

    -- 025: rol 'aspirante' (en tenant de plataforma) + grant guardias.crear
    IF NOT EXISTS (SELECT 1 FROM roles WHERE id = 'ASPIRANTEROLE0000000000001') THEN
        INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at)
        SELECT 'ASPIRANTEROLE0000000000001', t.id, 'aspirante', 'Aspirante (registro de guardia)',
               'Acceso restringido: solo completar su propia ficha de guardia', 0, NOW(6), NOW(6)
        FROM tenants t ORDER BY (t.platform_marker = 'Y') DESC, t.created_at ASC LIMIT 1;
    END IF;
    INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
        SELECT 'ASPIRANTEROLE0000000000001', p.id, NOW(6) FROM permisos p
        LEFT JOIN roles_permisos rp ON rp.role_id = 'ASPIRANTEROLE0000000000001' AND rp.permiso_id = p.id
        WHERE p.codigo = 'guardias.crear' AND rp.role_id IS NULL;
END $$
DELIMITER ;

CALL _ds_sync_aspirante();
DROP PROCEDURE _ds_sync_aspirante;
