-- =====================================================================
-- DigiSecure - Migracion 030: multiples archivos por tipo documental
-- =====================================================================
-- Permite parametrizar que un requisito documental pida mas de un archivo
-- (ej: 2 fotos, 3 fotos de vivienda, 2 referencias personales).
-- Cada archivo cargado queda identificado por documento_indice dentro del
-- expediente y tipo documental.
-- =====================================================================

-- @up

ALTER TABLE documento_tipos
    ADD COLUMN numero_documentos SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER obligatorio;

ALTER TABLE expediente_documentos
    ADD COLUMN documento_indice SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER documento_tipo_id,
    ADD KEY ix_expdoc_tenant_tipo_indice (tenant_id, expediente_id, documento_tipo_id, documento_indice);

-- @down

ALTER TABLE expediente_documentos
    DROP KEY ix_expdoc_tenant_tipo_indice,
    DROP COLUMN documento_indice;

ALTER TABLE documento_tipos
    DROP COLUMN numero_documentos;
