<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Application;

use DigiSecure\Subscription\Domain\Plan;
use DigiSecure\Subscription\Domain\UsoTenant;
use DigiSecure\Subscription\Infrastructure\PlanRepository;
use DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository;
use DigiSecure\Tenant\Infrastructure\TenantRepository;

/**
 * Orquesta la vista completa de la suscripción del tenant:
 * tenant + plan activo + catálogo + uso + ciclo vigente + historial.
 *
 * Es el punto de entrada único del controller de suscripción (GET /suscripcion).
 */
final class ConsultarSuscripcionService
{
    public function __construct(
        private readonly TenantRepository $tenantRepo,
        private readonly PlanRepository $planRepo,
        private readonly SuscripcionCicloRepository $ciclosRepo,
        private readonly CalcularUsoService $uso,
    ) {
    }

    /**
     * @return array{
     *   tenant: array<string,mixed>,
     *   plan: ?Plan,
     *   catalogo: list<Plan>,
     *   uso: UsoTenant,
     *   ciclo_vigente: ?array<string,mixed>,
     *   historial: list<array<string,mixed>>,
     *   dias_restantes: ?int,
     *   vencido: bool
     * }
     */
    public function execute(): array
    {
        $tenant = $this->tenantRepo->findCurrent() ?? [];
        $planCodigo = $tenant['plan_codigo'] ?? 'trial';

        $plan = $this->planRepo->findByCodigo($planCodigo);
        $catalogo = $this->planRepo->listarActivos(true);

        $uso = $this->uso->execute([
            'max_usuarios'   => (int) ($tenant['max_usuarios'] ?? 0),
            'max_guardias'   => (int) ($tenant['max_guardias'] ?? 0),
            'max_storage_mb' => (int) ($tenant['max_storage_mb'] ?? 0),
        ]);

        $cicloVigente = $this->ciclosRepo->cicloVigente();
        $historial = $this->ciclosRepo->historial(20);

        [$diasRestantes, $vencido] = $this->calcularVencimiento($tenant['plan_vencimiento'] ?? null);

        return [
            'tenant'         => $tenant,
            'plan'           => $plan,
            'catalogo'       => $catalogo,
            'uso'            => $uso,
            'ciclo_vigente'  => $cicloVigente,
            'historial'      => $historial,
            'dias_restantes' => $diasRestantes,
            'vencido'        => $vencido,
        ];
    }

    /**
     * @return array{0: ?int, 1: bool}
     */
    private function calcularVencimiento(mixed $fechaVencimiento): array
    {
        if (empty($fechaVencimiento) || !is_string($fechaVencimiento)) {
            return [null, false];
        }

        try {
            $vence = new \DateTimeImmutable($fechaVencimiento);
            $hoy = new \DateTimeImmutable('today');
            $diff = (int) $hoy->diff($vence)->format('%r%a');
            return [$diff, $diff < 0];
        } catch (\Exception) {
            return [null, false];
        }
    }
}
