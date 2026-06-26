-- =====================================================================
-- DigiSecure - Migracion 023: verificacion de email del aspirante
-- =====================================================================
-- Flujo de doble opt-in (Fase 2/3 del registro):
--   1. Al registrarse, el aspirante queda en estado 'nuevo' (pendiente de
--      revision) y con email_verificado = 0.
--   2. Se le envia un enlace magico /verificar?token=XXXX.
--   3. Al abrirlo (token valido y no expirado) -> email_verificado = 1.
--      El aspirante sigue sin poder operar; solo se confirma que el correo
--      es real.
--
-- Seguridad: el token NUNCA se guarda en claro, solo su hash SHA-256
-- (mismo criterio que tenants.token_activacion_hash).
-- =====================================================================

-- @up

ALTER TABLE aspirantes
    ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado,
    ADD COLUMN email_verificado_at DATETIME(6) NULL AFTER email_verificado,
    ADD COLUMN verificacion_token_hash CHAR(64) NULL AFTER email_verificado_at,
    ADD COLUMN verificacion_token_expira_at DATETIME(6) NULL AFTER verificacion_token_hash,
    ADD KEY ix_aspirantes_verif_token (verificacion_token_hash);

-- @down

ALTER TABLE aspirantes
    DROP KEY ix_aspirantes_verif_token,
    DROP COLUMN verificacion_token_expira_at,
    DROP COLUMN verificacion_token_hash,
    DROP COLUMN email_verificado_at,
    DROP COLUMN email_verificado;
