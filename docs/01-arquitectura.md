# DigiSecure — Documento de Arquitectura

> Versión: 1.0 · Fase 0.2 (cimientos) · Estado: VIGENTE
> Este documento es el **contrato técnico** del proyecto. Cualquier cambio
> arquitectónico requiere actualizar este archivo antes de tocar código.

---

## 1. Visión y propósito

**DigiSecure** es un SaaS multi-tenant para empresas de seguridad privada en Ecuador. Administra fichas y expedientes documentales de guardias de seguridad bajo cumplimiento de la Ley Orgánica de Protección de Datos Personales (LOPDP) ecuatoriana.

Una sola base de datos compartida sirve a múltiples tenants (empresas), con aislamiento estricto por `tenant_id` aplicado en la capa de repositorios.

### Stack

| Capa | Tecnología | Versión | Razón |
|---|---|---|---|
| Lenguaje | PHP | 8.3 | Tipos estrictos, readonly classes, enums |
| Base de datos | MySQL | 8.0+ | JSON nativo, window functions, CTEs |
| UI | AdminLTE | 4 | Bootstrap 5 sin jQuery |
| Frontend | Bootstrap | 5.x | Mobile-first |
| Charts | Chart.js | 4.x | KPIs y dashboards |
| Email | PHPMailer | 6.9 | Única dependencia externa de mail |
| WhatsApp | Meta Cloud API | v20+ | Notificaciones a guardias |
| Mapas | Google Maps JS API | — | Croquis del domicilio |

**Sin frameworks PHP.** Routing, ORM, contenedor DI, sesiones y autenticación se implementan desde cero. Esta es una decisión consciente del cliente para tener control total y no acumular dependencias.

---

## 2. Principios arquitectónicos

1. **Separación estricta por capas** (Hexagonal / Clean Architecture light).
2. **Multi-tenant por discriminador** con aislamiento forzado por `BaseRepository`.
3. **Domain-Driven Design selectivo** en módulos críticos (Ficha, Expediente, Security).
4. **CQRS** para reportes y KPIs.
5. **Inmutabilidad** en value objects del dominio.
6. **Tipado estricto** (`declare(strict_types=1)` en todos los archivos PHP).
7. **Fail fast**: las invariantes del dominio se validan en el constructor del agregado.
8. **Defensa en profundidad** en seguridad: nunca confiar en una sola capa.
9. **Escalabilidad horizontal** por diseño (sin estado en la app, sesiones en BD/Redis cuando llegue el momento).

---

## 3. Modelo de capas

```
┌──────────────────────────────────────────────────────────────────────┐
│                        public/index.php                              │
│                        (Front Controller único)                      │
└──────────────────────────────────────┬───────────────────────────────┘
                                       ▼
┌──────────────────────────────────────────────────────────────────────┐
│                            KERNEL                                    │
│  ┌──────────┐  ┌────────────┐  ┌──────────────┐  ┌───────────────┐   │
│  │ Bootstrap│→ │  Container │→ │  Middleware  │→ │     Router    │   │
│  └──────────┘  └────────────┘  │   pipeline   │  └──────┬────────┘   │
│                                └──────────────┘         │            │
└─────────────────────────────────────────────────────────┼────────────┘
                                                          ▼
┌──────────────────────────────────────────────────────────────────────┐
│                       PRESENTATION (Controllers)                     │
│  • Reciben Request, devuelven Response                               │
│  • Sin lógica de negocio, sin SQL, sin acceso a $_POST directo       │
│  • Delegan a Application Services                                    │
└──────────────────────────────────────┬───────────────────────────────┘
                                       ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    APPLICATION (Use Cases / Services)                │
│  • Orquestan operaciones del dominio                                 │
│  • Manejan transacciones                                             │
│  • Disparan eventos de dominio                                       │
│  • NO conocen HTTP, NO conocen SQL                                   │
└──────────────────────────────────────┬───────────────────────────────┘
                                       ▼
┌──────────────────────────────────────────────────────────────────────┐
│                          DOMAIN (Core)                               │
│  • Agregados, entidades, value objects                               │
│  • Reglas de negocio invariantes                                     │
│  • Interfaces de Repositorio (puertos)                               │
│  • Cero dependencias externas                                        │
└──────────────────────────────────────┬───────────────────────────────┘
                                       ▼
┌──────────────────────────────────────────────────────────────────────┐
│                       INFRASTRUCTURE                                 │
│  • Implementaciones concretas de los puertos del dominio             │
│  • PDO, filesystem, SMTP, WhatsApp Cloud, Google Maps                │
│  • BaseRepository (filtro tenant_id automático)                      │
└──────────────────────────────────────────────────────────────────────┘
```

### Reglas de dependencia
- **Domain** no depende de nada (ni siquiera del Kernel).
- **Application** depende solo de Domain.
- **Infrastructure** implementa interfaces de Domain.
- **Presentation** depende de Application.
- **Kernel** sirve a todos pero no depende de los módulos de negocio.

---

## 4. Flujo de un request HTTP

```
1. Apache → public/index.php
2. Bootstrap carga .env, registra ErrorHandler global
3. Container resuelve dependencias (constructor injection)
4. Pipeline de middleware:
     ├─ ErrorHandlerMiddleware       (try/catch global)
     ├─ SecurityHeadersMiddleware    (CSP, HSTS, X-Frame...)
     ├─ SessionMiddleware            (start con cookies seguras)
     ├─ CsrfMiddleware               (valida token en POST/PUT/DELETE)
     ├─ AuthenticationMiddleware     (carga usuario si hay sesión)
     ├─ TenantContextMiddleware      (inyecta tenant_id en contexto)
     ├─ AuthorizationMiddleware      (chequea permisos del rol)
     └─ AuditMiddleware              (loggea acción al finalizar)
5. Router despacha al Controller
6. Controller llama Application Service
7. Application Service:
     ├─ valida entrada (DTOs)
     ├─ carga agregado vía Repository (filtrado por tenant)
     ├─ ejecuta operación del dominio
     ├─ persiste cambios
     └─ devuelve resultado (DTO)
8. Controller construye Response
9. Middleware pipeline en orden inverso (post-process)
10. Response al cliente
```

---

## 5. Estructura de carpetas

```
digisecure/
├── public/                Front controller único (DocumentRoot apunta aquí)
│   ├── index.php
│   ├── assets/            CSS/JS compilados (AdminLTE 4)
│   └── .htaccess
│
├── src/                   PSR-4 → DigiSecure\
│   ├── Kernel/            Arranque del framework propio
│   │   ├── Bootstrap.php
│   │   ├── Container/         DI container manual
│   │   ├── Http/              Request, Response, Stream
│   │   ├── Routing/           Router, Route, RouteCollection
│   │   ├── Middleware/        Pipeline + interfaz
│   │   ├── Error/             ErrorHandler, ExceptionRenderer
│   │   └── Config/            ConfigLoader (.env)
│   │
│   ├── Shared/            Código compartido entre módulos
│   │   ├── Domain/            ValueObject, AggregateRoot, DomainEvent
│   │   ├── Application/       UseCase, DTO base
│   │   └── Infrastructure/    Helpers comunes, Clock, IdGenerator (ULID)
│   │
│   ├── Security/          Bounded context: Seguridad
│   │   ├── Auth/              Login, logout, password reset
│   │   ├── Session/           SessionManager seguro
│   │   ├── Csrf/              CsrfGuard, token generation
│   │   ├── Crypto/            Encryption (AES-256-GCM), Hasher (Argon2id)
│   │   ├── Rbac/              Role, Permission, Authorizer
│   │   ├── Audit/             AuditLogger, AuditEntry
│   │   └── Lopdp/             ConsentManager, AccessLog, DataRetention
│   │
│   ├── Tenant/            Bounded context: Multi-tenancy
│   │   ├── Domain/            Tenant entity, TenantContext
│   │   ├── Application/       CreateTenantUseCase, etc.
│   │   └── Infrastructure/    TenantRepository
│   │
│   ├── User/              Gestión de usuarios del sistema (no guardias)
│   │   ├── Domain/
│   │   ├── Application/
│   │   ├── Infrastructure/
│   │   └── Presentation/      Controllers, Views
│   │
│   ├── Ficha/             AGREGADO: Ficha del Guardia
│   │   ├── Domain/
│   │   │   ├── Ficha.php (root aggregate)
│   │   │   ├── ValueObject/   Cedula, Email, Telefono, FechaNacimiento, etc.
│   │   │   ├── Entity/        Domicilio, Estudios, ReferenciaPersonal...
│   │   │   ├── Enum/          TipoFicha, EstadoCivil, TipoSangre, Genero
│   │   │   ├── Event/         FichaCreada, FichaCompletada
│   │   │   └── Repository/    FichaRepository (interface)
│   │   ├── Application/
│   │   │   ├── Command/       CrearFicha, ActualizarFicha, CompletarFicha
│   │   │   ├── Query/         BuscarFichas, ObtenerFicha
│   │   │   └── Validator/     RequisitosBasicosValidator
│   │   ├── Infrastructure/
│   │   │   ├── PdoFichaRepository.php
│   │   │   └── PdfDeclaracionLealtadGenerator.php
│   │   └── Presentation/      Controllers + Views
│   │
│   ├── Expediente/        AGREGADO: Expediente Documental
│   │   ├── Domain/
│   │   │   ├── Expediente.php
│   │   │   ├── ExpedienteDocumento.php
│   │   │   ├── DocumentoTipo.php (catálogo)
│   │   │   ├── Enum/          BloqueExpediente, EstadoDocumento
│   │   │   ├── Event/         DocumentoSubido, DocumentoVencido
│   │   │   └── Repository/
│   │   ├── Application/
│   │   ├── Infrastructure/
│   │   └── Presentation/
│   │
│   ├── Notification/      Motor de notificaciones
│   │   ├── Domain/            Notification, NotificationChannel (interface)
│   │   ├── Application/       SendNotificationUseCase, ScheduleReminders
│   │   └── Infrastructure/
│   │       ├── Mail/          PhpMailerEmailChannel
│   │       └── Whatsapp/      MetaCloudWhatsappChannel
│   │
│   ├── Subscription/      Planes y facturación por tenant
│   │
│   ├── Reporting/         CQRS — read-only para KPIs
│   │   ├── Application/       Queries (read models)
│   │   └── Infrastructure/    SqlReadModel (lectura directa, sin pasar por agregados)
│   │
│   └── Infrastructure/    Servicios cross-cutting
│       ├── Persistence/       PdoConnection, BaseRepository, UnitOfWork
│       ├── Storage/           SecureFileStorage (cifrado, fuera del webroot)
│       ├── Mail/              MailerFactory
│       ├── Whatsapp/          WhatsappClient
│       └── Logging/           FileLogger
│
├── templates/             Vistas PHP planas
│   ├── layout/                AdminLTE 4 base
│   ├── auth/                  login, password reset
│   ├── errors/                403, 404, 500
│   └── [modulo]/              Una carpeta por módulo
│
├── migrations/            SQL versionado: 001_initial.sql, 002_..., etc.
├── config/                container.php, routes.php, middleware.php
├── var/                   NO versionado
│   ├── logs/
│   ├── cache/
│   ├── sessions/
│   └── storage/               Archivos cifrados (fotos, PDFs, capturas)
├── tests/
│   ├── Unit/
│   └── Integration/
├── bin/                   CLI: migrate.php, jobs/notify-expirations.php
└── docs/                  Esta documentación
```

---

## 6. Multi-tenancy

### 6.1 Estrategia
**BD compartida + discriminador `tenant_id`** en cada tabla tenant-aware.
Aislamiento por `tenant_id` físicamente forzado por `BaseRepository`.

### 6.2 Identificación del tenant
**Solo después del login**. Tras autenticar, el sistema resuelve el tenant del usuario y lo almacena en `TenantContext` (objeto request-scoped). NO se usa subdominio ni path.

### 6.3 BaseRepository (la pieza más crítica del sistema)

```php
abstract class BaseRepository
{
    public function __construct(
        protected readonly PdoConnection $pdo,
        protected readonly TenantContext $tenantContext,
    ) {}

    /**
     * Toda query DEBE pasar por aquí. Inyecta WHERE tenant_id = :tenant_id
     * automáticamente. Si la subclase ignora este método, viola el contrato
     * y se rompe en tests de auditoría.
     */
    final protected function buildQuery(string $baseSql): PDOStatement
    {
        $tenantId = $this->tenantContext->currentTenantId();
        if ($tenantId === null) {
            throw new TenantContextMissingException(
                'Intento de query sin contexto de tenant. Esto es un bug crítico.'
            );
        }

        $sql = $this->injectTenantFilter($baseSql);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':__tenant_id', $tenantId, PDO::PARAM_STR);
        return $stmt;
    }

    private function injectTenantFilter(string $sql): string
    {
        // Lógica para añadir AND tenant_id = :__tenant_id en cada WHERE
        // o un wrapper SELECT * FROM (...) WHERE tenant_id = :__tenant_id
    }
}
```

**Tests obligatorios:**
- Una query a una tabla tenant-aware sin contexto debe lanzar excepción.
- Una query a una tabla tenant-aware debe contener el filtro `tenant_id` (verificado por reflection del SQL generado).
- Un repositorio de tenant A no puede leer registros del tenant B (test de integración con 2 tenants).

### 6.4 Tablas globales (sin tenant_id)
- `tenants` (la tabla maestra)
- `migraciones`
- `configuracion_global_sistema`

Todo lo demás lleva `tenant_id` indexado como primer componente.

---

## 7. Bounded contexts y agregados

### 7.1 Mapa de contextos

```
┌──────────────────────────┐    ┌──────────────────────────┐
│       SECURITY           │    │         TENANT           │
│   (Auth, RBAC, Audit,    │◀───│  (Empresa cliente,       │
│    Crypto, LOPDP)        │    │   suscripción)           │
└────────┬─────────────────┘    └────────┬─────────────────┘
         │                               │
         │ usa contexto                  │ provee tenant_id
         ▼                               ▼
┌────────────────────────────────────────────────────────────┐
│                       USER                                  │
│       (Usuarios del sistema, NO guardias)                   │
└────────┬───────────────────────────────────────────────────┘
         │
         │ crea/edita
         ▼
┌──────────────────────────┐    ┌──────────────────────────┐
│         FICHA            │    │       EXPEDIENTE         │
│  (47 campos del guardia, │◀──▶│  (30 docs en 2 bloques,  │
│   datos personales,      │1:1 │   vigencias,             │
│   biométricos)           │    │   validaciones)          │
└──────────────────────────┘    └────────┬─────────────────┘
                                         │
                                         │ dispara eventos
                                         ▼
                                ┌──────────────────────────┐
                                │     NOTIFICATION         │
                                │  (Email + WhatsApp,      │
                                │   recordatorios)         │
                                └──────────────────────────┘

┌──────────────────────────┐    ┌──────────────────────────┐
│       REPORTING          │    │      SUBSCRIPTION        │
│   (CQRS, KPIs lectura)   │    │   (planes, billing)      │
└──────────────────────────┘    └──────────────────────────┘
```

### 7.2 Agregado Ficha (resumen)

- **Root:** `Ficha` (ID: ULID)
- **VOs:** `Cedula`, `Email`, `Telefono`, `FechaNacimiento`, `Estatura`, `CuentaBancaria`, `NombreCompleto`, `LugarNacimiento`
- **Entidades hijas:** `Domicilio`, `Estudios`, `TrabajoAnterior` (1:N), `ReferenciaPersonal` (padre/madre/hermano), `HijoMenor` (1:N), `Croquis`, `Foto`
- **Sin firma digital ni huellas digitales** (decisión #2/#3 del usuario)
- **47 campos** (los 46 originales + `genero`)

**Invariantes:**
- Cédula válida según algoritmo módulo 10 con coeficientes Ecuador
- Edad ≥ 18 al momento de emisión
- Si `tipo = REINGRESO`, debe existir ficha previa con mismo `cedula_hash` en el mismo tenant
- No puede marcarse COMPLETA sin haber firmado consentimiento LOPDP

### 7.3 Agregado Expediente (resumen)

- **Root:** `Expediente` (1:1 con Ficha)
- **Hijos:** colección de `ExpedienteDocumento`
- **30 tipos de documento** divididos en:
  - **Bloque A — Admisibilidad** (26 docs pre-empleo)
  - **Bloque B — Vinculación laboral** (4 docs post-contratación)
- **Bloque B se desbloquea solo cuando Bloque A está 100% ACEPTADO**
- Acta de uniformes permite **múltiples instancias históricas**

**Invariantes:**
- No se puede pasar a Bloque B sin completar Bloque A
- Un documento ACEPTADO no puede ser modificado, solo reemplazado (queda historial)
- Vigencia: si `documento_tipo.tiene_vencimiento = true` y no hay `fecha_vencimiento`, la ficha no puede completarse
- Requisitos básicos (edad 20-45, estatura, bachiller, 1 año experiencia): bloqueo duro al completar el expediente

---

## 8. Modelo de seguridad

### 8.1 Autenticación
- **Sesiones nativas PHP** con almacenamiento en filesystem (`var/sessions/`) inicialmente, migrable a Redis/MySQL en producción.
- **Cookies:** `Secure` (HTTPS en prod), `HttpOnly`, `SameSite=Strict`
- **Hash:** `password_hash($pwd, PASSWORD_ARGON2ID, [memory_cost=65536, time_cost=4, threads=2])`
- **Comparación:** siempre `password_verify()` y `hash_equals()` (timing-safe)
- **Regeneración de session_id:** tras login y tras cambio de privilegios
- **Rate limiting de login:** 5 intentos fallidos → bloqueo 15 minutos por IP+username

### 8.2 Autorización (RBAC)
- **Modelo:** Usuario → Rol(es) → Permiso(s)
- **Permisos:** strings con namespace (`ficha.crear`, `ficha.leer`, `expediente.aprobar_documento`, `usuario.gestionar`, etc.)
- **Verificación:** decorador `Authorizer::ensure($user, 'ficha.crear')` antes de cada acción
- **Menú dinámico:** solo se muestran las opciones para las que el usuario tiene permiso

### 8.3 CSRF
- Token generado con `random_bytes(32)` y hex-encoded
- Almacenado en sesión + inyectado en formularios como hidden input
- Validado en todo POST/PUT/DELETE con `hash_equals()`
- Excepciones: webhooks externos (que tienen su propia firma HMAC)

### 8.4 Cifrado
- **Algoritmo:** AES-256-GCM (autenticated encryption)
- **Implementación:** `openssl_encrypt()` con IV único de 12 bytes por cifrado
- **Datos cifrados:**
  - `cedula_cifrada` en `fichas` (+ `cedula_hash` SHA-256 para búsquedas)
  - `cuenta_bancaria_*` cifrados
  - Contenido binario de archivos en `var/storage/`
- **Clave maestra:** `ENCRYPTION_KEY` en `.env`, derivada por tenant con HKDF para evitar fuga cross-tenant
- **Rotación:** procedimiento documentado pero manual en Fase 1

### 8.5 Auditoría
Tabla `auditoria_acciones`:
- `id`, `tenant_id`, `usuario_id`, `accion`, `entidad`, `entidad_id`, `cambios_json`, `ip`, `user_agent`, `created_at`

Todas las acciones sensibles registran:
- Crear/editar/eliminar ficha
- Subir/aprobar/rechazar documento
- Login exitoso, login fallido, logout
- Cambios de rol/permiso
- Exportaciones de datos

### 8.6 LOPDP Ecuador

| Requisito legal | Implementación |
|---|---|
| Consentimiento explícito | Tabla `consentimientos_lopdp` con timestamp, IP, hash del texto legal versionado |
| Bitácora de accesos | Tabla `lopdp_accesos` separada de la auditoría técnica, registra **lecturas** de campos sensibles |
| Derechos ARCO-P | Endpoints desde Fase 1 (Acceso, Rectificación, Cancelación, Oposición, Portabilidad) |
| Cifrado en reposo | AES-256-GCM en datos sensibles + archivos en disco |
| Retención limitada | 5 años post-baja, después job de anonimización automática |
| Plazo de respuesta | 15 días para responder solicitudes ARCO-P (configurar alertas internas) |

---

## 9. Modelo de datos (visión global)

### 9.1 Tablas globales (sin tenant_id)
- `tenants` — empresa cliente
- `migraciones` — registro de migraciones aplicadas
- `configuracion_global` — settings del sistema

### 9.2 Tablas tenant-aware (núcleo)
- `usuarios` — usuarios del sistema (no guardias)
- `roles`, `permisos`, `usuario_roles`, `rol_permisos`
- `auditoria_acciones`
- `lopdp_accesos`
- `consentimientos_lopdp`
- `archivos_seguros` — tabla central de TODOS los archivos

### 9.3 Tablas tenant-aware (negocio)
- `fichas` — agregado raíz (47 campos)
- `fichas_trabajos_anteriores` — 1:N
- `fichas_hijos_menores` — 1:N
- `fichas_referencias` — 1:N
- `fichas_cursos` — N:M con catálogo
- `expedientes` — 1:1 con `fichas`
- `expediente_documentos` — 1:N
- `expediente_documento_capturas` — 2 capturas por validación
- `documento_tipos` — catálogo configurable por tenant (30 semilla)
- `requisitos_basicos_reglas` — 4 semilla, configurables
- `notificaciones` — cola de envíos
- `notificaciones_log` — registro de cada envío

### 9.4 Convenciones de naming SQL

- Tablas: `snake_case`, plural en español (`fichas`, `expediente_documentos`)
- Columnas: `snake_case`, español
- IDs: `id` (ULID `CHAR(26)`)
- Timestamps: `created_at`, `updated_at`, `deleted_at` (todos `DATETIME(6)`)
- FKs: `[entidad_singular]_id` (`tenant_id`, `usuario_id`)
- Booleanos: `es_*` o `tiene_*` o `activo` (`TINYINT(1)`)
- Índices: `idx_[tabla]_[cols]`
- Únicos: `uk_[tabla]_[cols]`
- FKs: `fk_[tabla]_[col]`

### 9.5 Reglas de modelado

1. **Toda tabla tenant-aware:** primer índice = `tenant_id`, e índices compuestos siempre arrancan con `tenant_id`.
2. **ULID en vez de UUID v4** para PKs (ordenable por tiempo, mejor para B-Tree).
3. **Particionamiento por `KEY(tenant_id)`** en tablas que crecerán mucho (`fichas`, `auditoria_acciones`, `lopdp_accesos`, `archivos_seguros`).
4. **Soft delete** vía `deleted_at NULL` en tablas de negocio.
5. **JSON columns** para datos semi-estructurados (cambios de auditoría, parámetros de reglas).
6. **InnoDB** siempre, charset `utf8mb4`, collation `utf8mb4_unicode_ci`.

---

## 10. Sistema de notificaciones

### 10.1 Casos de uso

| Trigger | Destinatarios | Canal | Frecuencia |
|---|---|---|---|
| Vencimiento próximo de doc | Guardia + Admin tenant | Email + WhatsApp | 30, 15, 7 días antes |
| Vencimiento alcanzado | Guardia + Admin tenant | Email + WhatsApp | El día del vencimiento |
| Fecha objetivo de actualización | Guardia + Admin tenant | Email + WhatsApp | 15, 7, 3 días antes y el día |
| Documento rechazado | Guardia + Admin tenant | Email | Inmediato |
| Solicitud LOPDP recibida | Admin tenant | Email | Inmediato |

### 10.2 Arquitectura

```
┌─────────────────────┐    ┌──────────────────────┐
│  Job programado     │    │  Eventos del dominio │
│  (cron diario)      │    │  (DocumentoRechazado,│
│                     │    │   FichaCompletada...) │
└──────────┬──────────┘    └───────────┬──────────┘
           │                           │
           ▼                           ▼
┌──────────────────────────────────────────────────┐
│        NotificationDispatcher                    │
│   - Aplica plantilla por tenant                  │
│   - Resuelve destinatarios                       │
│   - Decide canal(es)                             │
│   - Verifica idempotencia (no duplicar el día)   │
└─────────┬──────────────────────┬─────────────────┘
          ▼                      ▼
┌──────────────────┐   ┌──────────────────────┐
│  EmailChannel    │   │  WhatsappChannel     │
│  (PHPMailer SMTP)│   │  (Meta Cloud API)    │
└─────────┬────────┘   └──────────┬───────────┘
          │                       │
          ▼                       ▼
┌──────────────────────────────────────────────────┐
│           notificaciones_log                     │
│   (canal, destinatario, status, timestamp)       │
└──────────────────────────────────────────────────┘
```

### 10.3 Canales como interfaz

```php
interface NotificationChannel
{
    public function send(Notification $notification): NotificationResult;
    public function name(): string;
}
```

Implementaciones: `PhpMailerEmailChannel`, `MetaCloudWhatsappChannel`. Permite enchufar nuevos canales (SMS, push) sin tocar el dispatcher.

---

## 11. Las 12 reglas de oro vanilla (innegociables)

| # | Regla | Validación |
|---|---|---|
| 1 | PDO: `prepare()` + `bindValue()`, jamás concatenar SQL | Code review + test estático |
| 2 | PDO: `EMULATE_PREPARES=false`, `ERRMODE_EXCEPTION` | Configurado en `PdoConnection` |
| 3 | Output HTML: `htmlspecialchars($x, ENT_QUOTES \| ENT_HTML5, 'UTF-8')` | Helper `e()` global |
| 4 | Sesiones: `Secure`, `HttpOnly`, `SameSite=Strict` antes de `session_start()` | `SessionManager::start()` |
| 5 | Regenerar `session_id()` tras login y cambio de privilegio | `Authenticator::login()` |
| 6 | `password_hash` con `PASSWORD_ARGON2ID` | `Hasher::hash()` |
| 7 | Comparación de tokens con `hash_equals()`, jamás `==` | Code review |
| 8 | CSRF token obligatorio en POST/PUT/DELETE | `CsrfMiddleware` |
| 9 | Cifrado: `openssl_encrypt('aes-256-gcm', ...)` con IV único | `Encryption::encrypt()` |
| 10 | Errores en producción: `display_errors=0`, render genérico | `ErrorHandler` |
| 11 | Uploads: doble validación tipo (extensión + `finfo_file`) | `FileValidator` |
| 12 | Toda query a tabla tenant-aware pasa por `BaseRepository` | Test de auditoría |

**Cualquier violación detectada en code review se rechaza automáticamente.**

---

## 12. Plan de Fase 0 (cimientos)

| Paso | Estado | Entregable |
|---|---|---|
| 0.1 | ✅ | Composer + estructura PSR-4 + .gitignore + .env.example |
| 0.2 | ✅ | Este documento (`docs/01-arquitectura.md`) |
| 0.3 | 🔜 | Kernel: Request, Response, Router, Container, ErrorHandler, Bootstrap |
| 0.4 | ⏸ | PdoConnection + Migration runner propio |
| 0.5 | ⏸ | SessionManager + CsrfGuard + ConfigLoader |
| 0.6 | ⏸ | Migración 001: tenants, usuarios, roles, permisos, auditoría, LOPDP |
| 0.7 | ⏸ | Módulo Auth completo: login, logout, RBAC funcional |
| 0.8 | ⏸ | BaseRepository + TenantContext |
| 0.9 | ⏸ | Layout AdminLTE 4 + menú dinámico + página de login |

---

## 13. Plan posterior (Fases 1-4)

- **Fase 1:** Módulo Ficha del Guardia (agregado completo, value objects, validación cédula, Google Maps, generación PDF declaración lealtad)
- **Fase 2:** Módulo Expediente Documental (catálogo, upload seguro, validaciones externas, capturas, vencimientos, bloques A/B)
- **Fase 3:** Generación de PDFs (formato físico de huellas, declaración de lealtad descargable)
- **Fase 4:** Dashboard, KPIs, alertas, suscripciones, motor completo de notificaciones email + WhatsApp

---

## 14. Decisiones registradas

Ver `MEMORY.md` y `project_decisions_arquitectura.md` en el sistema de memoria del asistente. 23 decisiones vinculantes confirmadas con el cliente.

---

**FIN del documento de arquitectura v1.0**
