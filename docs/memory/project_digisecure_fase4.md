---
name: DigiSecure - Fase 4 (Notificaciones, Admin Usuarios, Auditoría, Roles)
description: Alcance, componentes y decisiones no triviales de la Fase 4 — motor de notificaciones, CLI de vencimientos, administración de usuarios y visores de auditoría/roles.
type: project
---

**Alcance entregado en Fase 4:**

1. **Motor de notificaciones** (`src/Notification/`)
   - Migración `004_notificaciones.sql` con dos tablas:
     - `notificaciones` (cola con `dedupe_key UNIQUE(tenant_id, dedupe_key)`, estados PENDIENTE/ENVIADA/FALLIDA/CANCELADA, intentos/max_intentos, scheduled_at, payload_json)
     - `notificaciones_log` (BIGINT id, log inmutable de cada intento, OK/ERROR, duracion_ms)
   - `EncolarNotificacionService` es el **único punto de entrada** para insertar en cola — valida + dedupe + audita `notificacion.encolada`.
   - `DespacharNotificacionesService` consume la cola: `claimPending` → incrementa intentos → invoca canal → `markSent`/`markFailed` (escala a FALLIDA cuando intentos ≥ max_intentos) → audita.
   - Canales registrados como mapa `canal => NotificationChannel` inyectado al servicio (extensible para SMS/WhatsApp/Push después).

2. **Hook de vencimientos** — `VerificarVencimientosService` recibe ahora un `?EncolarNotificacionService $encolar = null` (opcional, retrocompatible). Encola notificaciones con dedupe_key:
   - `doc_vencido:{id}:{Y-m-d}` para documentos recién marcados VENCIDO
   - `doc_por_vencer:{id}:{Y-m-d}` para documentos próximos a vencer (prioridad ALTA si dias ≤ 7)
   - El dedupe garantiza idempotencia diaria — el cron puede correr múltiples veces sin duplicar.

3. **CLI cron** — `bin/notify-expirations.php`
   - Comando recomendado: `php bin/notify-expirations.php --dias=15 --batch=50`
   - Flags: `--dias=N` (ventana de alerta), `--tenant=ID` (limita a un tenant), `--batch=N` (tamaño de lote para despachar), `--dry-run`
   - Guarda PHP_SAPI === 'cli'.
   - Itera tenants `WHERE estado='activo'` y para CADA tenant instancia un `TenantContext` fresco + sus repos/servicios. Esto es obligatorio porque la invariante de `TenantContext` prohíbe hacer reset del tenant_id mid-request (ver `project_decisions_arquitectura.md`).

4. **Administración de usuarios** (`src/User/`)
   - `AdministrarUsuariosService`: `crear` / `actualizar` / `cambiarEstado` / `resetearPassword`. Self-block prohibido. `resetearPassword` fuerza `debe_cambiar_password=1`.
   - `ensureRoleAccessible` valida `tenant_id=current OR tenant_id IS NULL` antes de asignar rol.
   - Vistas: `templates/user/{index,create,edit}.php` con paginación, filtros y separación visual de la sección "Resetear contraseña" en edit.

5. **Visor de auditoría** (`src/Security/Audit/`)
   - `AuditoriaRepository` **NO extiende `BaseRepository`** — la tabla `auditoria` usa `id BIGINT` (no ULID CHAR(26)) y la escritura se hace siempre vía `AuditLogger`. Hace filtro tenant manual.
   - Filtros: evento (datalist con `distinctEventos`), nivel, usuario_id, entidad_tipo, desde, hasta. Lectura desencripta IP con `INET6_NTOA(a.ip)`.

6. **Visor de roles** (`src/Security/Rbac/`) — read-only.
   - Listado: subqueries de `permisos_count` y `usuarios_count` por rol.
   - Detalle: muestra catálogo COMPLETO de permisos agrupado por recurso y marca los asignados (vs. mostrar solo los asignados) para que se vea el alcance del catálogo.
   - La gestión avanzada (crear/editar roles del tenant) queda explícitamente para fase posterior — los templates lo dicen al usuario en un alert.

**Decisiones no obvias / gotchas:**

- **PHPMailer está declarado en `composer.json` pero NO instalado en `vendor/`** (vendor solo tiene autoload y composer/). `EmailChannel` está escrito con doble camino: si `class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')` usa SMTP autenticado, si no, usa `mail()` de PHP con headers MIME multipart/alternative manuales y subject UTF-8 codificado en base64. Esto permite arrancar el sistema antes de `composer install` y mantener el mismo contrato. Si el usuario corre composer install, automáticamente sube de tier sin cambiar código.
- **Canales como mapa inyectado**: `DespacharNotificacionesService` recibe `array $channels` indexado por valor del enum `CanalNotificacion`. Para añadir SMS/WhatsApp basta con implementar `NotificationChannel` y registrarlo en `Bootstrap.php`.
- **`MenuBuilder` ya tenía las rutas `/usuarios`, `/auditoria`, `/roles`** apuntando a controladores que no existían — la Fase 4 las hizo funcionales sin tocar el menú.
- **Healthcheck `phase`** subió a `'4.0'` en Bootstrap.

**Why:** El sistema necesita cerrar el ciclo operativo (admisibilidad → vencimientos → notificación → renovación) y dar a los administradores de tenant las herramientas mínimas para gestionar usuarios y revisar la trazabilidad sin depender de soporte. La Fase 4 también establece la columna vertebral del motor de notificaciones que la Fase 5 (WhatsApp/SMS) extenderá.

**How to apply:**
- Para programar el cron: tarea de WAMP/Windows o crontab Linux que ejecute `php bin/notify-expirations.php --dias=15 --batch=50` cada hora o cada día (idempotente por el dedupe diario).
- Antes de añadir un canal nuevo: implementar `NotificationChannel` y registrarlo en `Bootstrap.php` dentro del array que se pasa a `DespacharNotificacionesService`. NO inventar otra vía de envío.
- Para encolar manualmente desde otro módulo: inyectar `EncolarNotificacionService` (NO escribir directo en `notificaciones`). Esto garantiza dedupe + auditoría consistentes.
- Si se va a escribir en `auditoria`: usar `AuditLogger`, NO `AuditoriaRepository` (que es solo lectura).
