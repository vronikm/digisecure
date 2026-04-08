<?php
declare(strict_types=1);

namespace DigiSecure\Security\Session;

use DigiSecure\Kernel\Config\Config;

/**
 * Gestor de sesiones seguras (wrapper de session_* nativo de PHP).
 *
 * Reglas de oro aplicadas (del documento de arquitectura):
 *  - Cookies: Secure (en HTTPS), HttpOnly, SameSite=Strict
 *  - session_name personalizado (NO "PHPSESSID" por defecto)
 *  - session_id regenerado tras login y tras cambio de privilegios
 *  - save_path en var/sessions/ (fuera del webroot, propiedad del app)
 *  - use_strict_mode activado (rechaza IDs de sesión desconocidos)
 *  - use_only_cookies (nunca sesión por URL query string)
 *  - gc_maxlifetime alineado con SESSION_LIFETIME_MINUTES
 *
 * NO toca $_SESSION directamente fuera de esta clase (lo hace, pero
 * solo porque PHP no expone otra API). El resto del código usa
 * get/set/remove/all/regenerateId/destroy.
 */
final class SessionManager
{
    private bool $started = false;

    public function __construct(
        private readonly string $name,
        private readonly int $lifetimeSeconds,
        private readonly bool $cookieSecure,
        private readonly bool $cookieHttpOnly,
        private readonly string $cookieSameSite,
        private readonly string $savePath,
    ) {
    }

    public static function fromConfig(Config $config, string $rootPath): self
    {
        $savePath = $config->string('session.save_path', 'var/sessions');
        if (!\str_starts_with($savePath, '/') && \preg_match('#^[a-zA-Z]:#', $savePath) !== 1) {
            // Ruta relativa → resolver contra root
            $savePath = "{$rootPath}/{$savePath}";
        }

        return new self(
            name: $config->string('session.name', 'DIGISECURE_SID'),
            lifetimeSeconds: $config->int('session.lifetime', 120) * 60,
            cookieSecure: $config->bool('session.cookie_secure', false),
            cookieHttpOnly: $config->bool('session.cookie_httponly', true),
            cookieSameSite: $config->string('session.cookie_samesite', 'Strict'),
            savePath: $savePath,
        );
    }

    /**
     * Inicia la sesión. Debe llamarse ANTES de cualquier output.
     * Idempotente: si ya está iniciada, no hace nada.
     */
    public function start(): void
    {
        if ($this->started || \session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        if (\headers_sent($file, $line)) {
            throw new \RuntimeException(
                "No se puede iniciar sesión: headers ya enviados en {$file}:{$line}"
            );
        }

        // Asegurar que el save_path exista y sea escribible
        if (!\is_dir($this->savePath)) {
            if (!@\mkdir($this->savePath, 0770, true) && !\is_dir($this->savePath)) {
                throw new \RuntimeException("No se pudo crear el save_path de sesión: {$this->savePath}");
            }
        }

        // Configuración de cookies (debe hacerse ANTES de session_start)
        \session_name($this->name);
        \session_save_path($this->savePath);

        \session_set_cookie_params([
            'lifetime' => 0,                  // cookie de sesión (hasta cerrar navegador)
            'path' => '/',
            'domain' => '',                   // host-only
            'secure' => $this->cookieSecure,
            'httponly' => $this->cookieHttpOnly,
            'samesite' => $this->cookieSameSite,
        ]);

        // Hardening
        \ini_set('session.use_strict_mode', '1');
        \ini_set('session.use_only_cookies', '1');
        \ini_set('session.use_trans_sid', '0');
        \ini_set('session.cookie_samesite', $this->cookieSameSite);
        \ini_set('session.sid_length', '48');
        \ini_set('session.sid_bits_per_character', '6');
        \ini_set('session.gc_maxlifetime', (string)$this->lifetimeSeconds);

        \session_start();
        $this->started = true;

        $this->handleIdleTimeout();
    }

    private function handleIdleTimeout(): void
    {
        $now = \time();
        $lastActivity = isset($_SESSION['_digisecure.last_activity'])
            ? (int)$_SESSION['_digisecure.last_activity']
            : null;

        if ($lastActivity !== null && ($now - $lastActivity) > $this->lifetimeSeconds) {
            // Sesión inactiva demasiado tiempo → destruir y recrear
            $this->destroy();
            \session_start();
        }

        $_SESSION['_digisecure.last_activity'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return \array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * Regenera el ID de sesión preservando los datos.
     * OBLIGATORIO tras login y tras cualquier cambio de privilegios.
     * Protege contra session fixation.
     */
    public function regenerateId(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();
        \session_regenerate_id($deleteOldSession);
    }

    /**
     * Destruye la sesión por completo (datos + cookie + archivo).
     */
    public function destroy(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (\ini_get('session.use_cookies')) {
            $params = \session_get_cookie_params();
            \setcookie(
                \session_name(),
                '',
                [
                    'expires' => \time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Strict',
                ],
            );
        }

        \session_destroy();
        $this->started = false;
    }

    public function id(): string
    {
        $this->ensureStarted();
        return \session_id() ?: '';
    }

    public function isStarted(): bool
    {
        return $this->started && \session_status() === PHP_SESSION_ACTIVE;
    }

    private function ensureStarted(): void
    {
        if (!$this->isStarted()) {
            throw new \RuntimeException(
                'Intento de usar la sesión antes de llamar a SessionManager::start().'
            );
        }
    }
}
