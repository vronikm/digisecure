---
name: DigiSecure - Expediente Documental del Guardia
description: Segundo agregado del dominio: índice documental con documentos pre-empleo + post-vinculación, requisitos básicos, validación externa, vigencias y recordatorios.
type: project
---

**Contexto:** Aparte de la Ficha (datos del guardia), DigiSecure debe gestionar un **Expediente Documental** dividido en dos bloques:
- **Bloque A — Documentos de admisibilidad (pre-empleo):** los 26 originales (ya sin la carpeta crema física). Validados antes de contratar.
- **Bloque B — Documentos de vinculación laboral (post-contratación):** generados/firmados después del ingreso del guardia a la empresa. **4 documentos:**
  - Contrato firmado y digitalizado
  - Aviso de entrada al IESS
  - Registro en SUT (Sistema Único de Trabajo - Ministerio de Trabajo Ecuador)
  - Acta de entrega-recepción de uniformes

Total inicial: **30 tipos de documento** (26 pre-empleo + 4 post-vinculación), todos configurables por tenant.

**Requisitos básicos (hard-rules de admisibilidad):**
1. Edad entre 20 y 45 años (calculada desde `fecha_nacimiento` vs hoy).
2. Estatura mínima: 1.65 m hombres, 1.50 m mujeres → **requiere campo `genero` en la ficha** (NO está en los 46 campos originales — gap detectado).
3. Instrucción mínima: Bachiller.
4. Experiencia en vigilancia/seguridad: ≥ 1 año (calculada desde `trabajos_anteriores`).

Estos requisitos deben validarse al intentar marcar la ficha como COMPLETA. Resultado: bloqueo + lista de incumplimientos.

**Documentos obligatorios (27):**
Son una colección 1:N por ficha. Cada documento tiene:
- Tipo (catálogo configurable por tenant — los 27 son los iniciales pero el tenant puede agregar/desactivar).
- Archivo (PDF para documentos, JPEG/PNG/WebP para fotos).
- Estado: PENDIENTE, EN_REVISION, ACEPTADO, RECHAZADO, VENCIDO.
- Observación del revisor.
- 2 capturas (screenshots) de la verificación en sitio web autorizado externo.
- URL del sitio externo donde se validó.
- Vigencia: algunos documentos vencen (ej: reentrenamiento "vigente 2 años", antecedentes penales, etc.) → fecha_vencimiento.
- Auditoría: quién subió, quién validó, cuándo.

**Bloque A — Documentos pre-empleo (26, sin la carpeta crema eliminada):**
1. Autorización tratamiento de datos personales (LOPDP) — CRÍTICO LEGAL, sin vencimiento (vinculado a versión del texto)
2. Hoja de vida (encuentraempleo.gob.ec)
3. Cédula de identidad vigente a color (PDF/JPG) — vencimiento = fecha caducidad cédula
4. Papeleta de votación a color — vence en cada proceso electoral
5. 2 fotografías tamaño carnet
6. Planilla de servicio básico — sin vencimiento estricto pero recomendable < 3 meses
7. Título de bachiller — sin vencimiento
8. Certificado Nivel 1 (120 horas) — sin vencimiento
9. Credenciales Nivel 1
10. Certificado Nivel 2
11. Credencial Nivel 2
12. Certificado reentrenamiento — VIGENCIA 2 AÑOS (caso explícito del usuario)
13. Diploma manejo de armas de fuego — vigencia variable
14. Certificados primeros auxilios y contra incendios (RIG-SICISEP) — vigencia variable
15. Exámenes salud física — vigencia ~1 año
16. Exámenes drogas y alcohol — vigencia ~1 año
17. Certificado Psicológico (MSP actual) — vigencia ~1 año
18. Tipificación sanguínea — sin vencimiento
19. Carnet vacunación MSP
20. Certificado AFIS (Policía Judicial Criminalística) — vigencia variable
21. Antecedentes penales (record actual) — vigencia ~6 meses
22. Certificado no violencia intrafamiliar — vigencia variable
23. Historial laboral IESS — sin vencimiento estricto, actualizable
24. 2 certificados de trabajo
25. 1 certificado de honorabilidad y conducta
26. 3 fotografías del domicilio (frente casa, calle, vista alejada)

**Bloque B — Documentos post-vinculación laboral (4):**
27. Contrato firmado y digitalizado — sin vencimiento (vigente mientras dure relación laboral)
28. Aviso de entrada al IESS — sin vencimiento (es comprobante puntual)
29. Registro en SUT (Ministerio de Trabajo) — sin vencimiento (comprobante puntual)
30. Acta entrega-recepción de uniformes — sin vencimiento (puede haber múltiples actas a lo largo del tiempo)

**Why:** Son requisitos legales/operativos del sector seguridad privada en Ecuador. Sin expediente completo y validado, una empresa no puede asignar al guardia a un puesto. La validación contra sitios externos es manual hoy (el revisor visita el sitio, toma capturas, marca aceptado/rechazado).

**How to apply:**
- Modelar como **agregado separado** `Expediente` (no embebido en Ficha) — tienen ciclos de vida distintos: la ficha se llena una vez, el expediente vive y se actualiza (renovaciones, vencimientos).
- `Expediente` y `Ficha` se relacionan 1:1 a través de `ficha_id`.
- Catálogo de tipos de documento configurable por tenant: tabla `documento_tipos` con flags `obligatorio`, `requiere_validacion_externa`, `requiere_vigencia`, `dias_vigencia`.
- Storage de archivos: cifrado en reposo (AES-256-GCM), nombres aleatorios (UUID), fuera del document root del web server, servir mediante endpoint autenticado.
- Validación de archivos: doble verificación (extensión + magic bytes con `finfo_file`), límite de tamaño por archivo y por expediente, rechazar PDFs con JS embebido.
- Job programado diario que marca documentos VENCIDOS y notifica al tenant.
- Dashboard del expediente: progreso visual (X de 30 aceptados), alertas de vencimiento próximo (30/15/7 días).
- **Vigencia diferenciada por tipo:** la tabla `documento_tipos` define `dias_vigencia` por cada tipo (puede ser NULL = sin vencimiento, o un número específico). El usuario puede sobreescribir el valor por documento individual cuando el certificado tiene una fecha de vencimiento explícita impresa en él.
- **Fecha objetivo de actualización (recordatorio personalizado):** cada documento puede tener una `fecha_objetivo_actualizacion` que el revisor define manualmente cuando rechaza o pide renovación, y el sistema envía recordatorios en cuenta regresiva hasta esa fecha (no es lo mismo que `fecha_vencimiento`, esto es una fecha de "esperamos que lo subas para tal día"). Cuando esa fecha se acerca o se pasa, escala la severidad del recordatorio.
- **Bloque B (post-vinculación)** se desbloquea solo cuando Bloque A está 100% ACEPTADO. No tiene sentido pedir contrato firmado si el guardia aún no es admisible.
- Subir un documento con vencimiento dispara automáticamente el cálculo de `fecha_vencimiento = fecha_emision_documento + dias_vigencia` (a menos que el revisor lo sobreescriba).
