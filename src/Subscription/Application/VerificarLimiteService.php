<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Application;

use DigiSecure\Subscription\Domain\LimiteExcedidoException;
use DigiSecure\Tenant\Infrastructure\TenantRepository;

/**
 * Guard de enforcement de límites del plan. Se invoca ANTES de ejecutar
 * operaciones que consumen recursos (crear ficha, crear usuario, subir archivo).
 *
 * Lanza LimiteExcedidoException si la operación superaría el límite contratado.
 * El controlador debe capturarla y mostrar el mensaje al usuario redirigiendo
 * a /suscripcion cuando corresponda.
 *
 * max_* = 0 significa "ilimitado" (no aplica validación).
 */
final class VerificarLimiteService
{
    public function __construct(
        private readonly TenantRepository $tenantRepo,
        private readonly CalcularUsoService $uso,
    ) {
    }

    public function verificarCrearUsuario(): void
    {
        $uso = $this->obtenerUsoActual();
        if (!$uso->puedeCrearUsuario()) {
            throw LimiteExcedidoException::usuarios($uso->usuariosActuales, $uso->usuariosMax);
        }
    }

    public function verificarCrearGuardia(): void
    {
        $uso = $this->obtenerUsoActual();
        if (!$uso->puedeCrearGuardia()) {
            throw LimiteExcedidoException::guardias($uso->guardiasActuales, $uso->guardiasMax);
        }
    }

    public function verificarSubirArchivo(int $tamanoBytes): void
    {
        $uso = $this->obtenerUsoActual();
        $tamanoMb = (int) ceil($tamanoBytes / (1024 * 1024));
        if (!$uso->puedeSubirArchivo($tamanoMb)) {
            throw LimiteExcedidoException::storage($uso->storageUsadoMb, $uso->storageMaxMb);
        }
    }

    private function obtenerUsoActual(): \DigiSecure\Subscription\Domain\UsoTenant
    {
        $tenant = $this->tenantRepo->findCurrent() ?? [];
        return $this->uso->execute([
            'max_usuarios'   => (int) ($tenant['max_usuarios'] ?? 0),
            'max_guardias'   => (int) ($tenant['max_guardias'] ?? 0),
            'max_storage_mb' => (int) ($tenant['max_storage_mb'] ?? 0),
        ]);
    }
}
