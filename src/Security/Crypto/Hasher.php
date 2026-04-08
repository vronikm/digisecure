<?php
declare(strict_types=1);

namespace DigiSecure\Security\Crypto;

/**
 * Hashing de contraseñas con Argon2id (PHP nativo).
 *
 * Argon2id es el algoritmo recomendado por OWASP para password storage:
 * - Resistente a GPU/ASIC (memory-hard)
 * - Resistente a timing attacks (verificación timing-safe en password_verify)
 * - Estándar PHC, vencedor del Password Hashing Competition
 *
 * Parámetros por defecto:
 *   memory_cost = 65536 KiB (64 MB)
 *   time_cost   = 4 iteraciones
 *   threads     = 2
 *
 * Estos valores producen ~250-400 ms por hash en hardware moderno,
 * que es el balance correcto: lento para fuerza bruta, rápido para login normal.
 *
 * Si en el futuro endurecemos los parámetros, `needsRehash()` permite
 * re-hashear contraseñas existentes en el siguiente login del usuario,
 * sin obligar a resetearlas.
 */
final class Hasher
{
    public function __construct(
        private readonly int $memoryCost = 65536,
        private readonly int $timeCost = 4,
        private readonly int $threads = 2,
    ) {
    }

    /**
     * @return array{memory_cost: int, time_cost: int, threads: int}
     */
    private function options(): array
    {
        return [
            'memory_cost' => $this->memoryCost,
            'time_cost' => $this->timeCost,
            'threads' => $this->threads,
        ];
    }

    public function hash(string $plainPassword): string
    {
        if ($plainPassword === '') {
            throw new \InvalidArgumentException('No se puede hashear una contraseña vacía.');
        }

        $hash = \password_hash($plainPassword, PASSWORD_ARGON2ID, $this->options());

        if ($hash === false) {
            throw new \RuntimeException('Falló el hashing de contraseña con Argon2id.');
        }

        return $hash;
    }

    /**
     * Verifica una contraseña contra un hash. Timing-safe (password_verify lo garantiza).
     */
    public function verify(string $plainPassword, string $hash): bool
    {
        if ($plainPassword === '' || $hash === '') {
            return false;
        }
        return \password_verify($plainPassword, $hash);
    }

    /**
     * Indica si un hash existente debe re-generarse (porque cambiaron los parámetros).
     */
    public function needsRehash(string $hash): bool
    {
        return \password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options());
    }
}
