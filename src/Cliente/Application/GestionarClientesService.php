<?php

declare(strict_types=1);

namespace DigiSecure\Cliente\Application;

use DigiSecure\Cliente\Infrastructure\ClienteRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\ValidadorRuc;

/**
 * Caso de uso: gestionar clientes (empresas a las que el tenant presta servicios).
 *
 * Reglas de negocio:
 *  - razon_social obligatoria.
 *  - documento (RUC o cedula) unico por tenant.
 *  - RUC se valida con el algoritmo del SRI; cedula con modulo 10.
 *  - email opcional pero si se provee debe ser valido.
 *  - archivar conserva el historico (estado=archivado), no elimina.
 *
 * Cada operacion deja registro en auditoria con el evento correspondiente.
 */
final class GestionarClientesService
{
    public function __construct(
        private readonly ClienteRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return string ID del cliente creado
     */
    public function crear(array $input, string $usuarioId, string $ip): string
    {
        $data = $this->validar($input);

        if ($this->repo->existeDocumento($data['documento'])) {
            throw new DomainException('Ya existe un cliente con ese documento en su empresa.');
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
            evento: 'cliente.creado',
            usuarioId: $usuarioId,
            entidadTipo: 'cliente',
            entidadId: $id,
            detalles: [
                'razon_social' => $data['razon_social'],
                'documento' => $data['documento'],
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
        $cliente = $this->repo->findById($id);
        if ($cliente === null) {
            throw new DomainException('El cliente no existe o no pertenece a su empresa.');
        }
        if ($cliente['estado'] === 'archivado') {
            throw new DomainException('No se puede editar un cliente archivado. Reactivelo primero.');
        }

        $data = $this->validar($input);

        if ($data['documento'] !== $cliente['documento']
            && $this->repo->existeDocumento($data['documento'], $id)) {
            throw new DomainException('Ya existe otro cliente con ese documento.');
        }

        $data['updated_at'] = Clock::nowString();
        $this->repo->actualizar($id, $data);

        $this->audit->log(
            evento: 'cliente.actualizado',
            usuarioId: $usuarioId,
            entidadTipo: 'cliente',
            entidadId: $id,
            detalles: ['campos' => array_keys($data)],
            ip: $ip,
        );
    }

    /**
     * Cambia el estado: activo / inactivo / archivado.
     * Archivar marca archivado_at y archivado_por; reactivar limpia ambos.
     */
    public function cambiarEstado(string $id, string $nuevoEstado, string $usuarioId, string $ip): void
    {
        if (!\in_array($nuevoEstado, ['activo', 'inactivo', 'archivado'], true)) {
            throw new DomainException('Estado invalido.');
        }

        $cliente = $this->repo->findById($id);
        if ($cliente === null) {
            throw new DomainException('El cliente no existe.');
        }
        if ($cliente['estado'] === $nuevoEstado) {
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
            evento: 'cliente.' . ($nuevoEstado === 'archivado' ? 'archivado' : 'estado_cambiado'),
            usuarioId: $usuarioId,
            entidadTipo: 'cliente',
            entidadId: $id,
            detalles: [
                'estado_anterior' => $cliente['estado'],
                'estado_nuevo' => $nuevoEstado,
                'razon_social' => $cliente['razon_social'],
            ],
            nivel: 'warning',
            ip: $ip,
        );
    }

    /**
     * Valida y normaliza la entrada del usuario.
     *
     * @param array<string, mixed> $input
     * @return array<string, scalar|null>
     */
    private function validar(array $input): array
    {
        $razonSocial = trim((string) ($input['razon_social'] ?? ''));
        if ($razonSocial === '' || mb_strlen($razonSocial) > 255) {
            throw new DomainException('La razon social es obligatoria (max 255 caracteres).');
        }

        $tipoDoc = strtoupper(trim((string) ($input['tipo_documento'] ?? 'RUC')));
        if (!\in_array($tipoDoc, ['RUC', 'CEDULA'], true)) {
            throw new DomainException('Tipo de documento invalido.');
        }

        $documento = preg_replace('/\D+/', '', (string) ($input['documento'] ?? '')) ?? '';
        if ($tipoDoc === 'RUC') {
            if (mb_strlen($documento) !== 13) {
                throw new DomainException('El RUC debe tener 13 digitos.');
            }
            if (!ValidadorRuc::esValido($documento)) {
                throw new DomainException('El RUC no es valido segun el algoritmo del SRI.');
            }
        } else {
            if (mb_strlen($documento) !== 10) {
                throw new DomainException('La cedula debe tener 10 digitos.');
            }
            if (!ValidadorRuc::esCedulaValida($documento)) {
                throw new DomainException('La cedula no es valida segun el algoritmo del Registro Civil.');
            }
        }

        $email = trim((string) ($input['contacto_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El email de contacto debe ser valido.');
        }

        $optional = static fn(string $key, int $maxLen = 255): ?string => (function () use ($input, $key, $maxLen): ?string {
            $v = trim((string) ($input[$key] ?? ''));
            if ($v === '') return null;
            return mb_strlen($v) > $maxLen ? mb_substr($v, 0, $maxLen) : $v;
        })();

        return [
            'razon_social'      => $razonSocial,
            'nombre_comercial'  => $optional('nombre_comercial', 255),
            'tipo_documento'    => $tipoDoc,
            'documento'         => $documento,
            'contacto_nombre'   => $optional('contacto_nombre', 200),
            'contacto_cargo'    => $optional('contacto_cargo', 120),
            'contacto_email'    => $email !== '' ? $email : null,
            'contacto_telefono' => $optional('contacto_telefono', 30),
            'direccion'         => $optional('direccion', 255),
            'ciudad'            => $optional('ciudad', 100),
            'provincia'         => $optional('provincia', 100),
            'pais'              => strtoupper(trim((string) ($input['pais'] ?? 'EC'))) ?: 'EC',
            'sector'            => $optional('sector', 80),
            'notas'             => $optional('notas', 5000),
        ];
    }
}
