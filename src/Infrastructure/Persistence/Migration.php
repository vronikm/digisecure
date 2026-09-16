<?php
declare(strict_types=1);

namespace DigiSecure\Infrastructure\Persistence;

/**
 * Representa un archivo de migración SQL parseado.
 *
 * Formato esperado del archivo:
 *
 *     -- Migration: descripción legible
 *     -- @up
 *     CREATE TABLE ... ;
 *     ALTER TABLE ... ;
 *     -- @down
 *     DROP TABLE ... ;
 *
 * El nombre del archivo determina el orden y debe seguir el formato:
 *     NNN_descripcion_corta.sql
 *     ej: 001_initial_security_core.sql
 */
final class Migration
{
    public function __construct(
        public readonly string $version,    // "001"
        public readonly string $name,       // "initial_security_core"
        public readonly string $filename,   // "001_initial_security_core.sql"
        public readonly string $upSql,
        public readonly string $downSql,
        public readonly string $checksum,   // sha256 del contenido completo con finales de línea LF
    ) {
    }

    public static function fromFile(string $absolutePath): self
    {
        $filename = basename($absolutePath);
        if (!preg_match('/^(\d{3,})_([a-z0-9_]+)\.sql$/i', $filename, $m)) {
            throw new MigrationException(
                "Nombre de migración inválido: '{$filename}'. Formato esperado: NNN_descripcion.sql"
            );
        }

        $version = $m[1];
        $name = $m[2];

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new MigrationException("No se pudo leer la migración: {$absolutePath}");
        }

        // Git en Windows (core.autocrlf=true) entrega CRLF; el repo y Linux usan LF.
        // Normalizar evita CHECKSUM_MISMATCH falsos entre entornos.
        $contents = self::normalizeLineEndings($contents);

        [$up, $down] = self::parseSections($contents, $filename);

        return new self(
            version: $version,
            name: $name,
            filename: $filename,
            upSql: trim($up),
            downSql: trim($down),
            checksum: hash('sha256', $contents),
        );
    }

    public static function normalizeLineEndings(string $contents): string
    {
        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function parseSections(string $contents, string $filename): array
    {
        // Buscar marcadores -- @up y -- @down
        $upPos = self::findMarker($contents, '@up');
        $downPos = self::findMarker($contents, '@down');

        if ($upPos === null) {
            throw new MigrationException("Migración '{$filename}' no contiene marcador '-- @up'");
        }

        if ($downPos === null) {
            // Permitido: migración sin rollback
            $up = substr($contents, $upPos);
            return [self::stripMarkerLine($up), ''];
        }

        if ($downPos < $upPos) {
            throw new MigrationException(
                "Migración '{$filename}': '-- @down' aparece antes de '-- @up'"
            );
        }

        $up = substr($contents, $upPos, $downPos - $upPos);
        $down = substr($contents, $downPos);

        return [self::stripMarkerLine($up), self::stripMarkerLine($down)];
    }

    private static function findMarker(string $contents, string $marker): ?int
    {
        // Match líneas que empiezan con "-- " y contienen el marker
        $pattern = '/^--\s*' . preg_quote($marker, '/') . '\s*$/m';
        if (preg_match($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return $matches[0][1];
        }
        return null;
    }

    private static function stripMarkerLine(string $section): string
    {
        // Quita la primera línea (que es el marcador)
        $newlinePos = strpos($section, "\n");
        return $newlinePos === false ? '' : substr($section, $newlinePos + 1);
    }
}
