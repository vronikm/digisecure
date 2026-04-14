<?php

declare(strict_types=1);

namespace DigiSecure\Security\Lopdp\Application;

use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Lopdp\Infrastructure\ConsentimientoRepository;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Servicio de aplicación para gestionar consentimientos LOPDP.
 *
 * Encapsula la lógica de aceptación/revocación y verificación rápida
 * "¿este titular ya consintió X?". Toda operación queda auditada.
 *
 * Propósitos típicos: tratamiento_datos_basico, datos_biometricos,
 * marketing_directo, transferencia_internacional.
 */
final class ConsentManager
{
    private const TITULAR_TIPOS = ['guardia', 'usuario'];
    private const BASES_LEGALES = ['consentimiento', 'contrato', 'obligacion_legal', 'interes_legitimo', 'interes_vital', 'interes_publico'];

    public function __construct(
        private readonly ConsentimientoRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    public function tieneConsentimientoVigente(string $titularTipo, string $titularId, string $proposito): bool
    {
        return $this->repo->consentimientoActivo($titularTipo, $titularId, $proposito);
    }

    /**
     * Registra un nuevo consentimiento aceptado por el titular.
     */
    public function aceptar(
        string $titularTipo,
        string $titularId,
        string $proposito,
        string $baseLegal,
        string $textoLegalVersion,
        string $textoLegal,
        string $usuarioId,
        string $ip,
        ?string $userAgent = null,
    ): string {
        if (!\in_array($titularTipo, self::TITULAR_TIPOS, true)) {
            throw new DomainException('Tipo de titular inválido.');
        }
        if (!\in_array($baseLegal, self::BASES_LEGALES, true)) {
            throw new DomainException('Base legal inválida.');
        }
        if (trim($proposito) === '') {
            throw new DomainException('El propósito es obligatorio.');
        }

        $ipBinary = null;
        if ($ip !== '') {
            $packed = inet_pton($ip);
            $ipBinary = $packed !== false ? $packed : null;
        }

        $id = $this->repo->registrar([
            'titular_tipo' => $titularTipo,
            'titular_id' => $titularId,
            'proposito' => $proposito,
            'base_legal' => $baseLegal,
            'texto_legal_version' => $textoLegalVersion,
            'texto_legal_hash' => hash('sha256', $textoLegal),
            'aceptado' => 1,
            'ip_aceptacion' => $ipBinary,
            'user_agent_aceptacion' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
        ]);

        $this->audit->log(
            'lopdp.consentimiento_aceptado',
            $usuarioId,
            'consentimiento_lopdp',
            $id,
            [
                'titular_tipo' => $titularTipo,
                'titular_id' => $titularId,
                'proposito' => $proposito,
                'base_legal' => $baseLegal,
            ],
            ip: $ip,
        );

        return $id;
    }

    /**
     * Revoca un consentimiento previamente otorgado.
     */
    public function revocar(string $id, string $motivo, string $usuarioId, string $ip): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new DomainException('El motivo de revocación debe tener al menos 5 caracteres.');
        }

        $ok = $this->repo->revocar($id, $motivo);
        if (!$ok) {
            throw new DomainException('No se pudo revocar el consentimiento (no existe o no pertenece al tenant).');
        }

        $this->audit->log(
            'lopdp.consentimiento_revocado',
            $usuarioId,
            'consentimiento_lopdp',
            $id,
            ['motivo' => $motivo],
            nivel: 'warning',
            ip: $ip,
        );
    }
}
