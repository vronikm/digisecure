<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: recuperación lógica de una ficha eliminada.
 *
 * Regla aplicada:
 * - Solo se recuperan fichas en estado ANULADA.
 * - Al recuperar, la ficha pasa a BORRADOR para revisión manual.
 */
final class RecuperarFichaService
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

        $estadoActual = EstadoFicha::from($ficha['estado']);
        if ($estadoActual !== EstadoFicha::ANULADA) {
            throw new DomainException('Solo se pueden recuperar fichas eliminadas.');
        }

        $this->fichaRepo->update($fichaId, [
            'estado' => EstadoFicha::BORRADOR->value,
            'updated_by' => $usuarioId,
        ]);

        $this->audit->log(
            'ficha.recuperar',
            $usuarioId,
            'ficha',
            $fichaId,
            detalles: [
                'estado_anterior' => $estadoActual->value,
                'estado_nuevo' => EstadoFicha::BORRADOR->value,
                'correlativo' => (string) ($ficha['correlativo'] ?? ''),
            ],
            nivel: 'warning',
            ip: $ip,
        );
    }
}

