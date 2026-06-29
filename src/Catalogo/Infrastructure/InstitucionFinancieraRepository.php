<?php

declare(strict_types=1);

namespace DigiSecure\Catalogo\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;

/**
 * Repositorio del catálogo GLOBAL de instituciones financieras.
 *
 * No es multi-tenant (como catalogo_cursos): son entidades nacionales
 * compartidas. Por eso usa PdoConnection directamente y NO BaseRepository.
 *
 * Orden estándar: primero bancos, luego cooperativas; dentro de cada grupo
 * por `orden` y luego alfabético.
 */
final class InstitucionFinancieraRepository
{
    private const ORDER = "FIELD(tipo, 'BANCO', 'COOPERATIVA'), orden, nombre";

    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(string $q = ''): array
    {
        $q = trim($q);
        if ($q !== '') {
            return $this->db->fetchAll(
                'SELECT * FROM instituciones_financieras WHERE nombre LIKE :q ORDER BY ' . self::ORDER,
                ['q' => '%' . $q . '%'],
            );
        }

        return $this->db->fetchAll('SELECT * FROM instituciones_financieras ORDER BY ' . self::ORDER);
    }

    /**
     * Instituciones activas para selects (campo Banco de la ficha).
     *
     * @return list<array<string, mixed>>
     */
    public function listActivas(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nombre, tipo FROM instituciones_financieras WHERE activo = 1 ORDER BY ' . self::ORDER,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM instituciones_financieras WHERE id = :id',
            ['id' => $id],
        );
    }

    public function existsByNombre(string $nombre, string $exceptId = ''): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM instituciones_financieras WHERE nombre = :nombre AND id <> :id LIMIT 1',
            ['nombre' => $nombre, 'id' => $exceptId],
        );

        return $row !== null;
    }

    /**
     * @param array{id: string, nombre: string, tipo: string, orden: int, activo: int} $data
     */
    public function create(array $data): void
    {
        $this->db->execute(
            'INSERT INTO instituciones_financieras (id, nombre, tipo, orden, activo, created_at)
             VALUES (:id, :nombre, :tipo, :orden, :activo, NOW(6))',
            [
                'id' => $data['id'],
                'nombre' => $data['nombre'],
                'tipo' => $data['tipo'],
                'orden' => $data['orden'],
                'activo' => $data['activo'],
            ],
        );
    }

    /**
     * @param array{nombre: string, tipo: string, orden: int, activo: int} $data
     */
    public function update(string $id, array $data): void
    {
        $this->db->execute(
            'UPDATE instituciones_financieras
                SET nombre = :nombre, tipo = :tipo, orden = :orden, activo = :activo, updated_at = NOW(6)
              WHERE id = :id',
            [
                'id' => $id,
                'nombre' => $data['nombre'],
                'tipo' => $data['tipo'],
                'orden' => $data['orden'],
                'activo' => $data['activo'],
            ],
        );
    }

    public function delete(string $id): void
    {
        $this->db->execute('DELETE FROM instituciones_financieras WHERE id = :id', ['id' => $id]);
    }
}
