# Analisis de cobertura Excel GEMESEG

Fuente revisada: `PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx`

Este analisis valida si DigiSecure puede almacenar la informacion del archivo sin copiar datos personales al repositorio. La lectura se hizo sobre estructura de hojas, encabezados y volumen de datos, no sobre una publicacion de cedulas, nombres ni contactos.

## Resumen del archivo

| Hoja | Rango | Filas estimadas | Uso |
| --- | --- | ---: | --- |
| GUARDIAS GEMESEG | A1:BV98 | 93 | Guardias activos, datos personales, laborales, documentos, cursos y vacunas |
| SALIDAS | A1:BU161 | 153 | Personal desvinculado y fecha de salida |
| DOCUMENTACION ACTUALIZADA | A1:AH74 | 73 | Estado de documentos de expediente |
| DOCUMENTOS FALTANTES | A1:H112 | 78 | Control de documentos pendientes |
| CURSOS | A1:N74 | 67 | Cursos y credenciales principales |

## Cobertura antes de la migracion 019

| Grupo del Excel | Estado | Donde se almacenaba |
| --- | --- | --- |
| Apellidos, nombres y cedula | Cubierto | `fichas`, con cedula cifrada y hash |
| Fecha de nacimiento, celular, email, direccion, estado civil y tipo de sangre | Cubierto parcial | `fichas` |
| Hijos/cargas familiares detalladas | Cubierto parcial | `fichas_hijos_menores`; faltaba resumen numerico |
| Puesto, ubicacion, fecha ingreso y fecha salida | Cubierto parcial | `clientes`, `puestos`, `asignaciones` |
| Documentos principales | Cubierto parcial | `documento_tipos`, `expedientes`, `expediente_documentos` |
| Cursos base: Nivel 1, Nivel 2, reentrenamiento y supervisor | Cubierto parcial | `catalogo_cursos`, `fichas_cursos` |
| Edad y anos de antiguedad | No se debe almacenar | Se calculan desde fecha nacimiento / fecha ingreso |

## Brechas detectadas

| Campo o grupo | Problema detectado | Solucion propuesta |
| --- | --- | --- |
| Procedencia | No habia campo directo | `fichas.procedencia` |
| Cargas familiares como numero | No habia resumen directo | `fichas.cargas_familiares` |
| Talla uniforme y talla zapatos | No habia almacenamiento directo | `fichas.talla_uniforme`, `fichas.talla_zapatos` |
| Clase de trabajo | No estaba en asignacion laboral | `asignaciones.clase_trabajo` |
| Remuneracion | No estaba en asignacion laboral | `asignaciones.remuneracion`, `asignaciones.remuneracion_moneda` |
| Contratos como referencia operativa | Solo existia documento de contrato firmado | `asignaciones.contrato_referencia` |
| Vacunas HB, DT, SR, FA, Influenza, COVID | No habia modelo estructurado | `catalogo_vacunas`, `fichas_vacunas` |
| Cursos adicionales del Excel | Catalogo incompleto | Nuevas semillas en `catalogo_cursos` |
| Hoja ingreso, visita domiciliaria, SICOSEP, certificado Fuerzas Armadas | No todos existian como tipo documental exacto | Nuevos `documento_tipos` |
| Filas originales del Excel | Riesgo de dejar datos sensibles en claro | `importacion_excel_registros` con JSON cifrado y hash |

## Migracion agregada

Archivo: `migrations/019_gemeseg_excel_coverage.sql`

La migracion agrega:

- Campos complementarios en `fichas`.
- Campos laborales complementarios en `asignaciones`.
- Catalogo y relacion para vacunas.
- Tabla de staging cifrado para importaciones de Excel.
- Cursos adicionales detectados en la hoja `GUARDIAS GEMESEG`.
- Tipos documentales adicionales detectados en `DOCUMENTACION ACTUALIZADA`.

## Mapeo recomendado para importar

| Excel | Destino recomendado |
| --- | --- |
| GUARDIAS GEMESEG | `fichas`, `asignaciones`, `fichas_cursos`, `fichas_vacunas`, `expediente_documentos` |
| SALIDAS | `asignaciones.fecha_fin`, `asignaciones.estado = finalizada`, `asignaciones.motivo_fin` |
| DOCUMENTACION ACTUALIZADA | `expediente_documentos.estado`, `fecha_emision_documento`, `fecha_vencimiento`, observaciones |
| DOCUMENTOS FALTANTES | Reporte derivado desde `expediente_documentos`, no tabla nueva |
| CURSOS | `fichas_cursos` y documentos/credenciales asociadas |

## Criterio LOPDP

- No guardar edad si puede calcularse con fecha de nacimiento.
- No guardar anos de antiguedad si puede calcularse con fecha de ingreso.
- No versionar el Excel ni exportaciones con cedulas, telefonos, correos o direcciones.
- Si se conserva fila original para auditoria, debe quedar cifrada en `importacion_excel_registros.raw_json_cifrado`.
- Para busquedas por cedula usar `cedula_hash`; la cedula en claro solo debe existir en memoria durante la operacion autorizada.

## Conclusion

Antes de esta revision, el sistema cubria la ficha principal, expediente documental, cursos base y asignaciones, pero no almacenaba toda la informacion del Excel GEMESEG de forma estructurada.

Con la migracion 019, el modelo queda preparado para almacenar la informacion relevante del archivo. Lo pendiente es construir o ejecutar el importador, mapear valores reales a catalogos existentes y validar duplicados por `tenant_id` + `cedula_hash` antes de cargar datos en produccion.

## Importador agregado

Se agrego el comando `bin/import-gemeseg-excel.php` para analizar el archivo y guardar staging cifrado sin publicar datos personales en claro.

Guia operativa: `docs/IMPORTACION_GEMESEG_EXCEL.md`
