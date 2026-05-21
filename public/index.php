<?php
/**
 * =====================================================================
 * DigiSecure - Front Controller (único punto de entrada HTTP)
 * =====================================================================
 *
 * NUNCA exponer otros archivos PHP fuera de este `public/`.
 * Configurar Apache/Nginx para que el DocumentRoot apunte a este
 * directorio. Todo el resto del proyecto vive arriba del webroot.
 * =====================================================================
 */

declare(strict_types=1);

// Path absoluto a la raíz del proyecto (un nivel arriba de public/)
define('DIGISECURE_ROOT', dirname(__DIR__));
define('DIGISECURE_START', microtime(true));

// Autoload de Composer (PSR-4)
$autoloadPath = DIGISECURE_ROOT . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "DigiSecure: dependencias no instaladas. Ejecuta `composer install` en la raíz del proyecto.\n";
    exit(1);
}
/** @var mixed $loader */
$loader = require $autoloadPath;

// Fallback de namespaces de módulos (hasta regenerar autoload con Composer).
if ($loader instanceof \Composer\Autoload\ClassLoader) {
    $loader->addPsr4('DigiSecure\\Superadmin\\', DIGISECURE_ROOT . '/src/Superadmin');
    $loader->addPsr4('DigiSecure\\Cliente\\', DIGISECURE_ROOT . '/src/Cliente');
    $loader->addPsr4('DigiSecure\\Puesto\\', DIGISECURE_ROOT . '/src/Puesto');
    $loader->addPsr4('DigiSecure\\Asignacion\\', DIGISECURE_ROOT . '/src/Asignacion');
}

// Verificar archivo .env
if (!is_file(DIGISECURE_ROOT . '/.env')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "DigiSecure: archivo .env no encontrado. Copia .env.example a .env y completa los valores.\n";
    exit(1);
}

// Arrancar el Kernel
(new \DigiSecure\Kernel\Bootstrap(DIGISECURE_ROOT))->run();
