<?php
declare(strict_types=1);

namespace DigiSecure\Security\Csrf;

use DigiSecure\Security\Session\SessionManager;

/**
 * Protección CSRF mediante synchronizer token pattern.
 *
 * Cómo funciona:
 *  1. En cada sesión se genera un token aleatorio de 32 bytes (random_bytes).
 *  2. El token vive en sesión bajo la clave `_digisecure.csrf_token`.
 *  3. Cada formulario debe incluir un campo oculto `_token` con ese valor.
 *  4. Al recibir un POST/PUT/PATCH/DELETE, se compara el token enviado
 *     contra el de sesión usando hash_equals() (timing-safe).
 *  5. Si no coincide → 419 / CsrfException.
 *
 * Reglas:
 *  - random_bytes(32) → 256 bits, indistinguible de aleatorio criptográfico.
 *  - hash_equals() → comparación timing-safe; nunca usar `===` sobre tokens.
 *  - rotateToken() debe llamarse tras login y tras cambio de privilegios.
 *  - El token se almacena en sesión, NO en cookie: la cookie ya viaja en
 *    cada request automáticamente, lo que rompería la defensa CSRF.
 *
 * NO se rota el token en cada request: rompería tabs paralelos y back-button.
 * Sí se rota en login/logout/regenerateId.
 */
final class CsrfGuard
{
    private const SESSION_KEY = '_digisecure.csrf_token';
    private const TOKEN_BYTES = 32;
    public const FIELD_NAME = '_token';
    public const HEADER_NAME = 'x-csrf-token';

    public function __construct(
        private readonly SessionManager $session,
    ) {
    }

    /**
     * Devuelve el token actual de la sesión. Si no existe, lo genera.
     * Idempotente: llamarlo N veces en el mismo request devuelve el mismo token.
     */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (\is_string($token) && $token !== '') {
            return $token;
        }
        return $this->rotateToken();
    }

    /**
     * Genera un nuevo token y lo persiste en sesión.
     * Llamar tras login, logout, regenerateId() o cambio de privilegios.
     */
    public function rotateToken(): string
    {
        $token = \bin2hex(\random_bytes(self::TOKEN_BYTES));
        $this->session->set(self::SESSION_KEY, $token);
        return $token;
    }

    /**
     * Validación timing-safe del token recibido contra el de sesión.
     */
    public function isValid(?string $submitted): bool
    {
        if ($submitted === null || $submitted === '') {
            return false;
        }
        $expected = $this->session->get(self::SESSION_KEY);
        if (!\is_string($expected) || $expected === '') {
            return false;
        }
        return \hash_equals($expected, $submitted);
    }

    /**
     * Versión "asertiva": lanza CsrfException si el token no es válido.
     */
    public function assertValid(?string $submitted): void
    {
        if (!$this->isValid($submitted)) {
            throw new CsrfException('Token CSRF inválido o ausente.');
        }
    }

    /**
     * HTML listo para insertar en un <form>: <input type="hidden" name="_token" value="...">
     */
    public function field(): string
    {
        $token = \htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = self::FIELD_NAME;
        return "<input type=\"hidden\" name=\"{$name}\" value=\"{$token}\">";
    }

    /**
     * Meta tag para AJAX: <meta name="csrf-token" content="...">
     * El JS lo lee y lo envía en X-CSRF-Token.
     */
    public function metaTag(): string
    {
        $token = \htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<meta name=\"csrf-token\" content=\"{$token}\">";
    }
}
