<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Application;

use DigiSecure\Notification\Domain\Enum\CanalNotificacion;
use DigiSecure\Notification\Domain\Enum\PrioridadNotificacion;
use DigiSecure\Notification\Infrastructure\NotificacionRepository;
use DigiSecure\Security\Audit\AuditLogger;

/**
 * Caso de uso: encolar una notificación.
 *
 * Este servicio es el único punto de entrada para insertar en la cola.
 * Otros módulos (Expediente, Ficha) lo invocan; nadie escribe directamente
 * en `notificaciones`.
 */
final class EncolarNotificacionService
{
    public function __construct(
        private readonly NotificacionRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array{
     *     evento: string,
     *     asunto: string,
     *     destinatario_email?: string|null,
     *     destinatario_usuario_id?: string|null,
     *     destinatario_nombre?: string|null,
     *     destinatario_telefono?: string|null,
     *     canal?: CanalNotificacion|string,
     *     prioridad?: PrioridadNotificacion|string,
     *     cuerpo_texto?: string|null,
     *     cuerpo_html?: string|null,
     *     payload?: array<string, mixed>|null,
     *     entidad_tipo?: string|null,
     *     entidad_id?: string|null,
     *     dedupe_key?: string|null,
     *     scheduled_at?: string|null,
     *     max_intentos?: int,
     * } $input
     *
     * @return string|null ID de la notificación creada, o null si dedupe_key colisionó
     */
    public function execute(array $input, string $usuarioId = 'SYSTEM', string $ip = '0.0.0.0'): ?string
    {
        if (empty($input['evento']) || empty($input['asunto'])) {
            throw new \InvalidArgumentException('evento y asunto son obligatorios');
        }
        if (empty($input['cuerpo_texto']) && empty($input['cuerpo_html'])) {
            throw new \InvalidArgumentException('La notificacion requiere cuerpo_texto o cuerpo_html');
        }

        $canal = $input['canal'] ?? CanalNotificacion::EMAIL;
        $canalEnum = $canal instanceof CanalNotificacion
            ? $canal
            : CanalNotificacion::tryFrom((string) $canal);
        if ($canalEnum === null) {
            throw new \InvalidArgumentException('Canal de notificacion no soportado');
        }

        $prioridad = $input['prioridad'] ?? PrioridadNotificacion::NORMAL;
        $prioridadEnum = $prioridad instanceof PrioridadNotificacion
            ? $prioridad
            : PrioridadNotificacion::tryFrom((string) $prioridad);
        if ($prioridadEnum === null) {
            throw new \InvalidArgumentException('Prioridad de notificacion no soportada');
        }

        $destinatarioEmail = $this->sanitizeEmail($input['destinatario_email'] ?? null);
        $destinatarioTelefono = $this->sanitizePhone($input['destinatario_telefono'] ?? null);
        $destinatarioUsuarioId = $input['destinatario_usuario_id'] ?? null;

        $this->validateDestination($canalEnum, $destinatarioEmail, $destinatarioTelefono, $destinatarioUsuarioId);

        $data = [
            'evento' => $input['evento'],
            'canal' => $canalEnum->value,
            'prioridad' => $prioridadEnum->value,
            'destinatario_usuario_id' => $destinatarioUsuarioId,
            'destinatario_email' => $destinatarioEmail,
            'destinatario_telefono' => $destinatarioTelefono,
            'destinatario_nombre' => $input['destinatario_nombre'] ?? null,
            'asunto' => $input['asunto'],
            'cuerpo_texto' => $input['cuerpo_texto'] ?? null,
            'cuerpo_html' => $input['cuerpo_html'] ?? null,
            'payload' => $input['payload'] ?? null,
            'entidad_tipo' => $input['entidad_tipo'] ?? null,
            'entidad_id' => $input['entidad_id'] ?? null,
            'dedupe_key' => $input['dedupe_key'] ?? null,
            'scheduled_at' => $input['scheduled_at'] ?? null,
            'max_intentos' => $input['max_intentos'] ?? 3,
        ];

        $id = $this->repo->enqueue($data);

        if ($id !== null) {
            $this->audit->log(
                'notificacion.encolada',
                $usuarioId,
                'notificacion',
                $id,
                detalles: [
                    'evento' => $data['evento'],
                    'canal' => $data['canal'],
                    'destinatario' => $data['destinatario_email'] ?? $data['destinatario_telefono'] ?? null,
                ],
                nivel: 'info',
                ip: $ip,
            );
        }

        return $id;
    }

    private function sanitizeEmail(mixed $email): ?string
    {
        $value = trim((string) $email);
        if ($value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('destinatario_email invalido');
        }
        return $value;
    }

    private function sanitizePhone(mixed $phone): ?string
    {
        $value = trim((string) $phone);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) < 8) {
            throw new \InvalidArgumentException('destinatario_telefono invalido');
        }

        return $value;
    }

    private function validateDestination(
        CanalNotificacion $canal,
        ?string $email,
        ?string $telefono,
        mixed $usuarioId,
    ): void {
        if ($canal === CanalNotificacion::EMAIL && $email === null) {
            throw new \InvalidArgumentException('Canal EMAIL requiere destinatario_email');
        }

        if (in_array($canal, [CanalNotificacion::SMS, CanalNotificacion::WHATSAPP], true) && $telefono === null) {
            throw new \InvalidArgumentException("Canal {$canal->value} requiere destinatario_telefono");
        }

        if ($canal === CanalNotificacion::PUSH && empty($usuarioId)) {
            throw new \InvalidArgumentException('Canal PUSH requiere destinatario_usuario_id');
        }
    }
}
