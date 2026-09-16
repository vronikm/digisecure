-- =====================================================================
-- DigiSecure - Migracion 034: evento de correccion de datos del aspirante
-- =====================================================================
-- Reclutamiento puede corregir los datos del registro de un aspirante
-- (p. ej. un correo mal escrito que impide verificar la cuenta). La
-- bitacora append-only `aspirante_eventos` registra la correccion con el
-- evento 'datos_actualizados' (derecho de rectificacion, LOPDP).
--
-- Agregar un valor al final de un ENUM es un cambio solo de metadatos en
-- MySQL 8 (no reconstruye la tabla).
-- =====================================================================

-- @up

ALTER TABLE aspirante_eventos
    MODIFY COLUMN evento ENUM('registro_creado','link_enviado','link_abierto','correo_validado','registro_completado','token_expirado','reenvio_link','datos_actualizados') NOT NULL;

-- @down

DELETE FROM aspirante_eventos WHERE evento = 'datos_actualizados';

ALTER TABLE aspirante_eventos
    MODIFY COLUMN evento ENUM('registro_creado','link_enviado','link_abierto','correo_validado','registro_completado','token_expirado','reenvio_link') NOT NULL;
