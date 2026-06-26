-- =====================================================================
-- DigiSecure - Migracion 022: contrasena del aspirante
-- =====================================================================
-- El modal de registro ahora captura contrasena + confirmacion para que el
-- postulante quede con credenciales (futuro acceso a su portal / conversion
-- a usuario). Se almacena SOLO el hash Argon2id (jamas la contrasena en claro).
--
-- Nullable: aspirantes creados antes de esta funcionalidad no tienen hash;
-- los nuevos siempre lo setean (el servicio lo exige).
-- =====================================================================

-- @up

ALTER TABLE aspirantes
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER correo_normalizado;

-- @down

ALTER TABLE aspirantes
    DROP COLUMN password_hash;
