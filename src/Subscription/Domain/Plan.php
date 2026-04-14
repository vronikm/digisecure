<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Domain;

/**
 * Plan comercial del SaaS.
 *
 * Value object inmutable que representa una fila del catálogo global `planes`.
 * Los límites aquí definidos son los QUE APLICAN cuando un tenant contrata el plan;
 * la columna tenants.max_* se sincroniza desde acá al activar un ciclo.
 */
final class Plan
{
    /**
     * @param array<string, mixed> $caracteristicas
     */
    public function __construct(
        public readonly string $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion,
        public readonly int $nivel,
        public readonly float $precioMensualUsd,
        public readonly float $precioAnualUsd,
        public readonly int $maxUsuarios,
        public readonly int $maxGuardias,
        public readonly int $maxStorageMb,
        public readonly array $caracteristicas,
        public readonly bool $esPublico,
        public readonly bool $activo,
        public readonly int $orden,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $caracteristicas = [];
        if (!empty($row['caracteristicas'])) {
            $decoded = is_string($row['caracteristicas'])
                ? json_decode($row['caracteristicas'], true)
                : $row['caracteristicas'];
            if (is_array($decoded)) {
                $caracteristicas = $decoded;
            }
        }

        return new self(
            id: (string) $row['id'],
            codigo: (string) $row['codigo'],
            nombre: (string) $row['nombre'],
            descripcion: isset($row['descripcion']) ? (string) $row['descripcion'] : null,
            nivel: (int) ($row['nivel'] ?? 0),
            precioMensualUsd: (float) ($row['precio_mensual_usd'] ?? 0),
            precioAnualUsd: (float) ($row['precio_anual_usd'] ?? 0),
            maxUsuarios: (int) ($row['max_usuarios'] ?? 0),
            maxGuardias: (int) ($row['max_guardias'] ?? 0),
            maxStorageMb: (int) ($row['max_storage_mb'] ?? 0),
            caracteristicas: $caracteristicas,
            esPublico: (bool) ($row['es_publico'] ?? true),
            activo: (bool) ($row['activo'] ?? true),
            orden: (int) ($row['orden'] ?? 0),
        );
    }

    public function tieneCaracteristica(string $clave): bool
    {
        return !empty($this->caracteristicas[$clave]);
    }

    public function caracteristica(string $clave, mixed $default = null): mixed
    {
        return $this->caracteristicas[$clave] ?? $default;
    }

    public function esGratuito(): bool
    {
        return $this->precioMensualUsd <= 0.0;
    }
}
