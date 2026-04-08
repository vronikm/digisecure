<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Config;

/**
 * Contenedor inmutable de configuración cargada desde .env + valores derivados.
 *
 * Acceso por clave con notación punto: $config->get('db.host')
 */
final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private readonly array $values,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        // Notación punto: db.host → values['db']['host']
        $segments = explode('.', $key);
        $current = $this->values;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_scalar($value) ? (string)$value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }
        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
