-- =====================================================================
-- DigiSecure - Migracion 036: numero de archivos segun el nombre del
--                             tipo documental
-- =====================================================================
-- Tipos como "2 fotografias tamano carnet" o "2 certificados de trabajo"
-- pedian un solo archivo: el guardia subia una foto y el expediente daba
-- el requisito por cumplido. Donde el nombre declara una cantidad, se
-- ajusta numero_documentos a esa cantidad.
--
-- Se limita a 2..10 para no tocar los tipos de un solo archivo ni
-- interpretar mal un nombre que empiece por un numero grande. No pisa a
-- quien ya lo configuro bien (solo actualiza si difiere).
-- =====================================================================

-- @up

UPDATE documento_tipos
   SET numero_documentos = CAST(REGEXP_SUBSTR(nombre, '^[0-9]+') AS UNSIGNED),
       updated_at = UTC_TIMESTAMP(6)
 WHERE nombre REGEXP '^[0-9]+ '
   AND CAST(REGEXP_SUBSTR(nombre, '^[0-9]+') AS UNSIGNED) BETWEEN 2 AND 10
   AND numero_documentos <> CAST(REGEXP_SUBSTR(nombre, '^[0-9]+') AS UNSIGNED);

-- @down

-- No reversible: no se conserva el valor anterior de cada tipo. El numero
-- de archivos es configurable por empresa en Configuracion > Tipos de
-- documento.
