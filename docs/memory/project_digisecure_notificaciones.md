---
name: DigiSecure - Sistema de Notificaciones y Vencimientos
description: Requisito de notificaciones automáticas por email/WhatsApp para vencimientos de documentos, con foco en reentrenamiento (vigencia 2 años).
type: project
---

**Requisito confirmado por el usuario:**

El sistema debe enviar **notificaciones automáticas** ante vencimientos de documentos/certificaciones.

**Caso explícito mencionado: Reentrenamiento**
- Vigencia: 2 años desde `fecha_ultimo_reentrenamiento`.
- Recordatorio: al menos **15 días antes** del vencimiento.
- Canales: **email** y **WhatsApp**.

**Generalización del requisito:**
- El sistema necesita un **motor de notificaciones** desde Fase 0/1, no como agregado posterior.
- Aplicable a TODOS los documentos del expediente con `requiere_vigencia = true` (no solo reentrenamiento): antecedentes penales, certificado psicológico, exámenes de salud, etc.
- Configurable: 30/15/7 días antes (mi recomendación previa que el usuario aprobó).

**Why:** En el sector seguridad privada, operar con personal con certificaciones vencidas es ilegal. La empresa contratante (tenant) necesita anticiparse para no perder al guardia ni incurrir en sanciones del ente regulador.

**How to apply:**
- Modelar como agregado separado: `Notificaciones` (canales, plantillas, cola, log de envíos).
- Job programado diario (cron) que escanea `expediente_documentos.fecha_vencimiento` y dispara notificaciones según política.
- WhatsApp requiere proveedor externo (Twilio, Meta Cloud API, o WhatsApp Business API) — **decisión pendiente con el usuario**.
- Email vía SMTP nativo de PHP (PHPMailer es la única dependencia razonable, librería madura y mínima — verificar con usuario antes de añadirla).
- Plantillas de notificación versionadas y configurables por tenant (asunto, cuerpo, idioma).
- Tabla `notificaciones_log`: registro de cada envío (canal, destinatario, contenido enviado, status, timestamp, costo si aplica).
- Idempotencia: una misma notificación de vencimiento no debe enviarse dos veces el mismo día.
- El destinatario puede ser: el guardia (si tiene email/teléfono) o el administrador del tenant. **Decisión de UX pendiente.**
