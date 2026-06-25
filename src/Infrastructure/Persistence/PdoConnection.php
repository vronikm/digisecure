<?php
declare(strict_types=1);

namespace DigiSecure\Infrastructure\Persistence;

use DigiSecure\Kernel\Config\Config;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Wrapper de PDO seguro para MySQL 8.
 *
 * Aplica las reglas de oro de DB:
 *  - EMULATE_PREPARES = false  (prepared statements REALES en MySQL)
 *  - ERRMODE_EXCEPTION         (excepciones, jamás silent fail)
 *  - DEFAULT_FETCH_MODE = ASSOC (sin objetos automáticos, sin numérico)
 *  - charset utf8mb4 desde el DSN (no SET NAMES, que es vulnerable)
 *  - sql_mode estricto (rechaza datos truncados, fechas inválidas, etc.)
 *
 * Es un singleton de aplicación: una sola conexión por request,
 * inyectada vía Container.
 *
 * NUNCA exponer el PDO subyacente. Toda interacción pasa por los métodos
 * de esta clase para garantizar que se usen prepared statements.
 */
final class PdoConnection
{
    private ?PDO $pdo = null;

    /** @var array<int, string> SQL ejecutado en la conexión actual (para auditoría/tests) */
    private array $queryLog = [];

    private bool $logQueries = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset = 'utf8mb4',
        private readonly string $collation = 'utf8mb4_unicode_ci',
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            host: $config->string('db.host', '127.0.0.1'),
            port: $config->int('db.port', 3306),
            database: $config->string('db.database', 'digisecure'),
            username: $config->string('db.username', ''),
            password: $config->string('db.password', ''),
            charset: $config->string('db.charset', 'utf8mb4'),
            collation: $config->string('db.collation', 'utf8mb4_unicode_ci'),
        );
    }

    /**
     * Conecta perezosamente. Idempotente.
     */
    public function connect(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = \sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $this->options($this->collation));
        } catch (PDOException $e) {
            if ($this->isUnknownCollationError($e) && $this->collation !== 'utf8mb4_unicode_ci') {
                try {
                    $this->pdo = new PDO($dsn, $this->username, $this->password, $this->options('utf8mb4_unicode_ci'));
                    return $this->pdo;
                } catch (PDOException $retryException) {
                    $e = $retryException;
                }
            }

            // Re-lanzar sin exponer credenciales
            throw new DatabaseException(
                "No se pudo conectar a MySQL en {$this->host}:{$this->port}/{$this->database}: "
                . $e->getMessage(),
                (int)$e->getCode(),
                $e,
            );
        }

        return $this->pdo;
    }

    /**
     * @return array<int, mixed>
     */
    private function options(string $collation): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => \sprintf(
                "SET NAMES %s COLLATE %s, "
                . "sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO', "
                . "time_zone='+00:00'",
                $this->charset,
                $collation,
            ),
        ];
    }

    private function isUnknownCollationError(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'unknown collation')
            || str_contains($message, 'collation')
            || str_contains($message, 'character set');
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Prepara y ejecuta una query con bindings tipados.
     *
     * @param array<string, scalar|null> $bindings
     */
    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $pdo = $this->connect();

        try {
            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $key => $value) {
                $param = $this->normalizeParamName($key);
                $stmt->bindValue($param, $value, $this->paramType($value));
            }
            $stmt->execute();
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Error ejecutando query: {$e->getMessage()}",
                (int)$e->getCode(),
                $e,
            );
        }

        if ($this->logQueries) {
            $this->queryLog[] = $sql;
        }

        return $stmt;
    }

    /**
     * Devuelve la primera fila de una query, o null si no hay resultados.
     *
     * @param array<string, scalar|null> $bindings
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->execute($sql, $bindings)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Devuelve todas las filas de una query.
     *
     * @param array<string, scalar|null> $bindings
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $bindings = []): array
    {
        $rows = $this->execute($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
        return \is_array($rows) ? \array_values($rows) : [];
    }

    /**
     * Devuelve un solo valor escalar (primera columna de la primera fila).
     *
     * @param array<string, scalar|null> $bindings
     */
    public function fetchScalar(string $sql, array $bindings = []): mixed
    {
        $stmt = $this->execute($sql, $bindings);
        $value = $stmt->fetchColumn(0);
        return $value === false ? null : $value;
    }

    /**
     * Cuenta filas afectadas por la última operación.
     */
    public function affectedRows(string $sql, array $bindings = []): int
    {
        return $this->execute($sql, $bindings)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->connect()->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->connect()->beginTransaction();
    }

    public function commit(): void
    {
        $this->connect()->commit();
    }

    public function rollBack(): void
    {
        $this->connect()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->isConnected() && $this->pdo->inTransaction();
    }

    /**
     * Ejecuta una closure dentro de una transacción.
     * Si la closure lanza, hace rollback (si aún hay transacción) y re-lanza.
     *
     * NOTA SOBRE DDL EN MYSQL: las sentencias DDL (CREATE/ALTER/DROP TABLE)
     * provocan un COMMIT implícito en MySQL, lo que cierra la transacción
     * antes de tiempo. Por eso comprobamos `inTransaction()` antes de
     * commit/rollback — si MySQL ya cerró la transacción por su cuenta,
     * no es un error: simplemente no hay nada más que commitear.
     *
     * @template T
     * @param \Closure(self): T $callback
     * @return T
     */
    public function transactional(\Closure $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            if ($this->inTransaction()) {
                $this->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ejecuta SQL crudo sin bindings (solo para DDL/migraciones).
     * NUNCA usar con datos del usuario.
     */
    public function rawExec(string $sql): int
    {
        $pdo = $this->connect();
        try {
            $result = $pdo->exec($sql);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Error ejecutando DDL: {$e->getMessage()}",
                (int)$e->getCode(),
                $e,
            );
        }
        return $result === false ? 0 : $result;
    }

    public function enableQueryLog(): void
    {
        $this->logQueries = true;
    }

    /**
     * @return list<string>
     */
    public function queryLog(): array
    {
        return $this->queryLog;
    }

    private function normalizeParamName(string $key): string
    {
        return str_starts_with($key, ':') ? $key : ":{$key}";
    }

    private function paramType(mixed $value): int
    {
        return match (true) {
            \is_int($value) => PDO::PARAM_INT,
            \is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
