<?php

declare(strict_types=1);

namespace DigiSecure\Security\Lopdp\Application;

use DateTimeImmutable;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Registra una nueva solicitud de derechos ARCO-P (LOPDP Ecuador).
 *
 * Reglas:
 *   - El plazo legal de respuesta es 15 días calendario desde la fecha de
 *     recepción (Art. 32 LOPDP). Se calcula automáticamente.
 *   - Tipos válidos: acceso, rectificación, cancelación, oposición, portabilidad.
 *   - Titular puede ser interno (guardia/usuario) o externo (sin cuenta).
 */
final class RegistrarSolicitudArcopService
{
    private const TIPOS_VALIDOS = ['acceso', 'rectificacion', 'cancelacion', 'oposicion', 'portabilidad'];
    private const TITULAR_TIPOS = ['guardia', 'usuario', 'externo'];
    private const CANALES = ['email', 'telefono', 'presencial', 'formulario_web'];
    private const PLAZO_DIAS = 15;

    public function __construct(
        private readonly SolicitudArcopRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input, string $usuarioId, string $ip): string
    {
        $tipo = (string)($input['tipo'] ?? '');
        $titularTipo = (string)($input['titular_tipo'] ?? '');
        $titularNombre = trim((string)($input['titular_nombre'] ?? ''));
        $descripcion = trim((string)($input['descripcion'] ?? ''));
        $fechaRecepcionStr = (string)($input['fecha_recepcion'] ?? '');
        $canal = (string)($input['canal_recepcion'] ?? 'email');

        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new DomainException('Tipo de solicitud ARCO-P inválido.');
        }
        if (!in_array($titularTipo, self::TITULAR_TIPOS, true)) {
            throw new DomainException('Tipo de titular inválido.');
        }
        if (!in_array($canal, self::CANALES, true)) {
            throw new DomainException('Canal de recepción inválido.');
        }
        if ($titularNombre === '') {
            throw new DomainException('El nombre del titular es obligatorio.');
        }
        if (mb_strlen($descripcion) < 10) {
            throw new DomainException('La descripción de la solicitud debe tener al menos 10 caracteres.');
        }

        $fechaRecepcion = $this->parseDate($fechaRecepcionStr);
        if ($fechaRecepcion === null) {
            throw new DomainException('Fecha de recepción inválida.');
        }

        $fechaLimite = $fechaRecepcion->modify('+' . self::PLAZO_DIAS . ' days');

        $email = trim((string)($input['titular_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Email del titular no es válido.');
        }

        $data = [
            'titular_tipo' => $titularTipo,
            'titular_id' => $this->nullable($input['titular_id'] ?? null),
            'titular_nombre' => $titularNombre,
            'titular_cedula' => $this->nullable($input['titular_cedula'] ?? null),
            'titular_email' => $email !== '' ? $email : null,
            'titular_telefono' => $this->nullable($input['titular_telefono'] ?? null),
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'fecha_recepcion' => $fechaRecepcion->format('Y-m-d'),
            'fecha_limite' => $fechaLimite->format('Y-m-d'),
            'canal_recepcion' => $canal,
            'estado' => 'RECIBIDA',
            'created_by' => $usuarioId,
        ];

        $id = $this->repo->crear($data);

        $this->audit->log(
            'lopdp.solicitud_arcop_creada',
            $usuarioId,
            'lopdp_solicitud',
            $id,
            ['tipo' => $tipo, 'titular_tipo' => $titularTipo, 'fecha_limite' => $data['fecha_limite']],
            ip: $ip,
        );

        return $id;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($d === false) {
            return null;
        }
        return $d->setTime(0, 0, 0);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string)$value);
        return $str === '' ? null : $str;
    }
}
