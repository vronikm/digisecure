# DigiSecure — Changelog

Estado actual: **Phase 18.1** · 36 migraciones · 122/122 tests verdes.

Stack: PHP 8.3 / MySQL 8 / Chart.js 4.4 + sistema de diseño propio `ds-system` (iconos Bootstrap; la hoja CSS de Bootstrap ya no se carga).

---

## QA integral — Correcciones de la prueba end to end (2026-09-22)

Una prueba de todo el flujo (registro de aspirante → contratación → asignación, más superadmin) dejó 8 fallos y una lista de mejoras de interfaz. Se corrigen todos.

**Fallos**

1. **El alta de empresa nunca llegaba.** `RegistrarTenantService` encolaba el correo de activación, pero el despachador (`bin/notify-expirations.php`) solo atiende tenants `activo`: el tenant recién registrado queda `pendiente` y su correo no salía jamás. Ahora se envía en el momento con `Mailer` y el resultado (`correo_activacion_enviado`, `correo_error`) queda en `registros_empresa`.
2. **Los cupos del plan contaban de más.** Nuevo `Subscription\Infrastructure\CriteriosCupo`, único lugar que define qué consume cupo: usuarios activos **con rol operativo** (las cuentas de aspirante no cuentan) y **personas** con ficha vigente (por `cedula_hash`, sin anuladas, bajas ni aspirantes sin contratar). Lo usan `CalcularUsoService` y las vistas del superadmin. En GEMESEG: 37/5 usuarios → 2/5; 12 fichas → 0 guardias.
3. **Requisitos básicos con Y en lugar de O.** `CompletarFichaService` exigía bachillerato **y** trabajo anterior; la decisión vinculante #10 pide bachillerato **o** un año de experiencia. El formulario ya no llama "Opcional" a los trabajos: explica el requisito.
4. **Suscripción vencida sin aviso.** Aviso fijo en el layout para quien administra la empresa, alerta "Suscripciones vencidas" para el superadmin y marca `· vencido` en el listado de empresas. No bloquea el servicio: la decisión de suspender sigue siendo comercial.
5. **`/dashboard` sin control de acceso.** Un empleado sin permisos veía los KPIs de la empresa. Ahora los KPIs exigen `guardias.ver`, `expedientes.ver` o `reportes.ver`; el resto ve una bienvenida.
6. **Un usuario torpe bloqueaba la oficina entera.** El rate-limit contaba `email OR ip` contra el mismo umbral (5/15 min): 5 fallos desde una IP dejaban fuera a todos los usuarios detrás de ese NAT. Ahora son dos umbrales: 5 por cuenta y 30 por IP.
7. **Enlaces con token en los logs.** `Mailer` y `VerificationMailer` escribían el cuerpo del correo (con el enlace de recuperación o verificación) en `var/logs` y en `error_log`, también en producción. Nuevo `RegistroEnvioFallido`: fuera de producción guarda el contenido para poder seguir el flujo sin MTA; en producción solo metadata con el destinatario enmascarado.
8. **La hoja de Bootstrap estaba bloqueada.** Su hash SRI era incorrecto, así que el navegador la descartaba desde abril: rejilla, barras de progreso, badges, pestañas y `d-none` no tenían estilo. Se retira el enlace (layout y páginas de error) y `ds-system.css` incorpora el subconjunto que las plantillas usan de verdad (rejilla de 12 columnas, tarjetas, formularios, botones, tablas, alertas, badges, progreso, pestañas y utilidades). Sin dependencia externa de CSS.

**Interfaz**

- Registro público: confirmación de correo (un correo mal escrito dejaba al aspirante sin poder verificarse) y el botón ambiguo "Registro" pasa a "Postúlate como guardia".
- Ficha: acceso al expediente desde cualquier paso, los contadores "0/0" de fases sin obligatorios se ocultan y la barra de progreso aclara que solo mide campos del formulario.
- Expediente: títulos sin el "·" suelto, numeración correlativa (antes saltaba números al usar el campo `orden`), total real de documentos (decía 26 con 25) y el estado por archivo solo cuando el tipo admite varios.
- Documentos: se muestra el nombre de quien subió y revisó, en vez del ULID; "Tamaño" y "Número" con tilde; se rechaza un vencimiento anterior o igual a la emisión (servidor y formulario).
- Privacidad: los listados enmascaran la cédula (`******1234`); el detalle la muestra completa. Lo que no se puede descifrar dice "No disponible" en vez de `[cifrado]`.
- Guardias: distintivo Aspirante / Empleado / De baja en el listado.
- Usuarios: la contraseña inicial ya no viaja visible en el HTML (campo `password` con botón de mostrar).
- Empresa: provincia y ciudad como listas encadenadas (mismo script que clientes).
- Asignaciones: si no hay fichas COMPLETA o no hay puestos, el botón queda deshabilitado con la explicación, en lugar de redirigir con un mensaje.
- Móvil: KPIs en dos columnas compactas, tarjetas de filtros plegadas, el menú lateral ya no queda tapado por su pie y el pie muestra empresa y rol en vez de "N permisos activos".

**Segunda pasada (lo que había quedado a decisión)**

- **Guardar la ficha desde cualquier paso.** El botón ya no aparece solo en el último paso: al guardar, el formulario lleva al paso del primer campo incompleto (el manejador de submit ya lo hacía, pero el botón estaba oculto). Además, las dos referencias personales entran en el porcentaje de avance — antes marcaba 100% sin poder guardar — y los nombres de los pasos dejan de recortarse ("Historia…") ahora que la rejilla comparte ancho con el resumen.
- **Migración 036:** los tipos documentales cuyo nombre declara una cantidad ("2 fotografías tamaño carnet", "2 certificados de trabajo") pedían un solo archivo, así que el expediente daba el requisito por cumplido con una foto. Se ajusta `numero_documentos` a la cantidad del nombre (2..10), sin pisar lo que ya estaba bien configurado. Efecto: los expedientes con una sola foto vuelven a contarse como incompletos, que es el estado real.
- **Nuevo `bin/diagnostico-cifrado.php`:** de solo lectura, informa cuántos valores no se descifran con la `ENCRYPTION_KEY` actual (nunca imprime valores ni claves) e indica el procedimiento con `recifrar-datos.php`. En la base local: 26 valores (6 cédulas de ficha, 12 cuentas bancarias y 8 cédulas de familiares) siguen cifrados con la clave anterior.

**Migración 035** concede `suscripcion.ver` a los roles con `tenants.ver` (la 007 lo hizo antes de que `admin_tenant` tuviera ese permiso, así que el administrador de la empresa no podía ver su propio plan). `suscripcion.gestionar` sigue siendo del administrador de la plataforma.

---

## Operación — Cambio de clave de cifrado (2026-09-16)

- **Nuevo `bin/recifrar-datos.php --dry-run|--apply`:** pasa a la `ENCRYPTION_KEY` actual todo lo cifrado con `ENCRYPTION_KEY_ANTERIOR`.
  - **Columnas:** cédula y cuenta bancaria de fichas, cédula de familiares, cédula y MFA de usuarios, staging GEMESEG (clave por tenant + AAD).
  - Recalcula `cedula_hash` al recifrar.
  - Lo que ya descifra con la clave actual no se toca; lo que no descifra con ninguna se reporta.
- Sirve para rotar la clave o para una base copiada entre entornos con claves distintas. Procedimiento en `docs/PRODUCTION_DEPLOY.md`.

---

## Reclutamiento — Corrección de datos del aspirante (2026-09-16)

Un postulante que escribe mal su correo nunca recibe el enlace de verificación y queda bloqueado en estado `nuevo`. Reclutamiento ahora puede corregir sus datos (rectificación LOPDP).

- **Rutas:** `GET|POST /reclutamiento/{id}/editar` (permiso `aspirantes.gestionar`). Botón **Editar** en el listado y **Editar datos** en el detalle, solo en estados `nuevo`, `aspirante`, `en_revision` y `contactado`.
- **`ActualizarAspiranteService`:**
  - Aplica las mismas reglas que el registro público, extraídas a `ValidadorDatosAspirante`.
  - El correo nuevo se confirma dos veces y no puede pertenecer a otro aspirante ni a otro usuario.
  - Sin verificar: emite un token nuevo (48 h) que invalida el anterior y se envía el enlace al correo corregido. También se puede reenviar sin cambiar datos.
  - Con usuario: el correo de inicio de sesión, los nombres y el teléfono se actualizan en la misma transacción.
  - El documento queda bloqueado si el aspirante ya creó su ficha.
- **Trazabilidad:**
  - Auditoría `aspirante.datos_actualizados`, con correo anterior y nuevo. Queda en nivel `warning` si cambia el login de una cuenta existente.
  - Evento `datos_actualizados` en `aspirante_eventos`.
  - **Migración 034:** amplía el ENUM de eventos.
- **UI:**
  - Formulario con validación en vivo de la confirmación de correo y avisos contextuales.
  - Nuevo componente `.ds-callout` en `ds-system.css`, que reemplaza en el detalle las alertas `login-*` (no tenían estilo dentro del layout interno).
- **Tests:** `ActualizarAspiranteServiceTest` (20 casos).

---

## Seguridad — Blind index HMAC para cédulas (2026-09-16)

`cedula_hash` era `sha256(cedula)` sin clave: con ~1.5e8 cédulas válidas, un dump de la BD revelaba todas por fuerza bruta aunque `cedula_cifrada` use AES-256-GCM (riesgo LOPDP).

- **Nuevo `Security\Crypto\BlindIndex`:** HMAC-SHA256 con subclave HKDF de `ENCRYPTION_KEY` (`Encryption::blindIndexer()`), normaliza a dígitos y separa dominio (`cedula:`). Índice global a propósito: los flujos de aspirantes mueven fichas entre tenants.
- **Se elimina `Cedula::hash()`.** Usan el índice: `CrearFichaService`, `ActualizarFichaService`, `NormalizarFamiliaresFicha`, `FichaController` (búsqueda y prefill), `LopdpController` (búsqueda de titular), importación GEMESEG (web y CLI). El procesador de staging recalcula el índice desde la cédula descifrada.
- **Nuevo `bin/rehash-cedulas.php --dry-run|--apply`:** recalcula `fichas`, `fichas_familiares`, `usuarios` e `importacion_excel_registros` desde el dato cifrado; idempotente; reporta filas no descifrables sin tocarlas.
- `bin/repair-encrypted-cedulas.php` acepta el hash vigente o el legado y deja el índice HMAC al reparar.
- **LOPDP:** `bin/anonimizar-retencion.php` ahora también borra `fichas_familiares` (cédula del cónyuge y datos de padres) al vencer la retención.
- Tests: `BlindIndexTest`, `NormalizarFamiliaresFichaTest`.

**Despliegue:** ejecutar `php bin/rehash-cedulas.php --apply` inmediatamente después de publicar el código (ver `docs/PRODUCTION_DEPLOY.md`).

---

## Mantenimiento — Integridad de migraciones y tests (2026-09-16)

- **Checksums estables entre entornos:** `Migration::fromFile()` normaliza CRLF→LF antes de calcular el sha256, y `.gitattributes` fuerza `eol=lf` en `*.sql`. Git en Windows (`core.autocrlf=true`) ya no produce `CHECKSUM_MISMATCH` falsos.
- **Collation `utf8mb4_unicode_ci` aplicable con FKs:** 017, 018 y 019 desactivan `FOREIGN_KEY_CHECKS` solo durante la conversión (evita el error 3780). La 018 convierte además, si existen, las tablas de aspirantes y catálogos creadas fuera de orden (evita el 1267 en 027/031).
- **026** declara `utf8mb4_unicode_ci` (antes `0900_ai_ci`, no soportado en MariaDB).
- **025** ya no falla en instalación limpia sin tenants (FK `fk_rp_role`).
- **Nueva 033:** normaliza las tablas posteriores a la 018 y la collation por defecto de la base.
- **Tests:** se corrigieron 17 tests desfasados del código (`AuthenticatedUser::$cargo`, `tenantGemeseg()`, `tipoId` plano en rutas de expediente) y se agregó `MigrationTest`. La caché de PHPUnit deja de versionarse.

**Al desplegar en un entorno donde 017/018/019/025/026 ya estaban aplicadas:** ejecutar `php bin/repair-migration-checksums.php` (revisar) y luego `--apply`, antes de `php bin/migrate.php migrate`.

---

## Fase 16 — Módulo Clientes + Sistema de diseño consolidado (2026-04-17 → 2026-05-06)

### Nueva funcionalidad: Módulo Clientes

Bloque base para los siguientes módulos operativos (puestos, turnos, asignaciones).

- **Migración:** `010_clientes.sql`
  - Tabla `clientes` con identidad (RUC/cédula), contacto, domicilio, sector, estado (activo/inactivo/archivado), audit fields (`archivado_at`, `archivado_por`).
  - Índices: `(tenant_id, estado)`, `(tenant_id, razon_social)`, **UNIQUE `(tenant_id, documento)`**.
  - 4 permisos sembrados: `clientes.ver|crear|editar|archivar` repartidos por rol.
- **Domain:** [`ValidadorRuc::esCedulaValida()`](src/Tenant/Domain/ValidadorRuc.php) — método nuevo para validar cédula ecuatoriana (10 dígitos, módulo 10 Registro Civil).
- **Capas:**
  - [`ClienteRepository`](src/Cliente/Infrastructure/ClienteRepository.php) extiende `BaseRepository`.
  - [`GestionarClientesService`](src/Cliente/Application/GestionarClientesService.php) con `crear/actualizar/cambiarEstado` + auditoría (`cliente.creado|actualizado|archivado|estado_cambiado`).
  - [`ClienteController`](src/Cliente/Controller/ClienteController.php) con flash messages.
- **Vistas:** `templates/clientes/{index,create,edit,show}.php`.
- **Rutas:** `/clientes/*` (7 endpoints).
- **Menú:** Sección "Operación" inicia con "Clientes" antes de "Guardias".

### Sistema de diseño visual extraído a archivos externos

- **`public/assets/css/ds-system.css`** (~33 KB) — sistema completo del shell autenticado con tokens, componentes `ds-*` documentados en 9 secciones.
- **`public/assets/css/ds-auth.css`** (~16 KB) — pantallas públicas standalone (login, registro, forgot/reset, gracias/activado/fallida).
- Layout `templates/layout/app.php` redujo de 1188 → ~465 líneas.
- Componentes establecidos: `.ds-page-header`, `.ds-card` con `.ds-card-header.tone-*`, `.ds-table-card`, `.ds-table` con `.ds-cell-*`, `.ds-badge` con tonos, `.ds-kpi-card`, `.ds-empty-state`, `.ds-filter-bar`, `.ds-form-field`, `.ds-btn` con variantes, `.ds-toast`, `.ds-bell-menu`, `.ds-progress`, `.ds-chip`.

### Migración masiva de templates al sistema `ds-*`

Más de 40 templates migrados (cards, tablas, badges, kpis, formularios, empty states, paginación):

**Pantallas públicas:** login, registro/formulario, registro/gracias, registro/activado, registro/activacion-fallida, auth/forgot-password, auth/reset-password.

**Errores:** errors/404, errors/403, errors/500.

**Listados:** user/index, ficha/index, expediente/index, roles/index, notificaciones/index, auditoria/index, superadmin/tenants, documento-tipos/index, lopdp/solicitudes/index, lopdp/consentimientos/index, superadmin/registros.

**Detalle/show:** ficha/show, user/profile, tenant/show, roles/show, notificaciones/show, expediente/dashboard, expediente/documento, lopdp/solicitudes/show, lopdp/index, suscripcion/show, superadmin/dashboard, superadmin/tenant-detalle, clientes/show.

**Forms:** user/create, user/edit, user/change-password, tenant/edit, roles/create, roles/edit, documento-tipos/edit, suscripcion/nuevo-ciclo, lopdp/solicitudes/create, expediente/upload, clientes/create, clientes/edit.

### Adopción de FlashBag end-to-end

Todos los controllers principales ahora emiten toasts en éxito/error tras redirect:

- UserController, SuperadminController (Fase 15)
- FichaController, RoleController, TenantController, SuscripcionController (Fase 16)
- LopdpController, DocumentoTipoController, NotificacionController, ExpedienteController, ClienteController (Fase 16)

Patrón consolidado:
```php
try {
    $this->service->hacerAlgo(...);
    $this->flash->success('Acción completada.');
} catch (DomainException $e) {
    $this->flash->error($e->getMessage(), 'No se pudo completar');
}
return Response::redirect('/destino');
```

### Pendientes diferidos en esta fase

- `templates/ficha/create.php` y `templates/ficha/edit.php` — stepper JS complejo de 4 pasos (960 + 510 líneas), no migrados para evitar romper navegación.
- `templates/dashboard/index.php`, `reporting/kpis.php`, `reporting/exportar.php` — funcionan con CSS propio, charts complejos.

---

## Fase 15 — Mensajes flash + toasts + páginas de error

- **`FlashBag`** (`src/Kernel/Http/FlashBag.php`) con `success/error/warning/info/pull` sobre `SessionManager`.
- **`FlashMiddleware`** extrae los flashes en GETs y los publica al layout vía `ViewRenderer::share('flashes', ...)`.
- **`ViewRenderer::share/shared`** para datos transversales sin tocar cada controller.
- Toasts custom `.ds-toast` arriba-derecha con animación + auto-dismiss 5.5s.
- Páginas de error 404/403/500 rediseñadas con identidad propia (gradient, marca, código, CTAs).
- Adopción inicial en UserController y SuperadminController.

---

## Fase 14 — Centro de alertas en topbar

- **`AlertasCentroService`** (`src/Notification/Application/`) agrega items accionables del usuario actual.
- Para tenant: documentos vencidos/por vencer, fichas borrador, solicitudes LOPDP pendientes, suscripción por vencer.
- Para superadmin: tenants pendientes/suspendidos/venciendo, registros fallidos.
- **`AlertasLayoutMiddleware`** publica el bundle al layout.
- Campana en topbar con badge de conteo + dropdown categorizado por severidad (`crítica|alta|media|info`).

---

## Fase 13 — Panel Superadmin

- Migración `009_superadmin_panel.sql` con permisos `superadmin.*`.
- Dashboard global con KPIs, charts, registros recientes y acciones rápidas.
- CRUD de tenants global (estado, plan), log de registros públicos.
- Templates: `templates/superadmin/{dashboard,tenants,tenant-detalle,registros}.php`.

---

## Fase 12 — Activación de cuenta

- Token de activación SHA256 con expiración 48h.
- Email transaccional con enlace `/activar/{token}`.
- Flujos: `gracias.php` (post-registro) → email → `activado.php` o `activacion-fallida.php`.

---

## Fase 11 — Auto-registro público

- Migración `008_registro_publico.sql` con tabla `registros_empresa` (audit trail).
- Formulario público en `/registro` con planes + datos empresa + admin principal.
- Trial gratis 30 días automático al registrar.
- Validación de RUC con algoritmo SRI (módulo 10/11) en `ValidadorRuc`.
- Rate limiting: 5 creaciones/hora por IP.

---

## Fases 1-10 — Construcción del core

| Fase | Migración | Módulo |
|------|-----------|--------|
| 1-2  | `001_initial_security_core.sql`, `002_fichas_guardia.sql`, `003_expediente_documental.sql` | Seguridad base, Fichas, Expediente |
| 3    | (en `001`)                                                                              | Reportes/KPIs                      |
| 4    | (en `001`)                                                                              | Usuarios + Auditoría + Roles       |
| 5    | `004_notificaciones.sql`                                                                | Notificaciones (Email/WhatsApp/SMS) |
| 6    | `005_lopdp_arcop.sql`                                                                   | LOPDP + PDFs (lealtad, huellas)    |
| 7    | (en `001`)                                                                              | Empresa/Tenant                     |
| 8-9  | `006_permisos_fase8_9.sql`                                                              | Configuración docs + CRUD roles    |
| 10   | `007_suscripciones.sql`                                                                 | Suscripción + descarga documentos  |

---

## Próximas fases naturales

- **Fase 17 — Puestos/Sitios**: ubicaciones físicas con `cliente_id` (FK), dirección, coordenadas, tipo (oficina, planta, residencial).
- **Fase 18 — Asignaciones**: vincular guardia a puesto en un periodo.
- **Fase 19 — Turnos**: rotaciones por puesto con horarios.
- **Fase 20 — Asistencia/Check-in**: registro de presencia con geolocalización.

---

## Cómo aplicar las migraciones

```bash
# Ver estado
php bin/migrate.php status

# Aplicar pendientes
php bin/migrate.php migrate

# Revertir la última
php bin/migrate.php rollback
```

## Cómo correr tests

`vendor/` se versiona sin dependencias de desarrollo (despliegue Git-only en cPanel). PHPUnit y PHPStan se instalan aparte, en `.devtools/` (ignorado por Git):

```bash
COMPOSER_VENDOR_DIR=.devtools/vendor composer install
php .devtools/vendor/bin/phpunit --bootstrap .devtools/vendor/autoload.php
```

## Healthcheck

```bash
curl http://localhost/digisecure/public/healthcheck
# {"status":"ok","service":"DigiSecure","phase":"16.0",...}
```
