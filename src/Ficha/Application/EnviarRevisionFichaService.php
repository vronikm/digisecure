<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Caso de uso: enviar ficha a revisión.
 *
 * Regla:
 * - Solo fichas en BORRADOR pueden pasar a EN_REVISION.
 */
final class EnviarRevisionFichaService
{
    public function __construct(
        private readonly FichaRepository $fichaRepo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @throws DomainException
     */
    public function execute(string $fichaId, string $usuarioId, string $ip): void
    {
        $ficha = $this->fichaRepo->findFichaById($fichaId);
        if ($ficha === null) {
            throw new DomainException('Ficha no encontrada.');
        }

        $estadoActual = EstadoFicha::from((string) $ficha['estado']);
        if ($estadoActual !== EstadoFicha::BORRADOR) {
            throw new DomainException(
                "Solo se puede enviar a revisión una ficha en estado 'Borrador'. Estado actual: '{$estadoActual->label()}'."
            );
        }

        $this->fichaRepo->update($fichaId, [
            'estado' => EstadoFicha::EN_REVISION->value,
            'fecha_revision' => Clock::now()->format('Y-m-d'),
            'updated_by' => $usuarioId,
        ]);

        $this->audit->log(
            'ficha.enviar_revision',
            $usuarioId,
            'ficha',
            $fichaId,
            detalles: [
                'estado_anterior' => EstadoFicha::BORRADOR->value,
                'estado_nuevo' => EstadoFicha::EN_REVISION->value,
                'correlativo' => (string) ($ficha['correlativo'] ?? ''),
            ],
            nivel: 'info',
            ip: $ip,
        );
    }
}

