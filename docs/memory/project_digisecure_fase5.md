---
name: DigiSecure - Fase 5 (Notificaciones multicanal operativas)
description: Cierre operativo del flujo de vencimientos con destinatarios duales (guardia + admin), encolado por Email/WhatsApp y despacho condicional por configuracion.
type: project
---

**Alcance entregado en esta iteracion:**

1. **Destinatarios duales en vencimientos** (`src/Expediente/Application/VerificarVencimientosService.php`)
   - Ahora el caso de uso encola notificaciones para **guardia** y **administracion del tenant**.
   - El servicio construye destinatarios desde los datos del documento/ficha/tenant.

2. **Canales por destinatario (Email + WhatsApp)**
   - Para cada destinatario se encola por `EMAIL` si existe email valido.
   - Se encola por `WHATSAPP` solo si `whatsappEnabled=true` y hay telefono valido.
   - Se mantiene prioridad alta para vencidos y alta/normal para por vencer segun dias restantes.

3. **Dedupe por ruta de entrega**
   - La `dedupe_key` ya no es solo por documento y dia.
   - Ahora incluye: evento-dia + scope (`guardia`/`admin`) + canal + hash de destino.
   - Resultado: idempotencia diaria sin colisiones entre canales/destinatarios.

4. **Datos de contacto en queries de vencimientos** (`src/Expediente/Infrastructure/ExpedienteRepository.php`)
   - `getDocumentosVencidos()` y `getDocumentosPorVencer()` ahora traen:
     - contacto guardia: `guardia_email`, `guardia_telefono`
     - contacto admin tenant: `admin_email`, `admin_telefono`
     - `tenant_nombre` para trazabilidad y cuerpo de notificacion

5. **Validaciones fuertes al encolar** (`src/Notification/Application/EncolarNotificacionService.php`)
   - Se valida canal y prioridad contra enums.
   - Se exige cuerpo (`cuerpo_texto` o `cuerpo_html`).
   - Se valida destino segun canal:
     - EMAIL -> `destinatario_email`
     - SMS/WHATSAPP -> `destinatario_telefono`
     - PUSH -> `destinatario_usuario_id`

6. **Cron con WhatsApp condicional** (`bin/notify-expirations.php`)
   - Se agrego carga de config WhatsApp (`WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_TIMEOUT_SECONDS`).
   - Si WhatsApp esta configurado:
     - se habilita encolado WhatsApp en `VerificarVencimientosService`
     - se registra `WhatsAppChannel` en el despachador.
   - Si no esta configurado, el cron opera solo con EMAIL sin generar cola WhatsApp.

7. **Config de app actualizada**
   - `src/Kernel/Bootstrap.php` ahora expone bloque `whatsapp` en `Config`.
   - El `VerificarVencimientosService` inyectado en DI activa WhatsApp solo cuando hay credenciales.
   - `.env.example` incorpora `WHATSAPP_TIMEOUT_SECONDS=15`.

**Decisiones no obvias / gotchas:**

- Se evita encolar WhatsApp cuando no hay credenciales para no contaminar la cola con errores evitables.
- El dedupe por hash de destino evita exponer PII en `dedupe_key` y mantiene idempotencia estable.
- El despacho de WhatsApp sigue usando `WhatsAppChannel` (Meta Cloud API) sin librerias externas.

**How to apply:**

- Configurar en `.env`:
  - `WHATSAPP_PHONE_NUMBER_ID`
  - `WHATSAPP_ACCESS_TOKEN`
  - opcional `WHATSAPP_TIMEOUT_SECONDS`
- Ejecutar cron:
  - `php bin/notify-expirations.php --dias=15 --batch=50`
- Verificar en BD:
  - `notificaciones` (canales EMAIL/WHATSAPP)
  - `notificaciones_log` (resultado por intento/canal)
