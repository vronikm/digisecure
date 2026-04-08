# DigiSecure

Sistema Integral SaaS multi-tenant para la administración de fichas y documentación de guardias de seguridad. Diseñado para empresas de seguridad privada en Ecuador.

## Stack

- **PHP 8.3** (vanilla, sin framework)
- **MySQL 8.0+**
- **AdminLTE 4** (Bootstrap 5)
- **Chart.js 4**
- **PHPMailer 6** (única dependencia externa de email)

## Estructura del proyecto

```
digisecure/
├── public/              # único entrypoint HTTP (DocumentRoot apunta aquí)
│   ├── index.php
│   └── .htaccess
├── src/                 # código de aplicación (PSR-4 → DigiSecure\)
│   ├── Kernel/          # Bootstrap, Router, Container, ErrorHandler
│   ├── Shared/          # Value objects, excepciones, interfaces comunes
│   ├── Security/        # Auth, RBAC, Csrf, Crypto, Audit, LOPDP
│   ├── Tenant/          # Contexto multi-tenant
│   ├── User/            # Gestión de usuarios
│   ├── Ficha/           # Agregado: ficha del guardia
│   ├── Expediente/      # Agregado: expediente documental
│   ├── Notification/    # Email + WhatsApp + cola de envíos
│   ├── Subscription/    # Planes y suscripciones por tenant
│   ├── Reporting/       # CQRS: KPIs, dashboards
│   └── Infrastructure/  # PDO, Storage, Mail, WhatsApp, Logging
├── templates/           # Vistas (PHP plano con escape obligatorio)
├── migrations/          # Migraciones SQL versionadas
├── config/              # Configuración cargada al arranque
├── var/                 # NO versionado: logs, cache, sessions, storage
│   ├── logs/
│   ├── cache/
│   ├── sessions/
│   └── storage/         # archivos cifrados (fotos, PDFs, capturas)
├── tests/
│   ├── Unit/
│   └── Integration/
├── bin/                 # CLI scripts (migraciones, jobs)
├── docs/                # Documentación de arquitectura y dominio
├── composer.json
├── .env.example         # plantilla — copiar a .env y completar
└── .gitignore
```

## Instalación (desarrollo)

```bash
# 1. Instalar dependencias
composer install

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Generar claves criptográficas y completar .env:
php -r "echo 'APP_KEY='.bin2hex(random_bytes(32)).PHP_EOL;"
php -r "echo 'ENCRYPTION_KEY='.bin2hex(random_bytes(32)).PHP_EOL;"

# 4. Configurar BD y ejecutar migraciones (Fase 0.6+)
# php bin/migrate.php
```

## Reglas de oro (innegociables)

1. **PDO siempre con prepared statements.** `EMULATE_PREPARES=false`. Jamás concatenar SQL.
2. **Toda salida HTML escapada** con `htmlspecialchars($x, ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
3. **Toda tabla tenant-aware** debe incluir `tenant_id` y solo accederse vía `BaseRepository`.
4. **Sesiones**: cookies `Secure`, `HttpOnly`, `SameSite=Strict`. Regenerar `session_id` tras login.
5. **Contraseñas**: `password_hash` con `PASSWORD_ARGON2ID`.
6. **CSRF**: token obligatorio en todo POST/PUT/DELETE, comparación con `hash_equals`.
7. **Cifrado**: AES-256-GCM para datos sensibles (cédulas, cuentas, archivos biométricos).
8. **Errores en producción**: `display_errors=0`, manejo centralizado en `ErrorHandler`.
9. **Uploads**: doble validación tipo (extensión + magic bytes), nombres aleatorios, fuera del webroot.
10. **Auditoría**: toda acción sensible registrada (quién, qué, cuándo, IP).
11. **LOPDP Ecuador**: consentimiento previo, bitácora de accesos a datos personales, derechos ARCO-P.
12. **Retención**: 5 años post-baja, después anonimización automática.

## Licencia

Propietario. Todos los derechos reservados.
