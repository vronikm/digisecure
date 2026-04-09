<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Http;

/**
 * HTTP Request inmutable.
 *
 * Encapsula $_GET, $_POST, $_FILES, $_SERVER, $_COOKIE, headers y body crudo.
 * NUNCA acceder a los superglobales directamente desde fuera del Kernel.
 *
 * Atributos: contenedor mutable para datos inyectados por middlewares
 * (usuario autenticado, tenant resuelto, parámetros de ruta, etc.).
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     */
    private function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $cookies,
        public readonly array $server,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /**
     * Construye la Request desde los superglobales PHP.
     * Único punto de contacto con $_GET, $_POST, etc.
     */
    public static function fromGlobals(): self
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return new self(
            method: $method,
            uri: $uri,
            path: $path,
            query: $_GET,
            post: $_POST,
            files: $_FILES,
            cookies: $_COOKIE,
            server: $_SERVER,
            headers: self::parseHeaders($_SERVER),
            body: self::readBody(),
        );
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string)$value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string)$value;
            }
        }
        return $headers;
    }

    private static function readBody(): string
    {
        $body = file_get_contents('php://input');
        return $body === false ? '' : $body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    public function clientIp(): string
    {
        // Cabeceras solo se confían si el sistema está detrás de un proxy de confianza.
        // Por defecto, REMOTE_ADDR es lo único confiable.
        return (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return $this->header('user-agent') ?? '';
    }

    /**
     * Devuelve un valor de query string sin escapar. La capa de presentación
     * es responsable de escapar al renderizar.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Devuelve los datos de un archivo subido ($_FILES entry).
     *
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        return isset($this->files[$key]) && is_array($this->files[$key])
            ? $this->files[$key]
            : null;
    }

    /**
     * Decodifica el body como JSON. Devuelve [] si no es válido.
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }
        try {
            $data = json_decode($this->body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        return is_array($data) ? $data : [];
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
