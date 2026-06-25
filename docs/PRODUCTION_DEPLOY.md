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
DB_COLLATION=utf8mb4_0900_ai_ci

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

## 4. Permisos

El usuario del servidor web debe poder escribir en:

```text
var/cache
var/logs
var/sessions
var/storage
```

El directorio `var/storage` debe estar fuera del webroot; en este proyecto ya esta fuera de `public/`.

## 5. Base de datos

Ejecutar migraciones:

```bash
php bin/migrate.php
```

Crear el superadmin inicial con una contrasena temporal fuerte:

```bash
php bin/create-superadmin.php --email=admin@digitech.com.ec
```

El script imprime la contrasena generada si no se pasa `--password`.

## 6. Archivos que no deben subirse

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

## 7. Verificacion minima

```bash
php -l public/index.php
php -l src/Kernel/Bootstrap.php
curl -I https://digisecure.digitech.com.ec/
curl https://digisecure.digitech.com.ec/healthcheck
```

En produccion `/healthcheck` no debe exponer version de PHP, token CSRF ni id de sesion.
