<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Application;

use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Notification\Application\EncolarNotificacionService;
use DigiSecure\Notification\Domain\Enum\CanalNotificacion;
use DigiSecure\Notification\Domain\Enum\PrioridadNotificacion;
use DigiSecure\Security\Audit\AuditLogger;

/**
 * Caso de uso: Verificar documentos vencidos y por vencer.
 *
 * Diseñado para ejecutarse como job diario (cron). Realiza dos pasadas:
 *   1. Marca como VENCIDO los documentos ACEPTADO cuya fecha_vencimiento ya
 *      paso, recalcula progreso del expediente y encola notificaciones.
 *   2. Detecta documentos proximos a vencer (<= N dias) y encola
 *      notificaciones de aviso (idempotentes via dedupe_key).
 */
final class VerificarVencimientosService
{
    public function __construct(
        private readonly ExpedienteRepository $repo,
        private readonly AuditLogger $audit,
        private readonly ?EncolarNotificacionService $encolar = null,
        private readonly bool $whatsappEnabled = false,
    ) {
    }

    /**
     * @return array{vencidos:int, por_vencer:int, notificaciones:int}
     */
    public function execute(string $ip = '0.0.0.0', int $diasAlerta = 30): array
    {
        $vencidos = $this->repo->getDocumentosVencidos();
        $count = 0;
        $notifs = 0;
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

            $notifs += $this->enqueueVencido($doc, $ip);

            $expedientesAfectados[$doc['expediente_id']] = true;
            $count++;
        }

        // Recalcular progreso de expedientes afectados
        foreach (array_keys($expedientesAfectados) as $expId) {
            $this->recalcularProgreso($expId);
        }

        // Segunda pasada: documentos proximos a vencer
        $porVencer = $this->repo->getDocumentosPorVencer($diasAlerta);
        foreach ($porVencer as $doc) {
            $notifs += $this->enqueuePorVencer($doc, $ip);
        }

        return [
            'vencidos' => $count,
            'por_vencer' => count($porVencer),
            'notificaciones' => $notifs,
        ];
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function enqueueVencido(array $doc, string $ip): int
    {
        if ($this->encolar === null) {
            return 0;
        }

        $tipoNombre = (string) ($doc['tipo_nombre'] ?? 'Documento');
        $correlativo = (string) ($doc['correlativo'] ?? '');
        $fechaVenc = (string) ($doc['fecha_vencimiento'] ?? '');

        $asunto = "[DigiSecure] Documento vencido: {$tipoNombre} ({$correlativo})";
        $cuerpo = "El documento \"{$tipoNombre}\" del guardia {$correlativo} vencio el {$fechaVenc} "
            . 'y ha sido marcado como VENCIDO automaticamente. Debe ser reemplazado para mantener '
            . 'el expediente vigente.';

        return $this->enqueueForRecipients(
            $doc,
            evento: 'expediente.documento.vencido',
            asunto: $asunto,
            cuerpo: $cuerpo,
            prioridad: PrioridadNotificacion::ALTA,
            dedupePrefix: 'doc_vencido:' . $doc['id'] . ':' . date('Y-m-d'),
            payload: [
                'documento_id' => $doc['id'],
                'expediente_id' => $doc['expediente_id'] ?? null,
                'tipo' => $tipoNombre,
                'correlativo' => $correlativo,
                'fecha_vencimiento' => $fechaVenc,
            ],
            ip: $ip,
        );
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function enqueuePorVencer(array $doc, string $ip): int
    {
        if ($this->encolar === null) {
            return 0;
        }

        $tipoNombre = (string) ($doc['tipo_nombre'] ?? 'Documento');
        $correlativo = (string) ($doc['correlativo'] ?? '');
        $dias = (int) ($doc['dias_restantes'] ?? 0);
        $fechaVenc = (string) ($doc['fecha_vencimiento'] ?? '');

        $asunto = "[DigiSecure] Documento por vencer en {$dias} dias: {$tipoNombre} ({$correlativo})";
        $cuerpo = "El documento \"{$tipoNombre}\" del guardia {$correlativo} vencera el {$fechaVenc} "
            . "({$dias} dias restantes). Coordine la renovacion antes de esa fecha.";

        return $this->enqueueForRecipients(
            $doc,
            evento: 'expediente.documento.por_vencer',
            asunto: $asunto,
            cuerpo: $cuerpo,
            prioridad: $dias <= 7 ? PrioridadNotificacion::ALTA : PrioridadNotificacion::NORMAL,
            dedupePrefix: 'doc_por_vencer:' . $doc['id'] . ':' . date('Y-m-d'),
            payload: [
                'documento_id' => $doc['id'],
                'expediente_id' => $doc['expediente_id'] ?? null,
                'tipo' => $tipoNombre,
                'correlativo' => $correlativo,
                'fecha_vencimiento' => $fechaVenc,
                'dias_restantes' => $dias,
            ],
            ip: $ip,
        );
    }

    /**
     * Encola notificaciones para guardia y administrador, por canal.
     *
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $payload
     */
    private function enqueueForRecipients(
        array $doc,
        string $evento,
        string $asunto,
        string $cuerpo,
        PrioridadNotificacion $prioridad,
        string $dedupePrefix,
        array $payload,
        string $ip,
    ): int {
        if ($this->encolar === null) {
            return 0;
        }

        $creadas = 0;
        foreach ($this->resolveRecipients($doc) as $target) {
            if ($target['email'] !== null) {
                $id = $this->encolar->execute(
                    [
                        'evento' => $evento,
                        'asunto' => $asunto,
                        'cuerpo_texto' => $cuerpo,
                        'prioridad' => $prioridad,
                        'canal' => CanalNotificacion::EMAIL,
                        'destinatario_nombre' => $target['nombre'],
                        'destinatario_email' => $target['email'],
                        'entidad_tipo' => 'expediente_documento',
                        'entidad_id' => (string) $doc['id'],
                        'dedupe_key' => $this->buildDedupeKey(
                            $dedupePrefix,
                            $target['scope'],
                            CanalNotificacion::EMAIL,
                            $target['email'],
                        ),
                        'payload' => $payload + [
                            'destinatario_scope' => $target['scope'],
                            'canal' => CanalNotificacion::EMAIL->value,
                        ],
                    ],
                    usuarioId: 'SYSTEM',
                    ip: $ip,
                );
                if ($id !== null) {
                    $creadas++;
                }
            }

            if ($this->whatsappEnabled && $target['telefono'] !== null) {
                $id = $this->encolar->execute(
                    [
                        'evento' => $evento,
                        'asunto' => $asunto,
                        'cuerpo_texto' => $cuerpo,
                        'prioridad' => $prioridad,
                        'canal' => CanalNotificacion::WHATSAPP,
                        'destinatario_nombre' => $target['nombre'],
                        'destinatario_telefono' => $target['telefono'],
                        'entidad_tipo' => 'expediente_documento',
                        'entidad_id' => (string) $doc['id'],
                        'dedupe_key' => $this->buildDedupeKey(
                            $dedupePrefix,
                            $target['scope'],
                            CanalNotificacion::WHATSAPP,
                            $target['telefono'],
                        ),
                        'payload' => $payload + [
                            'destinatario_scope' => $target['scope'],
                            'canal' => CanalNotificacion::WHATSAPP->value,
                        ],
                    ],
                    usuarioId: 'SYSTEM',
                    ip: $ip,
                );
                if ($id !== null) {
                    $creadas++;
                }
            }
        }

        return $creadas;
    }

    /**
     * @param array<string, mixed> $doc
     * @return list<array{scope:string,nombre:string,email:?string,telefono:?string}>
     */
    private function resolveRecipients(array $doc): array
    {
        $guardiaNombre = trim(
            (string) ($doc['nombres'] ?? '')
            . ' '
            . (string) ($doc['apellidos'] ?? '')
        );
        if ($guardiaNombre === '') {
            $guardiaNombre = (string) ($doc['correlativo'] ?? 'Guardia');
        }

        $tenantNombre = trim((string) ($doc['tenant_nombre'] ?? ''));
        if ($tenantNombre === '') {
            $tenantNombre = 'Tenant';
        }

        $targets = [
            [
                'scope' => 'guardia',
                'nombre' => $guardiaNombre,
                'email' => $this->sanitizeEmail($doc['guardia_email'] ?? null),
                'telefono' => $this->sanitizePhone($doc['guardia_telefono'] ?? null),
            ],
            [
                'scope' => 'admin',
                'nombre' => "Administracion {$tenantNombre}",
                'email' => $this->sanitizeEmail($doc['admin_email'] ?? null),
                'telefono' => $this->sanitizePhone($doc['admin_telefono'] ?? null),
            ],
        ];

        return array_values(array_filter(
            $targets,
            static fn(array $target): bool => $target['email'] !== null || $target['telefono'] !== null,
        ));
    }

    private function sanitizeEmail(mixed $value): ?string
    {
        $email = trim((string) $value);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    private function sanitizePhone(mixed $value): ?string
    {
        $phone = trim((string) $value);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }

        return $phone;
    }

    private function buildDedupeKey(
        string $prefix,
        string $scope,
        CanalNotificacion $canal,
        string $destination,
    ): string {
        $normalized = $canal === CanalNotificacion::EMAIL
            ? mb_strtolower(trim($destination), 'UTF-8')
            : (preg_replace('/\D+/', '', $destination) ?? '');

        $hash = substr(hash('sha256', $normalized), 0, 16);
        return "{$prefix}:{$scope}:{$canal->value}:{$hash}";
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
