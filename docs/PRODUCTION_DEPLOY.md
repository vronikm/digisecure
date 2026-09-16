# DigiSecure - despliegue a produccion

Dominio objetivo:

```text
https://digisecure.digitech.com.ec
```

## 1. Webroot

El DocumentRoot de Apache/Nginx debe apuntar a:

```text
/ruta/digisecure/public
```

No debe apuntar a la raiz del proyecto.

## 2. Dependencias

Version minima de PHP en produccion:

```text
PHP 8.2.31 o superior
```

Modo recomendado si el servidor permite SSH/Composer: ejecutar en la raiz del
proyecto:

```bash
composer install --no-dev --optimize-autoloader
```

Debe existir:

```text
vendor/autoload.php
```

Si el despliegue se hace solo desde Git en cPanel y no se ejecuta Composer en
el servidor, subir tambien `vendor/` en el repo. Prepararlo localmente con:

```bash
composer install --no-dev --optimize-autoloader
git add vendor composer.json composer.lock
```

El repositorio debe subir codigo, plantillas, documentacion y `vendor/` si se
usa modo Git-only, pero nunca secretos. El archivo `.env` real se crea una sola
vez en el servidor y no se versiona.

## 3. Archivo .env de produccion

Crear `.env` en el servidor, no subir el `.env` local de desarrollo.
Si cPanel actualiza el proyecto con Git, mantener `.env` fuera del repo evita
que un pull/checkout sobreescriba secretos.

Valores minimos esperados:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://digisecure.digitech.com.ec
APP_TIMEZONE=America/Guayaquil
APP_LOCALE=es_EC
APP_KEY=GENERAR_32_BYTES_HEX

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digitech_digisecure
DB_USERNAME=digitech
DB_PASSWORD=NO_VERSIONAR_ESTE_VALOR
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_NAME=DIGISECURE_SID
SESSION_LIFETIME_MINUTES=120
SESSION_COOKIE_SECURE=true
SESSION_COOKIE_HTTPONLY=true
SESSION_COOKIE_SAMESITE=Strict
SESSION_SAVE_PATH=var/sessions

ENCRYPTION_KEY=GENERAR_32_BYTES_HEX

LOG_PATH=var/logs/digisecure.log
LOG_LEVEL=error
```

Generar claves:

```bash
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
```

Usar una clave diferente para `APP_KEY` y `ENCRYPTION_KEY`.

El valor real de `DB_PASSWORD` debe escribirse solo en el `.env` del servidor.
Si la contrasena fue compartida por chat, correo o ticket, rotarla en cPanel y
actualizar el `.env` con la nueva.

## 4. Correo transaccional

La recuperacion de contrasena y la verificacion de aspirantes usan los valores
`MAIL_*` del `.env`. En produccion deben estar configurados antes de probar
`/password/forgot`.

```env
MAIL_DRIVER=smtp
MAIL_HOST=mail.tu-dominio.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@tu-dominio.com
MAIL_PASSWORD=NO_VERSIONAR_ESTE_VALOR
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@tu-dominio.com
MAIL_FROM_NAME="DigiSecure"
```

En cPanel, usar el host/puerto exacto de la cuenta de correo. Si el hosting
requiere el MTA local en vez de SMTP autenticado, usar:

```env
MAIL_DRIVER=mail
MAIL_FROM_ADDRESS=no-reply@tu-dominio.com
MAIL_FROM_NAME="DigiSecure"
```

Validar desde la raiz del proyecto en el servidor:

```bash
php bin/test-mail.php --to=tu-correo@dominio.com
```

El script no imprime passwords. Si falla, revisar `Codigo` y `Detalle`, ademas
de `var/logs/mail.log`.

## 5. Permisos

El usuario del servidor web debe poder escribir en:

```text
var/cache
var/imports
var/logs
var/sessions
var/storage
```

El directorio `var/storage` debe estar fuera del webroot; en este proyecto ya esta fuera de `public/`.

## 6. Base de datos

Revisar estado de migraciones:

```bash
php bin/migrate.php status
```

Si aparecen migraciones aplicadas con `CHECKSUM_MISMATCH` por cambios ya
versionados en Git, reparar primero el historial de checksums. El primer comando
solo muestra lo que cambiaria:

```bash
php bin/repair-migration-checksums.php
php bin/repair-migration-checksums.php --apply
```

Luego ejecutar migraciones pendientes:

```bash
php bin/migrate.php migrate
php bin/migrate.php status
```

### Indice de cedulas (blind index HMAC)

`cedula_hash` es un HMAC con una subclave derivada de `ENCRYPTION_KEY` (antes
era sha256 sin clave, reversible por fuerza bruta). Ejecutar **inmediatamente
despues de desplegar el codigo**; hasta hacerlo, la busqueda por cedula y la
deteccion de reingreso no encuentran fichas existentes:

```bash
php bin/rehash-cedulas.php --dry-run
php bin/rehash-cedulas.php --apply
```

Es idempotente. Si reporta filas "no descifrables", la `ENCRYPTION_KEY` del
servidor no es la que cifro esos datos: no continuar sin revisar. Para fichas
de aspirantes existe `php bin/repair-encrypted-cedulas.php --dry-run`.

Rotar `ENCRYPTION_KEY` exige re-cifrar los datos y volver a ejecutar
`bin/rehash-cedulas.php --apply`.

La migracion `020_importaciones_gemeseg_permission` habilita el permiso
`importaciones.gemeseg`. Despues de aplicarla, la opcion queda en:

```text
Control > Importaciones > Excel GEMESEG
```

Flujo recomendado para GEMESEG:

1. Subir el `.xlsx` desde la pantalla.
2. Guardar staging cifrado.
3. Procesar las filas pendientes desde `Staging reciente`.
4. Revisar fichas creadas en `Guardias` y expedientes/documentos pendientes.

Crear el superadmin inicial con una contrasena temporal fuerte:

```bash
php bin/create-superadmin.php --email=admin@digitech.com.ec
```

El script imprime la contrasena generada si no se pasa `--password`.

## 7. Archivos que no deben subirse

No subir:

```text
.git/
.claude/
.agents/
.codex/
.env local de desarrollo
tmp_*.txt
var/*.txt
var/logs/*.log locales
tests/ en paquetes finales si no se necesita ejecutar pruebas en servidor
```

## 8. Verificacion minima

```bash
php -l public/index.php
php -l src/Kernel/Bootstrap.php
curl -I https://digisecure.digitech.com.ec/
curl https://digisecure.digitech.com.ec/healthcheck
```

En produccion `/healthcheck` no debe exponer version de PHP, token CSRF ni id de sesion.
