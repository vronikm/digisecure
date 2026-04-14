<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Domain;

/**
 * Snapshot de uso actual del tenant vs los límites del plan activo.
 *
 * Permite calcular porcentajes, estados (ok/advertencia/crítico/excedido)
 * y tomar decisiones de enforcement sin repetir lógica en cada controlador.
 */
final class UsoTenant
{
    public const UMBRAL_ADVERTENCIA = 80;
    public const UMBRAL_CRITICO = 95;

    public function __construct(
        public readonly int $usuariosActuales,
        public readonly int $usuariosMax,
        public readonly int $guardiasActuales,
        public readonly int $guardiasMax,
        public readonly int $storageUsadoMb,
        public readonly int $storageMaxMb,
    ) {
    }

    public function porcentajeUsuarios(): int
    {
        return $this->porcentaje($this->usuariosActuales, $this->usuariosMax);
    }

    public function porcentajeGuardias(): int
    {
        return $this->porcentaje($this->guardiasActuales, $this->guardiasMax);
    }

    public function porcentajeStorage(): int
    {
        return $this->porcentaje($this->storageUsadoMb, $this->storageMaxMb);
    }

    public function peorPorcentaje(): int
    {
        return max(
            $this->porcentajeUsuarios(),
            $this->porcentajeGuardias(),
            $this->porcentajeStorage(),
        );
    }

    public function estadoGlobal(): string
    {
        $peor = $this->peorPorcentaje();
        if ($peor >= 100) {
            return 'excedido';
        }
        if ($peor >= self::UMBRAL_CRITICO) {
            return 'critico';
        }
        if ($peor >= self::UMBRAL_ADVERTENCIA) {
            return 'advertencia';
        }
        return 'ok';
    }

    public function puedeCrearUsuario(): bool
    {
        return $this->usuariosMax === 0 || $this->usuariosActuales < $this->usuariosMax;
    }

    public function puedeCrearGuardia(): bool
    {
        return $this->guardiasMax === 0 || $this->guardiasActuales < $this->guardiasMax;
    }

    public function puedeSubirArchivo(int $tamanoMb): bool
    {
        if ($this->storageMaxMb === 0) {
            return true;
        }
        return ($this->storageUsadoMb + $tamanoMb) <= $this->storageMaxMb;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'usuarios_actuales' => $this->usuariosActuales,
            'usuarios_max'      => $this->usuariosMax,
            'usuarios_pct'      => $this->porcentajeUsuarios(),
            'guardias_actuales' => $this->guardiasActuales,
            'guardias_max'      => $this->guardiasMax,
            'guardias_pct'      => $this->porcentajeGuardias(),
            'storage_usado_mb'  => $this->storageUsadoMb,
            'storage_max_mb'    => $this->storageMaxMb,
            'storage_pct'       => $this->porcentajeStorage(),
            'peor_pct'          => $this->peorPorcentaje(),
            'estado_global'     => $this->estadoGlobal(),
        ];
    }

    private function porcentaje(int $actual, int $maximo): int
    {
        if ($maximo <= 0) {
            return 0;
        }
        return (int) min(100, floor(($actual / $maximo) * 100));
    }
}
