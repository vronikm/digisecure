# Importacion GEMESEG Excel

Opcion web agregada:

```text
Control > Importaciones > Excel GEMESEG
```

Ruta directa:

```text
/importaciones/gemeseg
```

Comando CLI agregado: `bin/import-gemeseg-excel.php`

El importador esta pensado para produccion y es seguro por defecto:

- Sin `--apply` no escribe en la base.
- Con `--apply` guarda las filas del Excel en `importacion_excel_registros` cifradas con `ENCRYPTION_KEY`.
- No imprime nombres, cedulas, telefonos, correos ni direcciones.
- No crea fichas automaticamente todavia; primero deja staging auditable para revisar duplicados y reglas de mapeo.

## Requisitos

1. Tener `vendor/` instalado o desplegado.
2. Tener `.env` configurado.
3. Tener `ENCRYPTION_KEY` valido en produccion.
4. Tener aplicadas las migraciones 019 y 020:

```bash
php bin/repair-migration-checksums.php
php bin/repair-migration-checksums.php --apply
php bin/migrate.php migrate
```

5. Tener habilitada la extension PHP `zip`, necesaria para leer XLSX.
6. Para la opcion web, el usuario debe tener el permiso `importaciones.gemeseg`.

## Importar desde la pantalla web

1. Ingresa como superadmin o administrador del tenant.
2. Abre `Control > Importaciones > Excel GEMESEG`.
3. Sube el archivo `.xlsx` y presiona `Analizar Excel`.
4. Revisa el resumen por hoja, cedulas validas, errores y duplicados.
5. Marca la confirmacion y presiona `Guardar staging cifrado`.
6. En `Staging reciente`, marca `Confirmar` y presiona `Procesar` para convertir las filas pendientes en registros del sistema.

La pantalla guarda el Excel en `var/imports/gemeseg` solo como archivo temporal.
Al guardar staging, la copia temporal se elimina y las filas quedan cifradas en
`importacion_excel_registros`. La pantalla no muestra cedulas, telefonos,
correos ni direcciones completas.

El procesamiento es idempotente sobre el staging pendiente:

- Crea fichas en `BORRADOR` cuando la fila tiene cedula valida, nombres, apellidos y fecha de nacimiento.
- Actualiza solo campos vacios de fichas existentes.
- Registra cursos detectados en `fichas_cursos`.
- Registra vacunas detectadas en `fichas_vacunas`.
- Crea marcadores documentales en `expediente_documentos` sin archivo adjunto; la evidencia se sube despues desde el expediente.
- Marca cada fila de staging como `procesado`, `omitido` o `error`.

Interpretacion de estados:

- `pendiente`: fila lista para procesamiento.
- `procesado`: fila convertida o asociada a una ficha.
- `omitido`: no se pudo convertir sin inventar datos obligatorios; por ejemplo, una ficha nueva sin fecha de nacimiento.
- `error`: fila invalida o con cedula ausente/invalida desde el analisis.

Si el Excel se corrige despues de una primera carga, usa un nombre de fuente
nuevo para no chocar con la proteccion anti-duplicados por `fuente + hoja + fila`.

## Analizar sin escribir

```bash
php bin/import-gemeseg-excel.php --file="/ruta/PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx"
```

Resultado de referencia con el archivo revisado:

| Hoja | Filas | Cedulas | Validas | Invalidas | Duplicadas |
| --- | ---: | ---: | ---: | ---: | ---: |
| GUARDIAS GEMESEG | 92 | 92 | 92 | 0 | 1 |
| SALIDAS | 153 | 125 | 125 | 0 | 7 |
| DOCUMENTACION ACTUALIZADA | 73 | 73 | 73 | 0 | 0 |
| DOCUMENTOS FALTANTES | 78 | 72 | 72 | 0 | 37 |
| CURSOS | 66 | 66 | 65 | 1 | 0 |

Totales:

- 462 filas de datos.
- 428 filas con cedula.
- 427 cedulas validas.
- 1 cedula invalida.
- 34 filas sin cedula.
- 1 cedula corregida por posible cero inicial perdido en Excel.
- 45 duplicados por cedula dentro de sus hojas.

## Listar tenants

```bash
php bin/import-gemeseg-excel.php --list-tenants
```

Usa el `id` del tenant correcto para los siguientes comandos.

## Validar contra la base sin escribir

```bash
php bin/import-gemeseg-excel.php \
  --file="/ruta/PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx" \
  --tenant-id="TENANT_ID"
```

Esto valida:

- Que existe el tenant.
- Que la migracion 019 esta aplicada.
- Cuantas cedulas del Excel ya existen en `fichas`.
- Cuantas filas de esa fuente ya existen en staging.

## Guardar staging cifrado

```bash
php bin/import-gemeseg-excel.php \
  --file="/ruta/PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx" \
  --tenant-id="TENANT_ID" \
  --user-id="USUARIO_ID" \
  --source="PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx" \
  --apply
```

`--user-id` es opcional, pero recomendado para auditoria.

El comando usa la llave derivada por tenant y AAD:

```text
tenant_id|gemeseg_excel|source|hoja|fila
```

Eso evita que una fila cifrada de un tenant pueda reutilizarse validamente en otro contexto.

## Pruebas parciales

Procesar solo una hoja:

```bash
php bin/import-gemeseg-excel.php --file="/ruta/archivo.xlsx" --sheet="GUARDIAS GEMESEG"
```

Limitar filas por hoja:

```bash
php bin/import-gemeseg-excel.php --file="/ruta/archivo.xlsx" --limit=5
```

## Siguiente paso

Despues del procesamiento, el paso siguiente es una consolidacion operativa que:

- Relacione puesto/ubicacion con `clientes`, `puestos` y `asignaciones`.
- Permita adjuntar los archivos fisicos/digitales a los documentos detectados.
- Revise filas `omitido` o `error` para correccion manual.
