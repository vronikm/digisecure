<?php

declare(strict_types=1);

namespace DigiSecure\Security\Lopdp\Application;

use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Responde o deniega una solicitud ARCO-P y deja trazabilidad LOPDP.
 *
 * Estados destino:
 *   - EN_PROCESO: el responsable la tomó pero aún no resuelve.
 *   - RESPONDIDA: se entregó respuesta al titular.
 *   - DENEGADA: se rechazó (debe registrarse motivo).
 */
final class ResponderSolicitudArcopService
{
    private const ESTADOS_FINALES = ['RESPONDIDA', 'DENEGADA'];
    private const ESTADOS_VALIDOS = ['EN_PROCESO', 'RESPONDIDA', 'DENEGADA'];

    public function __construct(
        private readonly SolicitudArcopRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(string $solicitudId, array $input, string $usuarioId, string $ip): void
    {
        $solicitud = $this->repo->findById($solicitudId);
        if ($solicitud === null) {
            throw new DomainException('Solicitud no encontrada.');
        }
        if (in_array($solicitud['estado'], self::ESTADOS_FINALES, true)) {
            throw new DomainException('La solicitud ya está cerrada y no puede modificarse.');
        }

        $estado = (string)($input['estado'] ?? '');
        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) {
            throw new DomainException('Estado destino inválido.');
        }

        $data = ['estado' => $estado];

        if ($estado === 'RESPONDIDA') {
            $respuesta = trim((string)($input['respuesta_texto'] ?? ''));
            if (mb_strlen($respuesta) < 10) {
                throw new DomainException('La respuesta debe tener al menos 10 caracteres.');
            }
            $data['respuesta_texto'] = $respuesta;
            $data['respondida_at'] = Clock::nowString();
            $data['respondida_por'] = $usuarioId;
        } elseif ($estado === 'DENEGADA') {
            $motivo = trim((string)($input['denegada_motivo'] ?? ''));
            if (mb_strlen($motivo) < 10) {
                throw new DomainException('El motivo de denegación debe tener al menos 10 caracteres.');
            }
            $data['denegada_motivo'] = $motivo;
            $data['respondida_at'] = Clock::nowString();
            $data['respondida_por'] = $usuarioId;
        }

        $this->repo->actualizar($solicitudId, $data);

        $this->audit->log(
            'lopdp.solicitud_arcop_' . strtolower($estado),
            $usuarioId,
            'lopdp_solicitud',
            $solicitudId,
            ['estado' => $estado],
            nivel: $estado === 'DENEGADA' ? 'warning' : 'info',
            ip: $ip,
        );
    }
}
