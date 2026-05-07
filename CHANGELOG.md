# DigiSecure — Changelog

Estado actual: **Phase 16.0** · 10 migraciones aplicadas · 45/45 tests verdes.

Stack: PHP 8.3 / MySQL 8 / Bootstrap 5.3 / Chart.js 4.4 + sistema de diseño propio `ds-system`.

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

```bash
vendor/bin/phpunit --colors=always
```

## Healthcheck

```bash
curl http://localhost/digisecure/public/healthcheck
# {"status":"ok","service":"DigiSecure","phase":"16.0",...}
```
