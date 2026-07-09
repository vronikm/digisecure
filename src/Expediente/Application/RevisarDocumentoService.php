<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Application;

use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: Revisar (aceptar/rechazar) un documento del expediente.
 *
 * Al aceptar/rechazar se recalcula el progreso del expediente:
 *  - bloque_a_aceptados / bloque_b_aceptados
 *  - Si Bloque A llega a 100%, desbloquea Bloque B
 *  - Si ambos bloques están completos, estado = COMPLETO
 */
final class RevisarDocumentoService
{
    public function __construct(
        private readonly ExpedienteRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @throws DomainException
     */
    public function execute(
        string $documentoId,
        string $accion, // 'aceptar' | 'rechazar'
        string $observacion,
        string $usuarioId,
        string $ip,
    ): void {
        $doc = $this->repo->findDocumentoById($documentoId);
        if ($doc === null) {
            throw new DomainException('Documento no encontrado.');
        }

        $estadoActual = EstadoDocumento::from($doc['estado']);
        if (!in_array($estadoActual, [EstadoDocumento::EN_REVISION, EstadoDocumento::RECHAZADO, EstadoDocumento::VENCIDO], true)) {
            throw new DomainException("No se puede revisar un documento en estado '{$estadoActual->label()}'.");
        }

        $nuevoEstado = match ($accion) {
            'aceptar' => EstadoDocumento::ACEPTADO,
            'rechazar' => EstadoDocumento::RECHAZADO,
            default => throw new DomainException("Acción no válida: {$accion}."),
        };

        // Actualizar documento
        $this->repo->updateDocumento($documentoId, [
            'estado' => $nuevoEstado->value,
            'observacion_revisor' => $observacion !== '' ? $observacion : null,
            'revisado_por' => $usuarioId,
            'revisado_at' => date('Y-m-d H:i:s.u'),
        ]);

        // Recalcular progreso del expediente
        $expedienteId = $doc['expediente_id'];
        $this->recalcularProgreso($expedienteId);

        // Auditoría
        $this->audit->log(
            "expediente.documento.{$accion}",
            $usuarioId,
            'expediente_documento',
            $documentoId,
            detalles: [
                'expediente_id' => $expedienteId,
                'tipo_codigo' => $doc['tipo_codigo'],
                'observacion' => $observacion,
            ],
            ip: $ip,
        );
    }

    /**
     * Subir screenshots de validación externa.
     */
    public function guardarScreenshots(
        string $documentoId,
        ?string $screenshot1Storage,
        ?string $screenshot2Storage,
        ?string $validacionUrl,
        string $usuarioId,
        string $ip,
    ): void {
        $doc = $this->repo->findDocumentoById($documentoId);
        if ($doc === null) {
            throw new DomainException('Documento no encontrado.');
        }

        $data = [];
        if ($screenshot1Storage !== null) {
            $data['screenshot_1_storage'] = $screenshot1Storage;
        }
        if ($screenshot2Storage !== null) {
            $data['screenshot_2_storage'] = $screenshot2Storage;
        }
        if ($validacionUrl !== null) {
            $data['validacion_url'] = $validacionUrl;
        }

        if ($data !== []) {
            $this->repo->updateDocumento($documentoId, $data);

            $this->audit->log(
                'expediente.documento.validacion_externa',
                $usuarioId,
                'expediente_documento',
                $documentoId,
                detalles: ['expediente_id' => $doc['expediente_id'], 'campos' => array_keys($data)],
                ip: $ip,
            );
        }
    }

    private function recalcularProgreso(string $expedienteId): void
    {
        $expediente = $this->repo->findExpedienteById($expedienteId);
        if ($expediente === null) {
            return;
        }

        $resumenA = $this->repo->resumenActivosByBloque($expedienteId, 'A');
        $resumenB = $this->repo->resumenActivosByBloque($expedienteId, 'B');

        $aceptadosA = $resumenA['aceptados'];
        $aceptadosB = $resumenB['aceptados'];
        $totalA = $resumenA['total'];
        $totalB = $resumenB['total'];

        $bloqueACompleto = $totalA > 0 && $aceptadosA >= $totalA;
        $bloqueBCompleto = $totalB === 0 || $aceptadosB >= $totalB;

        // Determinar estado
        if ($bloqueACompleto && $bloqueBCompleto) {
            $estado = EstadoExpediente::COMPLETO;
        } elseif ($bloqueACompleto) {
            $estado = EstadoExpediente::BLOQUE_A_COMPLETO;
        } else {
            $estado = EstadoExpediente::INCOMPLETO;
        }

        $this->repo->updateExpediente($expedienteId, [
            'bloque_a_aceptados' => $aceptadosA,
            'bloque_b_aceptados' => $aceptadosB,
            'bloque_a_total' => $totalA,
            'bloque_b_total' => $totalB,
            'bloque_b_desbloqueado' => $bloqueACompleto ? 1 : 0,
            'estado' => $estado->value,
        ]);
    }
}
