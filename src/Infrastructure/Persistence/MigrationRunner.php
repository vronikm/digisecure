<?php
declare(strict_types=1);

namespace DigiSecure\Infrastructure\Persistence;

/**
 * Ejecutor de migraciones SQL versionadas.
 *
 * Comandos:
 *  - status()    : muestra el estado de cada migración (aplicada / pendiente)
 *  - migrate()   : aplica todas las pendientes en orden
 *  - rollback()  : revierte la última aplicada
 *  - rollbackAll(): revierte todas (peligroso, solo dev)
 *
 * Estado persistido en tabla `migraciones`:
 *   version, name, filename, checksum, applied_at
 *
 * Idempotencia garantizada por la PK (version) y un check de checksum:
 * si una migración ya aplicada se modifica en disco, se detecta y se aborta.
 *
 * NO depende de PdoConnection directamente para tablas tenant-aware:
 * `migraciones` es una tabla GLOBAL (sin tenant_id).
 */
final class MigrationRunner
{
    private const MIGRATIONS_TABLE = 'migraciones';

    public function __construct(
        private readonly PdoConnection $db,
        private readonly string $migrationsPath,
    ) {
    }

    public function ensureMigrationsTable(): void
    {
        $sql = sprintf(
            "CREATE TABLE IF NOT EXISTS %s (
                version VARCHAR(10) NOT NULL,
                name VARCHAR(150) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME(6) NOT NULL,
                execution_ms INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            self::MIGRATIONS_TABLE,
        );
        $this->db->rawExec($sql);
    }

    /**
     * @return list<array{migration: Migration, status: string, appliedAt: ?string}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $files = $this->discoverMigrations();
        $applied = $this->loadAppliedMigrations();

        $result = [];
        foreach ($files as $migration) {
            $appliedRow = $applied[$migration->version] ?? null;

            if ($appliedRow === null) {
                $status = 'pending';
                $appliedAt = null;
            } elseif ($appliedRow['checksum'] !== $migration->checksum) {
                $status = 'CHECKSUM_MISMATCH';
                $appliedAt = $appliedRow['applied_at'];
            } else {
                $status = 'applied';
                $appliedAt = $appliedRow['applied_at'];
            }

            $result[] = [
                'migration' => $migration,
                'status' => $status,
                'appliedAt' => $appliedAt,
            ];
        }

        return $result;
    }

    /**
     * @return list<Migration> migraciones efectivamente aplicadas en esta ejecución
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $files = $this->discoverMigrations();
        $applied = $this->loadAppliedMigrations();

        $executed = [];

        foreach ($files as $migration) {
            $existing = $applied[$migration->version] ?? null;

            if ($existing !== null) {
                if ($existing['checksum'] !== $migration->checksum) {
                    throw new MigrationException(
                        "La migración {$migration->version} ('{$migration->filename}') ya fue aplicada "
                        . "pero su contenido cambió en disco. Esto rompe el historial. "
                        . "NO modifiques migraciones aplicadas; crea una nueva."
                    );
                }
                continue;
            }

            if (trim($migration->upSql) === '') {
                throw new MigrationException(
                    "La migración {$migration->filename} no tiene contenido en la sección @up"
                );
            }

            $start = microtime(true);

            // Las migraciones se ejecutan dentro de una transacción
            // (MySQL solo soporta DDL transaccional parcial pero los DML van bien;
            //  para CREATE TABLE el rollback no funciona, así que cada migración
            //  debe ser idempotente o ir con cuidado).
            $this->db->transactional(function (PdoConnection $db) use ($migration, $start) {
                // Ejecutar todas las sentencias del @up
                foreach ($this->splitStatements($migration->upSql) as $stmt) {
                    if (trim($stmt) === '') {
                        continue;
                    }
                    $db->rawExec($stmt);
                }

                $elapsedMs = (int)round((microtime(true) - $start) * 1000);

                $db->execute(
                    sprintf(
                        "INSERT INTO %s (version, name, filename, checksum, applied_at, execution_ms)
                         VALUES (:version, :name, :filename, :checksum, :applied_at, :execution_ms)",
                        self::MIGRATIONS_TABLE,
                    ),
                    [
                        'version' => $migration->version,
                        'name' => $migration->name,
                        'filename' => $migration->filename,
                        'checksum' => $migration->checksum,
                        'applied_at' => date('Y-m-d H:i:s.u'),
                        'execution_ms' => $elapsedMs,
                    ],
                );
            });

            $executed[] = $migration;
        }

        return $executed;
    }

    public function rollbackLast(): ?Migration
    {
        $this->ensureMigrationsTable();

        $row = $this->db->fetchOne(
            sprintf(
                "SELECT version, name, filename, checksum FROM %s ORDER BY version DESC LIMIT 1",
                self::MIGRATIONS_TABLE,
            ),
        );

        if ($row === null) {
            return null;
        }

        $files = $this->discoverMigrations();
        $migration = null;
        foreach ($files as $m) {
            if ($m->version === $row['version']) {
                $migration = $m;
                break;
            }
        }

        if ($migration === null) {
            throw new MigrationException(
                "Migración {$row['version']} aplicada pero el archivo no existe en disco. "
                . "No se puede hacer rollback."
            );
        }

        if (trim($migration->downSql) === '') {
            throw new MigrationException(
                "La migración {$migration->filename} no tiene sección @down. No se puede revertir."
            );
        }

        $this->db->transactional(function (PdoConnection $db) use ($migration) {
            foreach ($this->splitStatements($migration->downSql) as $stmt) {
                if (trim($stmt) === '') {
                    continue;
                }
                $db->rawExec($stmt);
            }

            $db->execute(
                sprintf("DELETE FROM %s WHERE version = :version", self::MIGRATIONS_TABLE),
                ['version' => $migration->version],
            );
        });

        return $migration;
    }

    /**
     * @return list<Migration>
     */
    private function discoverMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.sql');
        if ($files === false || $files === []) {
            return [];
        }

        sort($files, SORT_STRING);

        $migrations = [];
        $seenVersions = [];

        foreach ($files as $file) {
            $migration = Migration::fromFile($file);

            if (isset($seenVersions[$migration->version])) {
                throw new MigrationException(
                    "Versión duplicada {$migration->version}: "
                    . "'{$seenVersions[$migration->version]}' y '{$migration->filename}'"
                );
            }
            $seenVersions[$migration->version] = $migration->filename;

            $migrations[] = $migration;
        }

        return $migrations;
    }

    /**
     * @return array<string, array{version: string, name: string, filename: string, checksum: string, applied_at: string}>
     */
    private function loadAppliedMigrations(): array
    {
        $rows = $this->db->fetchAll(
            sprintf(
                "SELECT version, name, filename, checksum, applied_at FROM %s",
                self::MIGRATIONS_TABLE,
            ),
        );

        $byVersion = [];
        foreach ($rows as $row) {
            $byVersion[(string)$row['version']] = [
                'version' => (string)$row['version'],
                'name' => (string)$row['name'],
                'filename' => (string)$row['filename'],
                'checksum' => (string)$row['checksum'],
                'applied_at' => (string)$row['applied_at'],
            ];
        }

        return $byVersion;
    }

    /**
     * Divide un bloque SQL en sentencias individuales por `;`.
     *
     * Cuidado: este splitter es básico y NO soporta:
     *  - DELIMITER changes (procedures, triggers)
     *  - strings con `;` dentro
     *  - comentarios multilinea con `;` adentro
     *
     * Para Fase 0 nos sirve. Si alguna migración futura necesita procedures,
     * usaremos un parser más robusto o ejecutaremos la migración como bloque.
     *
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $cleaned = $this->stripSqlComments($sql);
        $statements = explode(';', $cleaned);
        return array_values(array_filter(array_map('trim', $statements), static fn($s) => $s !== ''));
    }

    private function stripSqlComments(string $sql): string
    {
        // Remover comentarios -- ... (línea completa) y /* ... */ (bloque)
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        return $sql;
    }
}
