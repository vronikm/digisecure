<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Application;

use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Security\Audit\AuditLogger;

/**
 * Caso de uso: Verificar documentos vencidos y marcarlos como VENCIDO.
 *
 * Diseñado para ejecutarse como job diario (cron).
 * Busca documentos con estado ACEPTADO cuya fecha_vencimiento < hoy
 * y los marca como VENCIDO, recalculando el progreso del expediente.
 */
final class VerificarVencimientosService
{
    public function __construct(
        private readonly ExpedienteRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return int Número de documentos marcados como vencidos
     */
    public function execute(string $ip = '0.0.0.0'): int
    {
        $vencidos = $this->repo->getDocumentosVencidos();
        $count = 0;
        $expedientesAfectados = [];

        foreach ($vencidos as $doc) {
            $this->repo->updateDocumento($doc['id'], [
                'estado' => EstadoDocumento::VENCIDO->value,
            ]);

            $this->audit->log(
                'expediente.documento.vencido_auto',
                'SYSTEM',
                'expediente_documento',
                $doc['id'],
                detalles: [
                    'expediente_id' => $doc['expediente_id'],
                    'fecha_vencimiento' => $doc['fecha_vencimiento'],
                ],
                nivel: 'warning',
                ip: $ip,
            );

            $expedientesAfectados[$doc['expediente_id']] = true;
            $count++;
        }

        // Recalcular progreso de expedientes afectados
        foreach (array_keys($expedientesAfectados) as $expId) {
            $this->recalcularProgreso($expId);
        }

        return $count;
    }

    private function recalcularProgreso(string $expedienteId): void
    {
        $expediente = $this->repo->findExpedienteById($expedienteId);
        if ($expediente === null) {
            return;
        }

        $aceptadosA = $this->repo->countAceptadosByBloque($expedienteId, 'A');
        $aceptadosB = $this->repo->countAceptadosByBloque($expedienteId, 'B');

        $totalA = (int) $expediente['bloque_a_total'];
        $totalB = (int) $expediente['bloque_b_total'];

        $bloqueACompleto = $totalA > 0 && $aceptadosA >= $totalA;
        $bloqueBCompleto = $totalB > 0 && $aceptadosB >= $totalB;

        if ($bloqueACompleto && $bloqueBCompleto) {
            $estado = 'COMPLETO';
        } elseif ($bloqueACompleto) {
            $estado = 'BLOQUE_A_COMPLETO';
        } else {
            $estado = 'INCOMPLETO';
        }

        $this->repo->updateExpediente($expedienteId, [
            'bloque_a_aceptados' => $aceptadosA,
            'bloque_b_aceptados' => $aceptadosB,
            'estado' => $estado,
        ]);
    }
}
