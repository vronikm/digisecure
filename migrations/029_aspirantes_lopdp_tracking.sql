-- =====================================================================
-- DigiSecure - Migracion 029: trazabilidad LOPDP del aspirante
-- =====================================================================
-- Registra en el mismo aspirante:
--   1. Fecha/hora y origen de la aceptacion de la Politica de Privacidad
--      y Tratamiento de Datos Personales desde el modal publico.
--   2. Fecha/hora de subida del formulario "Autorizacion tratamiento datos
--      personales (LOPDP)" en el expediente documental.
-- =====================================================================

-- @up

ALTER TABLE aspirantes
    ADD COLUMN politicas_aceptadas_at DATETIME(6) NULL AFTER politicas_version,
    ADD COLUMN politicas_aceptadas_desde VARCHAR(80) NULL AFTER politicas_aceptadas_at,
    ADD COLUMN politicas_aceptadas_ruta VARCHAR(255) NULL AFTER politicas_aceptadas_desde,
    ADD COLUMN politicas_aceptadas_ip VARBINARY(16) NULL AFTER politicas_aceptadas_ruta,
    ADD COLUMN politicas_aceptadas_user_agent VARCHAR(500) NULL AFTER politicas_aceptadas_ip,
    ADD COLUMN lopdp_autorizacion_documento_id CHAR(26) NULL AFTER politicas_aceptadas_user_agent,
    ADD COLUMN lopdp_autorizacion_archivo_nombre VARCHAR(255) NULL AFTER lopdp_autorizacion_documento_id,
    ADD COLUMN lopdp_autorizacion_subida_at DATETIME(6) NULL AFTER lopdp_autorizacion_archivo_nombre,
    ADD KEY ix_aspirantes_lopdp_politicas (tenant_id, acepta_politicas, politicas_aceptadas_at),
    ADD KEY ix_aspirantes_lopdp_autorizacion (tenant_id, lopdp_autorizacion_subida_at),
    ADD KEY ix_aspirantes_lopdp_documento (lopdp_autorizacion_documento_id),
    ADD CONSTRAINT fk_aspirantes_lopdp_documento
        FOREIGN KEY (lopdp_autorizacion_documento_id)
        REFERENCES expediente_documentos(id)
        ON DELETE SET NULL
        ON UPDATE RESTRICT;

UPDATE aspirantes
   SET politicas_aceptadas_at = COALESCE(politicas_aceptadas_at, created_at),
       politicas_aceptadas_desde = COALESCE(
           NULLIF(politicas_aceptadas_desde, ''),
           CASE WHEN origen = 'login_publico' THEN 'modal_postula_gemeseg' ELSE origen END
       ),
       politicas_aceptadas_ruta = COALESCE(
           NULLIF(politicas_aceptadas_ruta, ''),
           CASE WHEN origen = 'login_publico' THEN '/login' ELSE origen END
       ),
       politicas_aceptadas_ip = COALESCE(politicas_aceptadas_ip, ip),
       politicas_aceptadas_user_agent = COALESCE(NULLIF(politicas_aceptadas_user_agent, ''), user_agent)
 WHERE acepta_politicas = 1;

-- @down

ALTER TABLE aspirantes DROP FOREIGN KEY fk_aspirantes_lopdp_documento;

ALTER TABLE aspirantes
    DROP KEY ix_aspirantes_lopdp_documento,
    DROP KEY ix_aspirantes_lopdp_autorizacion,
    DROP KEY ix_aspirantes_lopdp_politicas,
    DROP COLUMN lopdp_autorizacion_subida_at,
    DROP COLUMN lopdp_autorizacion_archivo_nombre,
    DROP COLUMN lopdp_autorizacion_documento_id,
    DROP COLUMN politicas_aceptadas_user_agent,
    DROP COLUMN politicas_aceptadas_ip,
    DROP COLUMN politicas_aceptadas_ruta,
    DROP COLUMN politicas_aceptadas_desde,
    DROP COLUMN politicas_aceptadas_at;
