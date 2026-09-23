<?php
/**
 * Informa cuantos datos cifrados NO se pueden leer con la ENCRYPTION_KEY
 * actual. Solo lectura: no modifica nada y no imprime valores ni claves.
 *
 * Sirve para saber si hace falta `php bin/recifrar-datos.php` (base copiada
 * entre entornos o clave rotada) antes de que el usuario vea "No disponible"
 * en cedulas y cuentas bancarias.
 *
 * Uso:
 *   php bin/diagnostico-cifrado.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: este script solo se ejecuta desde CLI.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Security\Crypto\Encryption;

$root = dirname(__DIR__);
$env = EnvLoader::load($root . '/.env');
date_default_timezone_set($env['APP_TIMEZONE'] ?? 'America/Guayaquil');

$db = PdoConnection::fromConfig(new Config([
    'db' => [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => $env['DB_PORT'] ?? '3306',
        'database' => $env['DB_DATABASE'] ?? 'digisecure',
        'username' => $env['DB_USERNAME'] ?? '',
        'password' => $env['DB_PASSWORD'] ?? '',
        'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ],
]));

$encryption = Encryption::fromHexKey((string) ($env['ENCRYPTION_KEY'] ?? ''));

/** Columnas cifradas con la clave maestra y sin AAD. */
$columnas = [
    ['fichas', 'cedula_cifrada'],
    ['fichas', 'cuenta_bancaria_cifrada'],
    ['fichas_familiares', 'cedula_cifrada'],
    ['usuarios', 'cedula_cifrada'],
    ['usuarios', 'mfa_secret_cifrado'],
];

$columnaExiste = static function (PdoConnection $conn, string $tabla, string $columna): bool {
    try {
        return $conn->fetchOne(
            'SELECT 1 AS existe
               FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c',
            ['t' => $tabla, 'c' => $columna],
        ) !== null;
    } catch (\Throwable) {
        return false;
    }
};

echo str_repeat('=', 78) . PHP_EOL;
echo 'DigiSecure :: Diagnostico de datos cifrados (solo lectura)' . PHP_EOL;
echo 'BD: ' . ($env['DB_DATABASE'] ?? '?') . '@' . ($env['DB_HOST'] ?? '?') . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;
printf("%-28s %-26s %8s %8s %8s\n", 'TABLA', 'COLUMNA', 'FILAS', 'LEGIBLE', 'ILEGIBLE');

$totalIlegibles = 0;

foreach ($columnas as [$tabla, $columna]) {
    if (!$columnaExiste($db, $tabla, $columna)) {
        printf("%-28s %-26s %8s\n", $tabla, $columna, 'n/d');
        continue;
    }

    $filas = $db->fetchAll(
        "SELECT {$columna} AS valor FROM {$tabla} WHERE {$columna} IS NOT NULL AND {$columna} <> ''",
    );

    $ok = 0;
    $fallan = 0;
    foreach ($filas as $fila) {
        try {
            $encryption->decrypt((string) $fila['valor']);
            $ok++;
        } catch (\Throwable) {
            $fallan++;
        }
    }

    $totalIlegibles += $fallan;
    printf("%-28s %-26s %8d %8d %8d\n", $tabla, $columna, count($filas), $ok, $fallan);
}

// Staging GEMESEG: clave derivada por tenant + AAD por fila.
if ($columnaExiste($db, 'importacion_excel_registros', 'raw_json_cifrado')) {
    $filas = $db->fetchAll(
        'SELECT tenant_id, fuente, hoja, fila, raw_json_cifrado AS valor FROM importacion_excel_registros',
    );
    $ok = 0;
    $fallan = 0;
    $clavesTenant = [];
    foreach ($filas as $fila) {
        $tenantId = (string) $fila['tenant_id'];
        $clavesTenant[$tenantId] ??= $encryption->deriveTenantKey($tenantId);
        $aad = "{$tenantId}|gemeseg_excel|{$fila['fuente']}|{$fila['hoja']}|{$fila['fila']}";
        try {
            $clavesTenant[$tenantId]->decrypt((string) $fila['valor'], $aad);
            $ok++;
        } catch (\Throwable) {
            $fallan++;
        }
    }
    $totalIlegibles += $fallan;
    printf("%-28s %-26s %8d %8d %8d\n", 'importacion_excel_registros', 'raw_json_cifrado', count($filas), $ok, $fallan);
}

echo str_repeat('-', 78) . PHP_EOL;

if ($totalIlegibles === 0) {
    echo 'Todo se descifra con la clave actual. No hay nada que recifrar.' . PHP_EOL;
    exit(0);
}

echo "Hay {$totalIlegibles} valor(es) cifrado(s) con otra clave: se muestran como 'No disponible'." . PHP_EOL;
echo 'Para recuperarlos, poner ENCRYPTION_KEY_ANTERIOR en el .env (la clave con la' . PHP_EOL;
echo 'que se cifraron) y ejecutar:' . PHP_EOL;
echo '    php bin/recifrar-datos.php --dry-run' . PHP_EOL;
echo '    php bin/recifrar-datos.php --apply' . PHP_EOL;
echo 'Despues, retirar ENCRYPTION_KEY_ANTERIOR del .env.' . PHP_EOL;
exit(2);
