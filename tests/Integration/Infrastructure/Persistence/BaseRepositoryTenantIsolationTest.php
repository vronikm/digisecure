<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Integration\Infrastructure\Persistence;

use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Tenant\Domain\TenantContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración: aislamiento multi-tenant en BaseRepository.
 *
 * Requiere MySQL con la BD de desarrollo. Se ejecuta como parte del
 * testsuite "Integration". Se salta si la BD no está disponible.
 *
 * Usa una tabla temporal con estructura tenant-aware para no contaminar
 * datos reales.
 */
final class BaseRepositoryTenantIsolationTest extends TestCase
{
    private ?PdoConnection $db = null;

    protected function setUp(): void
    {
        $envFile = dirname(__DIR__, 4) . '/.env';
        if (!is_file($envFile)) {
            self::markTestSkipped('.env no encontrado — requiere entorno con BD configurada.');
        }

        $envVars = EnvLoader::load($envFile);
        $config = new Config([
            'db' => [
                'host' => $envVars['DB_HOST'] ?? '127.0.0.1',
                'port' => $envVars['DB_PORT'] ?? '3306',
                'database' => $envVars['DB_DATABASE'] ?? 'digisecure',
                'username' => $envVars['DB_USERNAME'] ?? '',
                'password' => $envVars['DB_PASSWORD'] ?? '',
                'charset' => $envVars['DB_CHARSET'] ?? 'utf8mb4',
                'collation' => $envVars['DB_COLLATION'] ?? 'utf8mb4_0900_ai_ci',
            ],
        ]);

        try {
            $this->db = PdoConnection::fromConfig($config);
            $this->db->rawExec(
                'CREATE TEMPORARY TABLE __test_tenant_iso (
                    id CHAR(26) NOT NULL PRIMARY KEY,
                    tenant_id CHAR(26) NOT NULL,
                    nombre VARCHAR(100) NOT NULL,
                    KEY ix_test_tenant (tenant_id)
                 ) ENGINE=InnoDB'
            );
        } catch (\Throwable $e) {
            self::markTestSkipped('No se pudo conectar a MySQL: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->db?->isConnected()) {
            $this->db->rawExec('DROP TEMPORARY TABLE IF EXISTS __test_tenant_iso');
        }
    }

    private function buildRepo(string $tenantId): TestableRepository
    {
        $ctx = new TenantContext();
        $ctx->set($tenantId, 'Test', 'activo');
        return new TestableRepository($this->db, $ctx);
    }

    #[Test]
    public function insert_agrega_tenant_id_automaticamente(): void
    {
        $repo = $this->buildRepo('TENANT_AAA');
        $repo->doInsert(['id' => 'REC001', 'nombre' => 'Alfa']);

        $row = $this->db->fetchOne(
            'SELECT tenant_id, nombre FROM __test_tenant_iso WHERE id = :id',
            ['id' => 'REC001'],
        );

        self::assertNotNull($row);
        self::assertSame('TENANT_AAA', $row['tenant_id']);
        self::assertSame('Alfa', $row['nombre']);
    }

    #[Test]
    public function query_filtra_por_tenant(): void
    {
        // Insertar datos de 2 tenants directamente
        $this->db->execute(
            "INSERT INTO __test_tenant_iso (id, tenant_id, nombre) VALUES
                ('A1', 'T1', 'Uno-T1'),
                ('A2', 'T1', 'Dos-T1'),
                ('B1', 'T2', 'Uno-T2')",
        );

        $repoT1 = $this->buildRepo('T1');
        $repoT2 = $this->buildRepo('T2');

        $rowsT1 = $repoT1->doQuery('SELECT id, nombre FROM __test_tenant_iso ORDER BY id');
        $rowsT2 = $repoT2->doQuery('SELECT id, nombre FROM __test_tenant_iso ORDER BY id');

        self::assertCount(2, $rowsT1);
        self::assertSame('Uno-T1', $rowsT1[0]['nombre']);
        self::assertSame('Dos-T1', $rowsT1[1]['nombre']);

        self::assertCount(1, $rowsT2);
        self::assertSame('Uno-T2', $rowsT2[0]['nombre']);
    }

    #[Test]
    public function update_no_puede_afectar_otro_tenant(): void
    {
        $this->db->execute(
            "INSERT INTO __test_tenant_iso (id, tenant_id, nombre) VALUES
                ('X1', 'T1', 'Original'),
                ('X1_CLON', 'T2', 'Intocable')",
        );

        // Tenant T1 intenta actualizar: solo debe afectar sus registros
        $repoT1 = $this->buildRepo('T1');
        $affected = $repoT1->doUpdate('X1', ['nombre' => 'Cambiado']);
        self::assertSame(1, $affected);

        // Intentar actualizar ID que pertenece a T2 → 0 filas
        $affected2 = $repoT1->doUpdate('X1_CLON', ['nombre' => 'Hackeado']);
        self::assertSame(0, $affected2);

        // Verificar que T2 no fue afectado
        $row = $this->db->fetchOne('SELECT nombre FROM __test_tenant_iso WHERE id = :id', ['id' => 'X1_CLON']);
        self::assertSame('Intocable', $row['nombre']);
    }

    #[Test]
    public function count_filtra_por_tenant(): void
    {
        $this->db->execute(
            "INSERT INTO __test_tenant_iso (id, tenant_id, nombre) VALUES
                ('C1', 'T1', 'a'), ('C2', 'T1', 'b'), ('C3', 'T2', 'c')",
        );

        $repoT1 = $this->buildRepo('T1');
        $repoT2 = $this->buildRepo('T2');

        self::assertSame(2, $repoT1->doCount());
        self::assertSame(1, $repoT2->doCount());
    }

    #[Test]
    public function findById_no_retorna_registro_de_otro_tenant(): void
    {
        $this->db->execute(
            "INSERT INTO __test_tenant_iso (id, tenant_id, nombre) VALUES ('F1', 'T1', 'MiRegistro')",
        );

        $repoT2 = $this->buildRepo('T2');
        self::assertNull($repoT2->doFindById('F1'));

        $repoT1 = $this->buildRepo('T1');
        self::assertNotNull($repoT1->doFindById('F1'));
    }
}

/**
 * Subclase concreta de BaseRepository para testing.
 * Expone los métodos protegidos a través de wrappers públicos.
 *
 * @internal Solo para tests
 */
final class TestableRepository extends BaseRepository
{
    protected function tableName(): string
    {
        return '__test_tenant_iso';
    }

    /** @param array<string, scalar|null> $data */
    public function doInsert(array $data): void
    {
        $this->tenantInsert($data);
    }

    /** @return list<array<string, mixed>> */
    public function doQuery(string $sql, array $bindings = []): array
    {
        return $this->tenantQuery($sql, $bindings);
    }

    public function doUpdate(string $id, array $data): int
    {
        return $this->tenantUpdate($id, $data);
    }

    public function doCount(string $where = '', array $bindings = []): int
    {
        return $this->tenantCount($where, $bindings);
    }

    public function doFindById(string $id): ?array
    {
        return $this->findById($id);
    }
}
