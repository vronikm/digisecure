<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Revision de aspirantes por un reclutador/admin (Fase 4).
 *
 * Aprobar crea un `usuario` activo en el tenant del aprobador, reutilizando el
 * email + password_hash que el aspirante ya definio (asi puede iniciar sesion).
 * El rol asignado es configurable: por defecto NINGUNO (minimo privilegio) para
 * no exponer datos de la empresa a un postulante; un admin asigna el rol luego.
 *
 * Rechazar marca al aspirante como 'descartado' (con motivo opcional).
 *
 * Ambas operaciones son transaccionales y auditadas.
 */
final class GestionarAspirantesService
{
    public function __construct(
        private readonly AspiranteRepository $repo,
        private readonly PdoConnection $db,
        private readonly AuditLogger $audit,
        private readonly string $rolAprobacionCodigo = '',
    ) {
    }

    /**
     * Aprueba al aspirante y crea su cuenta de usuario.
     *
     * @return string ID del usuario creado.
     */
    public function aprobar(string $aspiranteId, string $tenantId, string $adminId, string $ip): string
    {
        $a = $this->repo->findDetalle($aspiranteId);
        if ($a === null) {
            throw new DomainException('Aspirante no encontrado.');
        }
        if ((int) ($a['email_verificado'] ?? 0) !== 1) {
            throw new DomainException('No se puede aprobar: el aspirante aún no ha verificado su correo.');
        }
        if (($a['estado'] ?? '') === 'aprobado') {
            throw new DomainException('Este aspirante ya fue aprobado.');
        }
        if (($a['estado'] ?? '') === 'descartado') {
            throw new DomainException('Este aspirante fue descartado; no puede aprobarse.');
        }
        if ($tenantId === '') {
            throw new DomainException('No se pudo determinar la empresa destino del usuario.');
        }
        $tenantDestino = (string) ($this->repo->tenantGemeseg() ?? '');
        if ($tenantDestino === '') {
            $tenantDestino = trim((string) ($a['tenant_id'] ?? ''));
        }
        if ($tenantDestino === '') {
            $tenantDestino = $tenantId;
        }

        $now = Clock::nowString();
        $roleId = $this->rolAprobacionCodigo !== ''
            ? $this->repo->buscarRolId($this->rolAprobacionCodigo, $tenantDestino)
            : null;
        $usuarioExistente = ($a['usuario_id'] ?? null) !== null ? (string) $a['usuario_id'] : null;

        if ($usuarioExistente !== null) {
            // PROMOVER: el aspirante ya validó su correo y tiene una cuenta
            // restringida. Aprobar levanta el confinamiento (quita el rol
            // 'aspirante') y le asigna el rol de aprobación si está configurado.
            $this->db->transactional(function () use ($usuarioExistente, $tenantDestino, $roleId, $adminId, $now, $aspiranteId): void {
                $rolAspirante = $this->repo->buscarRolId('aspirante', $tenantDestino);
                if ($rolAspirante !== null) {
                    $this->repo->quitarRol($usuarioExistente, $rolAspirante);
                }
                if ($roleId !== null) {
                    $this->repo->asignarRol($usuarioExistente, $roleId, $adminId, $now);
                }
                // El usuario y la ficha que completó (creados en el tenant de
                // plataforma) se trasladan al tenant de la empresa, para que el
                // empleado aparezca en Empleados/Guardias de esa empresa.
                $this->repo->moverUsuarioYFichasATenant($usuarioExistente, $tenantDestino);
                $this->repo->marcarAprobado($aspiranteId, $usuarioExistente, $tenantDestino, $adminId, $now);
            });

            $this->audit->log(
                'aspirante.aprobado',
                $adminId,
                'aspirante',
                $aspiranteId,
                detalles: ['usuario_id' => $usuarioExistente, 'accion' => 'promovido', 'tenant_destino' => $tenantDestino, 'rol' => $this->rolAprobacionCodigo ?: null],
                ip: $ip,
            );

            return $usuarioExistente;
        }

        // CREAR: aspirante verificado sin cuenta aún (camino de respaldo).
        if ($this->repo->existeCorreoEnUsuarios((string) $a['correo_normalizado'])) {
            throw new DomainException('Ya existe un usuario del sistema con este correo.');
        }

        $usuarioId = IdGenerator::ulid();
        $this->db->transactional(function () use ($a, $usuarioId, $tenantDestino, $adminId, $roleId, $now, $aspiranteId): void {
            $this->repo->crearUsuario([
                'id'                => $usuarioId,
                'tenant_id'         => $tenantDestino,
                'email'             => (string) $a['correo'],
                'email_normalizado' => (string) $a['correo_normalizado'],
                'password_hash'     => (string) $a['password_hash'],
                'nombres'           => (string) $a['nombres'],
                'apellidos'         => (string) $a['apellidos'],
                'telefono'          => $a['celular'] !== null ? (string) $a['celular'] : null,
                'cargo'             => 'Aspirante aprobado',
                'created_by'        => $adminId,
                'cuando'            => $now,
            ]);

            if ($roleId !== null) {
                $this->repo->asignarRol($usuarioId, $roleId, $adminId, $now);
            }

            $this->repo->marcarAprobado($aspiranteId, $usuarioId, $tenantDestino, $adminId, $now);
        });

        $this->audit->log(
            'aspirante.aprobado',
            $adminId,
            'aspirante',
            $aspiranteId,
            detalles: ['usuario_id' => $usuarioId, 'accion' => 'creado', 'rol' => $this->rolAprobacionCodigo ?: null],
            ip: $ip,
        );

        return $usuarioId;
    }

    public function rechazar(string $aspiranteId, string $motivo, string $adminId, string $ip): void
    {
        $a = $this->repo->findDetalle($aspiranteId);
        if ($a === null) {
            throw new DomainException('Aspirante no encontrado.');
        }
        if (($a['estado'] ?? '') === 'aprobado') {
            throw new DomainException('No se puede rechazar un aspirante que ya fue aprobado.');
        }
        if (($a['estado'] ?? '') === 'descartado') {
            throw new DomainException('Este aspirante ya estaba descartado.');
        }

        $motivo = trim($motivo);
        $this->repo->marcarDescartado(
            $aspiranteId,
            $motivo !== '' ? mb_substr($motivo, 0, 255) : null,
            $adminId,
            Clock::nowString(),
        );

        $this->audit->log(
            'aspirante.descartado',
            $adminId,
            'aspirante',
            $aspiranteId,
            detalles: ['motivo' => $motivo !== '' ? $motivo : null],
            nivel: 'warning',
            ip: $ip,
        );
    }
}
