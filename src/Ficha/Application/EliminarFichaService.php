<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: eliminación lógica de una ficha.
 *
 * En lugar de borrado físico, la ficha pasa a estado ANULADA para preservar
 * trazabilidad y cumplir con reglas de auditoría.
 */
final class EliminarFichaService
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
        if ($estadoActual === EstadoFicha::ANULADA) {
            throw new DomainException('La ficha ya se encuentra eliminada.');
        }

        $this->fichaRepo->update($fichaId, [
            'estado' => EstadoFicha::ANULADA->value,
            'updated_by' => $usuarioId,
        ]);

        $this->audit->log(
            'ficha.eliminar',
            $usuarioId,
            'ficha',
            $fichaId,
            detalles: [
                'estado_anterior' => $estadoActual->value,
                'correlativo' => (string) ($ficha['correlativo'] ?? ''),
            ],
            nivel: 'warning',
            ip: $ip,
        );
    }
}
