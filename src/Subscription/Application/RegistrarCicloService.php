<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Subscription\Domain\Plan;
use DigiSecure\Subscription\Infrastructure\PlanRepository;
use DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Registra un ciclo de suscripción (renovación, upgrade, downgrade) y
 * sincroniza los campos plan_codigo / plan_vencimiento / max_* del tenant.
 *
 * Pensado para uso administrativo interno: no integra pasarela de pago
 * real (MVP). El estado_pago se registra manualmente.
 */
final class RegistrarCicloService
{
    private const TIPOS_VALIDOS   = ['inicial','renovacion','upgrade','downgrade','cancelacion'];
    private const ESTADOS_VALIDOS = ['pendiente','pagado','vencido','reembolsado','cortesia'];

    public function __construct(
        private readonly PdoConnection $db,
        private readonly PlanRepository $planRepo,
        private readonly SuscripcionCicloRepository $ciclosRepo,
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input, string $usuarioId, string $ip): string
    {
        $planCodigo = trim((string) ($input['plan_codigo'] ?? ''));
        $tipo       = trim((string) ($input['tipo'] ?? 'renovacion'));
        $fechaIni   = trim((string) ($input['fecha_inicio'] ?? ''));
        $meses      = (int) ($input['meses'] ?? 1);
        $estado     = trim((string) ($input['estado_pago'] ?? 'pendiente'));
        $metodo     = trim((string) ($input['metodo_pago'] ?? '')) ?: null;
        $ref        = trim((string) ($input['referencia_pago'] ?? '')) ?: null;
        $notas      = trim((string) ($input['notas'] ?? '')) ?: null;

        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new DomainException('Tipo de ciclo inválido.');
        }
        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) {
            throw new DomainException('Estado de pago inválido.');
        }
        if ($meses < 1 || $meses > 60) {
            throw new DomainException('La duración debe estar entre 1 y 60 meses.');
        }

        $plan = $this->planRepo->findByCodigo($planCodigo);
        if ($plan === null || !$plan->activo) {
            throw new DomainException('Plan no encontrado o inactivo.');
        }

        $inicio = $this->parseFecha($fechaIni) ?? new \DateTimeImmutable('today');
        $fin = $inicio->modify("+{$meses} months -1 day");

        $precio = $this->calcularPrecio($plan, $meses);
        $pagadoAt = $estado === 'pagado' ? Clock::nowString() : null;

        $id = $this->ciclosRepo->insertar([
            'plan_codigo'     => $plan->codigo,
            'tipo'            => $tipo,
            'fecha_inicio'    => $inicio->format('Y-m-d'),
            'fecha_fin'       => $fin->format('Y-m-d'),
            'precio_usd'      => $precio,
            'estado_pago'     => $estado,
            'metodo_pago'     => $metodo,
            'referencia_pago' => $ref,
            'pagado_at'       => $pagadoAt,
            'notas'           => $notas,
            'created_by'      => $usuarioId,
        ]);

        // Si el ciclo queda como pagado o cortesía, aplicar cambios al tenant.
        if (in_array($estado, ['pagado','cortesia'], true) && $tipo !== 'cancelacion') {
            $this->sincronizarTenant($plan, $inicio, $fin);
        }

        $this->audit->log(
            evento: 'suscripcion.ciclo.registrado',
            usuarioId: $usuarioId,
            entidadTipo: 'suscripcion_ciclo',
            entidadId: $id,
            detalles: [
                'plan' => $plan->codigo,
                'tipo' => $tipo,
                'meses' => $meses,
                'precio_usd' => $precio,
                'estado_pago' => $estado,
            ],
            nivel: 'info',
            ip: $ip,
        );

        return $id;
    }

    private function parseFecha(string $raw): ?\DateTimeImmutable
    {
        if ($raw === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function calcularPrecio(Plan $plan, int $meses): float
    {
        if ($meses >= 12 && $plan->precioAnualUsd > 0) {
            $anios = intdiv($meses, 12);
            $mesesRest = $meses % 12;
            return round(($anios * $plan->precioAnualUsd) + ($mesesRest * $plan->precioMensualUsd), 2);
        }
        return round($meses * $plan->precioMensualUsd, 2);
    }

    private function sincronizarTenant(Plan $plan, \DateTimeImmutable $inicio, \DateTimeImmutable $fin): void
    {
        $tid = $this->tenantContext->requireTenantId();

        $this->db->affectedRows(
            'UPDATE tenants
                SET plan_codigo = :plan,
                    plan_inicio = :ini,
                    plan_vencimiento = :fin,
                    max_usuarios = :mu,
                    max_guardias = :mg,
                    max_storage_mb = :ms,
                    estado = IF(estado = \'pendiente\', \'activo\', estado),
                    updated_at = :now
              WHERE id = :tid',
            [
                'plan' => $plan->codigo,
                'ini'  => $inicio->format('Y-m-d'),
                'fin'  => $fin->format('Y-m-d'),
                'mu'   => $plan->maxUsuarios,
                'mg'   => $plan->maxGuardias,
                'ms'   => $plan->maxStorageMb,
                'now'  => Clock::nowString(),
                'tid'  => $tid,
            ],
        );
    }
}
