<?php
declare(strict_types=1);

namespace DigiSecure\Security\Crypto;

/**
 * Blind index: hash determinista con clave (HMAC-SHA256) para buscar por
 * igualdad sobre datos que se guardan cifrados (`WHERE cedula_hash = :h`).
 *
 * Por que HMAC y no sha256 plano:
 *   Una cedula ecuatoriana tiene ~1.5e8 valores validos. Con sha256 sin clave,
 *   quien obtenga un dump de la BD recupera todas las cedulas por fuerza bruta
 *   en segundos, aunque `cedula_cifrada` use AES-256-GCM. Con HMAC el atacante
 *   necesita ademas la clave, que vive solo en el `.env` del servidor.
 *
 * La clave es una subclave derivada de ENCRYPTION_KEY con HKDF (ver
 * Encryption::blindIndexer()): separacion de claves sin un secreto nuevo que
 * desplegar. Rotar ENCRYPTION_KEY exige recalcular los indices con
 * `php bin/rehash-cedulas.php --apply`.
 *
 * El indice es global (no por tenant) a proposito: los flujos de aspirantes y
 * contratacion mueven fichas/usuarios entre tenants y el indice debe seguir
 * siendo valido sin descifrar.
 */
final class BlindIndex
{
    private const KEY_LENGTH = 32;

    public function __construct(
        private readonly string $key,
    ) {
        if (\strlen($this->key) !== self::KEY_LENGTH) {
            throw new \InvalidArgumentException(
                'La clave de blind index debe tener exactamente ' . self::KEY_LENGTH . ' bytes.'
            );
        }
    }

    /**
     * Indice de una cedula. Se normaliza a solo digitos para que
     * "171003406-5" y "1710034065" produzcan el mismo valor.
     */
    public function cedula(string $cedula): string
    {
        $digits = \preg_replace('/\D+/', '', $cedula) ?? '';
        if ($digits === '') {
            throw new \InvalidArgumentException('No se puede indexar una cedula vacia.');
        }

        // Prefijo de dominio: el mismo valor en otro tipo de dato da otro indice.
        return \hash_hmac('sha256', 'cedula:' . $digits, $this->key);
    }
}
