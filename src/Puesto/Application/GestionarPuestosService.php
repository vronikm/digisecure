<?php

declare(strict_types=1);

namespace DigiSecure\Puesto\Application;

use DigiSecure\Puesto\Domain\Enum\ModalidadPuesto;
use DigiSecure\Puesto\Domain\Enum\TipoPuesto;
use DigiSecure\Puesto\Infrastructure\PuestoRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Caso de uso: gestionar puestos / sitios donde el tenant presta servicios.
 *
 * Reglas:
 *  - Cliente debe existir, pertenecer al tenant y estar no archivado.
 *  - Nombre obligatorio.
 *  - codigo (si se provee) debe ser unico por tenant.
 *  - num_guardias_requeridos minimo 1.
 *  - Coordenadas opcionales pero si se proveen deben estar en rangos validos.
 *  - Archivar conserva historico (estado=archivado).
 */
final class GestionarPuestosService
{
    public function __construct(
        private readonly PuestoRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return string ID del puesto creado
     */
    public function crear(array $input, string $usuarioId, string $ip): string
    {
        $data = $this->validar($input);

        if (!$this->repo->clienteEsValido($data['cliente_id'])) {
            throw new DomainException('El cliente seleccionado no existe o esta archivado.');
        }

        if ($data['codigo'] !== null && $this->repo->existeCodigo($data['codigo'])) {
            throw new DomainException('Ya existe un puesto con ese codigo en su empresa.');
        }

        $id = IdGenerator::ulid();
        $now = Clock::nowString();

        $row = $data + [
            'id' => $id,
            'estado' => 'activo',
            'archivado_at' => null,
            'archivado_por' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->repo->crear($row);

        $this->audit->log(
            evento: 'puesto.creado',
            usuarioId: $usuarioId,
            entidadTipo: 'puesto',
            entidadId: $id,
            detalles: [
                'nombre' => $data['nombre'],
                'cliente_id' => $data['cliente_id'],
                'tipo' => $data['tipo'],
            ],
            ip: $ip,
        );

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function actualizar(string $id, array $input, string $usuarioId, string $ip): void
    {
        $puesto = $this->repo->findById($id);
        if ($puesto === null) {
            throw new DomainException('El puesto no existe o no pertenece a su empresa.');
        }
        if ($puesto['estado'] === 'archivado') {
            throw new DomainException('No se puede editar un puesto archivado. Reactivelo primero.');
        }

        $data = $this->validar($input);

        if (!$this->repo->clienteEsValido($data['cliente_id'])) {
            throw new DomainException('El cliente seleccionado no existe o esta archivado.');
        }

        if ($data['codigo'] !== null
            && $data['codigo'] !== $puesto['codigo']
            && $this->repo->existeCodigo($data['codigo'], $id)) {
            throw new DomainException('Ya existe otro puesto con ese codigo.');
        }

        $data['updated_at'] = Clock::nowString();
        $this->repo->actualizar($id, $data);

        $this->audit->log(
            evento: 'puesto.actualizado',
            usuarioId: $usuarioId,
            entidadTipo: 'puesto',
            entidadId: $id,
            detalles: ['campos' => array_keys($data)],
            ip: $ip,
        );
    }

    public function cambiarEstado(string $id, string $nuevoEstado, string $usuarioId, string $ip): void
    {
        if (!\in_array($nuevoEstado, ['activo', 'inactivo', 'archivado'], true)) {
            throw new DomainException('Estado invalido.');
        }

        $puesto = $this->repo->findById($id);
        if ($puesto === null) {
            throw new DomainException('El puesto no existe.');
        }
        if ($puesto['estado'] === $nuevoEstado) {
            return;
        }

        $now = Clock::nowString();
        $data = ['estado' => $nuevoEstado, 'updated_at' => $now];

        if ($nuevoEstado === 'archivado') {
            $data['archivado_at'] = $now;
            $data['archivado_por'] = $usuarioId;
        } else {
            $data['archivado_at'] = null;
            $data['archivado_por'] = null;
        }

        $this->repo->actualizar($id, $data);

        $this->audit->log(
            evento: 'puesto.' . ($nuevoEstado === 'archivado' ? 'archivado' : 'estado_cambiado'),
            usuarioId: $usuarioId,
            entidadTipo: 'puesto',
            entidadId: $id,
            detalles: [
                'estado_anterior' => $puesto['estado'],
                'estado_nuevo' => $nuevoEstado,
                'nombre' => $puesto['nombre'],
            ],
            nivel: 'warning',
            ip: $ip,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, scalar|null>
     */
    private function validar(array $input): array
    {
        $clienteId = trim((string) ($input['cliente_id'] ?? ''));
        if ($clienteId === '') {
            throw new DomainException('Debe seleccionar un cliente.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw new DomainException('El nombre del puesto es obligatorio (max 200 caracteres).');
        }

        $codigo = trim((string) ($input['codigo'] ?? ''));
        if ($codigo !== '' && mb_strlen($codigo) > 50) {
            throw new DomainException('El codigo no puede superar 50 caracteres.');
        }

        $tipo = strtolower(trim((string) ($input['tipo'] ?? 'oficina')));
        if (TipoPuesto::tryFrom($tipo) === null) {
            throw new DomainException('Tipo de puesto invalido.');
        }

        $modalidad = strtolower(trim((string) ($input['modalidad'] ?? 'fijo')));
        if (ModalidadPuesto::tryFrom($modalidad) === null) {
            throw new DomainException('Modalidad invalida.');
        }

        $numGuardias = (int) ($input['num_guardias_requeridos'] ?? 1);
        if ($numGuardias < 1 || $numGuardias > 999) {
            throw new DomainException('El numero de guardias requeridos debe estar entre 1 y 999.');
        }

        $latitud = $this->parseCoord($input['latitud'] ?? null, -90.0, 90.0, 'Latitud');
        $longitud = $this->parseCoord($input['longitud'] ?? null, -180.0, 180.0, 'Longitud');

        $optional = static fn(string $key, int $maxLen) => (function () use ($input, $key, $maxLen): ?string {
            $v = trim((string) ($input[$key] ?? ''));
            if ($v === '') return null;
            return mb_strlen($v) > $maxLen ? mb_substr($v, 0, $maxLen) : $v;
        })();

        return [
            'cliente_id'              => $clienteId,
            'codigo'                  => $codigo !== '' ? $codigo : null,
            'nombre'                  => $nombre,
            'tipo'                    => $tipo,
            'modalidad'               => $modalidad,
            'direccion'               => $optional('direccion', 255),
            'ciudad'                  => $optional('ciudad', 100),
            'provincia'               => $optional('provincia', 100),
            'pais'                    => strtoupper(trim((string) ($input['pais'] ?? 'EC'))) ?: 'EC',
            'latitud'                 => $latitud,
            'longitud'                => $longitud,
            'horario_descripcion'     => $optional('horario_descripcion', 255),
            'num_guardias_requeridos' => $numGuardias,
            'contacto_sitio'          => $optional('contacto_sitio', 200),
            'telefono_sitio'          => $optional('telefono_sitio', 30),
            'observaciones'           => $optional('observaciones', 5000),
        ];
    }

    private function parseCoord(mixed $raw, float $min, float $max, string $label): ?string
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }
        if (!is_numeric($raw)) {
            throw new DomainException("{$label} debe ser un numero.");
        }
        $val = (float) $raw;
        if ($val < $min || $val > $max) {
            throw new DomainException("{$label} fuera de rango ({$min} a {$max}).");
        }
        return (string) $val;
    }
}
