<?php
declare(strict_types=1);

namespace DigiSecure\Security\Crypto;

/**
 * Cifrado simétrico autenticado con AES-256-GCM.
 *
 * GCM (Galois/Counter Mode) provee:
 *  - Confidencialidad (cifrado AES-256)
 *  - Integridad (tag de autenticación de 16 bytes)
 *  - Autenticación (AAD opcional)
 *
 * Cada cifrado usa un IV (nonce) aleatorio de 12 bytes único.
 * NUNCA reutilizar el mismo IV con la misma clave: rompe el esquema.
 *
 * Formato del ciphertext devuelto (base64 url-safe):
 *     base64( version(1) || iv(12) || tag(16) || ciphertext(n) )
 *
 * Version = 0x01. Permite rotar algoritmos en el futuro sin romper
 * los datos ya cifrados.
 *
 * La clave maestra se lee de ENCRYPTION_KEY (.env) en hex de 64 chars
 * (= 32 bytes = 256 bits). Generar con:
 *     php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
 *
 * Uso típico:
 *     $enc = new Encryption($keyBytes);
 *     $ciphertext = $enc->encrypt('cédula 0102030405', 'tenant:01HXYZ');
 *     $plaintext  = $enc->decrypt($ciphertext, 'tenant:01HXYZ');
 *
 * El segundo parámetro (AAD - Additional Authenticated Data) permite
 * "enlazar" un ciphertext a un contexto: si un atacante intenta pegar un
 * ciphertext cifrado para el tenant A en una fila del tenant B, el descifrado
 * falla. Esto protege contra cross-tenant data leaks.
 */
final class Encryption
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_LENGTH = 32;    // 256 bits
    private const IV_LENGTH = 12;     // GCM standard
    private const TAG_LENGTH = 16;    // GCM default
    private const VERSION = "\x01";

    public function __construct(
        private readonly string $key,
    ) {
        if (\strlen($this->key) !== self::KEY_LENGTH) {
            throw new \InvalidArgumentException(
                'La clave de cifrado debe tener exactamente ' . self::KEY_LENGTH . ' bytes (256 bits).'
            );
        }
    }

    /**
     * Construye desde la clave hex del .env (ENCRYPTION_KEY).
     */
    public static function fromHexKey(string $hexKey): self
    {
        $hexKey = \trim($hexKey);
        if ($hexKey === '') {
            throw new \InvalidArgumentException(
                'ENCRYPTION_KEY está vacía. Generar con: php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"'
            );
        }
        if (\preg_match('/^[0-9a-fA-F]+$/', $hexKey) !== 1) {
            throw new \InvalidArgumentException('ENCRYPTION_KEY no es hex válido.');
        }
        $bytes = \hex2bin($hexKey);
        if ($bytes === false) {
            throw new \InvalidArgumentException('ENCRYPTION_KEY no es hex válido.');
        }
        return new self($bytes);
    }

    public function encrypt(string $plaintext, string $aad = ''): string
    {
        $iv = \random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = \openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('openssl_encrypt falló: ' . (\openssl_error_string() ?: 'sin detalle'));
        }

        $packed = self::VERSION . $iv . $tag . $ciphertext;
        return self::base64UrlEncode($packed);
    }

    public function decrypt(string $ciphertext, string $aad = ''): string
    {
        $packed = self::base64UrlDecode($ciphertext);
        $minLen = 1 + self::IV_LENGTH + self::TAG_LENGTH;
        if ($packed === null || \strlen($packed) < $minLen) {
            throw new CryptoException('Ciphertext corrupto o incompleto.');
        }

        $version = $packed[0];
        if ($version !== self::VERSION) {
            throw new CryptoException(
                'Versión de ciphertext no soportada: 0x' . \bin2hex($version)
            );
        }

        $iv = \substr($packed, 1, self::IV_LENGTH);
        $tag = \substr($packed, 1 + self::IV_LENGTH, self::TAG_LENGTH);
        $raw = \substr($packed, 1 + self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = \openssl_decrypt(
            $raw,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
        );

        if ($plaintext === false) {
            // No exponer el motivo exacto: un atacante no debe saber si falló
            // por tag inválido, AAD incorrecto o clave errónea.
            throw new CryptoException('Descifrado falló: tag inválido, clave incorrecta o datos manipulados.');
        }

        return $plaintext;
    }

    /**
     * Deriva una clave por tenant usando HKDF (sin guardar nada).
     * Esto permite que si se compromete la clave de un tenant específico,
     * el resto siga seguro. La clave maestra nunca cifra datos directamente
     * en tablas tenant-aware — siempre pasa por esta derivación.
     */
    public function deriveTenantKey(string $tenantId): self
    {
        $derived = \hash_hkdf(
            'sha256',
            $this->key,
            self::KEY_LENGTH,
            "digisecure:tenant:{$tenantId}",
            '',
        );
        return new self($derived);
    }

    /**
     * Hash determinista para blind index (búsquedas sobre datos cifrados).
     *
     * Usado para permitir `WHERE cedula_hash = :h` sin descifrar todas las filas.
     * Usa HMAC-SHA256 con la clave maestra para que un atacante con acceso solo
     * a la BD no pueda precomputar rainbow tables.
     */
    public function blindIndex(string $value): string
    {
        return \hash_hmac('sha256', $value, $this->key);
    }

    private static function base64UrlEncode(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): ?string
    {
        $padded = \str_pad($data, \strlen($data) + ((4 - \strlen($data) % 4) % 4), '=', STR_PAD_RIGHT);
        $decoded = \base64_decode(\strtr($padded, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
