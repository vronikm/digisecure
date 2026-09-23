<?php
/**
 * Cambio de clave de cifrado: pasa los datos cifrados con ENCRYPTION_KEY_ANTERIOR
 * a la ENCRYPTION_KEY actual y recalcula sus indices (cedula_hash).
 *
 * Casos de uso:
 *  - Rotacion de ENCRYPTION_KEY.
 *  - Una base copiada entre entornos con claves distintas (p. ej. desarrollo
 *    subido a produccion): las filas cifradas con la clave del otro entorno
 *    dejan de descifrarse.
 *
 * Por cada valor cifrado:
 *  - descifra con la clave actual   -> no se toca (al dia)
 *  - descifra con la clave anterior -> se vuelve a cifrar con la actual
 *  - no descifra con ninguna        -> se reporta y no se toca
 *
 * Usa el propio dato cifrado como fuente: no confia en otras tablas. No
 * imprime valores ni claves. `updated_at` no cambia (es un cambio tecnico).
 *
 * Uso:
 *   1. Agregar temporalmente al .env: ENCRYPTION_KEY_ANTERIOR=<clave hex anterior>
 *   2. php bin/recifrar-datos.php --dry-run
 *   3. php bin/recifrar-datos.php --apply
 *   4. php bin/rehash-cedulas.php --apply   (indices de filas que ya estaban al dia)
 *   5. Quitar ENCRYPTION_KEY_ANTERIOR del .env
 *
 * Opcional: --env=/ruta/a/otro.env (por defecto, el .env del proyecto).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: este script solo se ejecuta desde CLI.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

use DigiSecure\Infrastructure\Import\GemesegExcelImportService;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Security\Crypto\BlindIndex;
use DigiSecure\Security\Crypto\Encryption;

$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);
$envPath = dirname(__DIR__) . '/.env';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--env=')) {
        $envPath = substr($arg, strlen('--env='));
    }
}

if ($apply === $dryRun) {
    fwrite(STDERR, "Uso: php bin/recifrar-datos.php --dry-run | --apply [--env=/ruta/.env]\n");
    exit(1);
}
if (!is_file($envPath)) {
    fwrite(STDERR, "No existe el archivo de entorno: {$envPath}\n");
    exit(1);
}

$env = EnvLoader::load($envPath);
date_default_timezone_set($env['APP_TIMEZONE'] ?? 'America/Guayaquil');

$claveActual = trim((string) ($env['ENCRYPTION_KEY'] ?? ''));
$claveAnterior = trim((string) ($env['ENCRYPTION_KEY_ANTERIOR'] ?? ''));
if ($claveAnterior === '') {
    fwrite(STDERR, "Falta ENCRYPTION_KEY_ANTERIOR en el .env (la clave con la que se cifraron los datos a migrar).\n");
    exit(1);
}
if (strcasecmp($claveAnterior, $claveActual) === 0) {
    fwrite(STDERR, "ENCRYPTION_KEY_ANTERIOR es igual a ENCRYPTION_KEY: no hay nada que cambiar.\n");
    exit(1);
}

try {
    $actual = Encryption::fromHexKey($claveActual);
    $anterior = Encryption::fromHexKey($claveAnterior);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'Clave invalida: ' . $e->getMessage() . "\n");
    exit(1);
}
$blindIndex = $actual->blindIndexer();

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

// Columnas cifradas sin AAD con la clave maestra. `hash`: su blind index, si tiene.
$columnas = [
    ['tabla' => 'fichas', 'columna' => 'cedula_cifrada', 'hash' => 'cedula_hash'],
    ['tabla' => 'fichas', 'columna' => 'cuenta_bancaria_cifrada', 'hash' => null],
    ['tabla' => 'fichas_familiares', 'columna' => 'cedula_cifrada', 'hash' => 'cedula_hash'],
    ['tabla' => 'usuarios', 'columna' => 'cedula_cifrada', 'hash' => 'cedula_hash'],
    ['tabla' => 'usuarios', 'columna' => 'mfa_secret_cifrado', 'hash' => null],
];

echo str_repeat('=', 78) . PHP_EOL;
echo 'DigiSecure :: Cambio de clave de cifrado' . PHP_EOL;
echo 'Modo: ' . ($apply ? 'APPLY' : 'dry-run') . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;
printf("%-44s %6s %7s %10s %10s\n", 'COLUMNA', 'FILAS', 'AL DIA', $apply ? 'RECIFRADO' : 'RECIFRAR', 'NO DESCIF.');

$totales = ['recifrados' => 0, 'no_descifrables' => 0];

$db->transactional(function (PdoConnection $conn) use ($columnas, $actual, $anterior, $blindIndex, $apply, &$totales): void {
    foreach ($columnas as ['tabla' => $tabla, 'columna' => $columna, 'hash' => $hash]) {
        if (!columnaExiste($conn, $tabla, $columna)) {
            continue;
        }
        $rows = $conn->fetchAll(
            "SELECT id, {$columna} AS valor" . ($hash !== null ? ", {$hash} AS hash_actual" : '') . "
               FROM {$tabla}
              WHERE {$columna} IS NOT NULL AND {$columna} <> ''",
        );
        $stats = ['filas' => count($rows), 'al_dia' => 0, 'recifrados' => 0, 'no_descifrables' => 0];

        foreach ($rows as $row) {
            $valor = (string) $row['valor'];
            if (descifra($actual, $valor, '')) {
                $stats['al_dia']++;
                continue;
            }
            $plano = descifrar($anterior, $valor, '');
            if ($plano === null) {
                $stats['no_descifrables']++;
                continue;
            }

            $sets = ["{$columna} = :nuevo"];
            $bind = ['nuevo' => $actual->encrypt($plano), 'id' => (string) $row['id'], 'viejo' => $valor];
            if ($hash !== null) {
                try {
                    $bind['hash'] = $blindIndex->cedula($plano);
                    $sets[] = "{$hash} = :hash";
                } catch (InvalidArgumentException) {
                    // Valor sin digitos: se re-cifra, el indice queda como estaba.
                }
            }

            $stats['recifrados']++;
            if ($apply) {
                $conn->execute(
                    "UPDATE {$tabla} SET " . implode(', ', $sets) . " WHERE id = :id AND {$columna} = :viejo",
                    $bind,
                );
            }
        }

        imprimir("{$tabla}.{$columna}", $stats);
        $totales['recifrados'] += $stats['recifrados'];
        $totales['no_descifrables'] += $stats['no_descifrables'];
    }

    // Staging GEMESEG: clave derivada por tenant + AAD por fila.
    if (columnaExiste($conn, 'importacion_excel_registros', 'raw_json_cifrado')) {
        $rows = $conn->fetchAll(
            'SELECT id, tenant_id, fuente, hoja, fila, raw_json_cifrado AS valor FROM importacion_excel_registros',
        );
        $stats = ['filas' => count($rows), 'al_dia' => 0, 'recifrados' => 0, 'no_descifrables' => 0];
        foreach ($rows as $row) {
            $tenantId = (string) $row['tenant_id'];
            // Mismo AAD que GemesegExcelImportService / GemesegStagingProcessorService.
            $aad = "{$tenantId}|gemeseg_excel|{$row['fuente']}|{$row['hoja']}|{$row['fila']}";
            $valor = (string) $row['valor'];
            $actualTenant = $actual->deriveTenantKey($tenantId);
            if (descifra($actualTenant, $valor, $aad)) {
                $stats['al_dia']++;
                continue;
            }
            $json = descifrar($anterior->deriveTenantKey($tenantId), $valor, $aad);
            if ($json === null) {
                $stats['no_descifrables']++;
                continue;
            }
            $payload = json_decode($json, true);
            $data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];

            $stats['recifrados']++;
            if ($apply) {
                $conn->execute(
                    'UPDATE importacion_excel_registros
                        SET raw_json_cifrado = :nuevo, cedula_hash = :hash
                      WHERE id = :id AND raw_json_cifrado = :viejo',
                    [
                        'nuevo' => $actualTenant->encrypt($json, $aad),
                        'hash' => GemesegExcelImportService::cedulaInfo($data, $blindIndex)['hash'],
                        'id' => (string) $row['id'],
                        'viejo' => $valor,
                    ],
                );
            }
        }
        imprimir('importacion_excel_registros.raw_json_cifrado', $stats);
        $totales['recifrados'] += $stats['recifrados'];
        $totales['no_descifrables'] += $stats['no_descifrables'];
    }
});

echo str_repeat('-', 78) . PHP_EOL;
if ($apply) {
    echo "Valores recifrados con la clave actual: {$totales['recifrados']}.\n";
    echo "Siguiente: php bin/rehash-cedulas.php --dry-run  y luego quitar ENCRYPTION_KEY_ANTERIOR del .env.\n";
} elseif ($totales['recifrados'] > 0) {
    echo "{$totales['recifrados']} valor(es) se recifrarian. Ejecuta con --apply.\n";
} else {
    echo "No hay valores cifrados con la clave anterior.\n";
}
if ($totales['no_descifrables'] > 0) {
    echo "ATENCION: {$totales['no_descifrables']} valor(es) no descifran con ninguna de las dos claves y no se tocaron.\n";
}

function descifra(Encryption $enc, string $valor, string $aad): bool
{
    return descifrar($enc, $valor, $aad) !== null;
}

function descifrar(Encryption $enc, string $valor, string $aad): ?string
{
    try {
        return $enc->decrypt($valor, $aad);
    } catch (Throwable) {
        return null;
    }
}

function columnaExiste(PdoConnection $conn, string $tabla, string $columna): bool
{
    return (int) $conn->fetchScalar(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
        ['t' => $tabla, 'c' => $columna],
    ) > 0;
}

/**
 * @param array{filas: int, al_dia: int, recifrados: int, no_descifrables: int} $stats
 */
function imprimir(string $etiqueta, array $stats): void
{
    printf(
        "%-44s %6d %7d %10d %10d\n",
        $etiqueta,
        $stats['filas'],
        $stats['al_dia'],
        $stats['recifrados'],
        $stats['no_descifrables'],
    );
}
