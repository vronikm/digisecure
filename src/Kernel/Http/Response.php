<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Http;

/**
 * HTTP Response inmutable.
 *
 * Construir con factories estáticas o builder. Cada `with*()` devuelve
 * una nueva instancia. Solo `send()` toca el output buffer y headers.
 */
final class Response
{
    public const STATUS_OK = 200;
    public const STATUS_CREATED = 201;
    public const STATUS_NO_CONTENT = 204;
    public const STATUS_MOVED_PERMANENTLY = 301;
    public const STATUS_FOUND = 302;
    public const STATUS_SEE_OTHER = 303;
    public const STATUS_NOT_MODIFIED = 304;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_FORBIDDEN = 403;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_METHOD_NOT_ALLOWED = 405;
    public const STATUS_CONFLICT = 409;
    public const STATUS_UNPROCESSABLE = 422;
    public const STATUS_TOO_MANY_REQUESTS = 429;
    public const STATUS_INTERNAL_ERROR = 500;
    public const STATUS_SERVICE_UNAVAILABLE = 503;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status = self::STATUS_OK,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = self::STATUS_OK): self
    {
        return new self(
            status: $status,
            body: $body,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    public static function text(string $body, int $status = self::STATUS_OK): self
    {
        return new self(
            status: $status,
            body: $body,
            headers: ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = self::STATUS_OK): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return new self(
            status: $status,
            body: $body,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function redirect(string $location, int $status = self::STATUS_FOUND): self
    {
        return new self(
            status: $status,
            body: '',
            headers: ['Location' => BasePath::prepend($location)],
        );
    }

    public static function noContent(): self
    {
        return new self(status: self::STATUS_NO_CONTENT);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;
        return new self($this->status, $this->body, $headers);
    }

    public function withStatus(int $status): self
    {
        return new self($status, $this->body, $this->headers);
    }

    public function withBody(string $body): self
    {
        return new self($this->status, $body, $this->headers);
    }

    /**
     * Envía la respuesta al cliente. Idempotente: solo debe llamarse una vez
     * por request, desde el front controller.
     */
    public function send(): void
    {
        if (headers_sent()) {
            // Si ya se enviaron headers (output buffering roto), solo escribir body.
            echo $this->body;
            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", true);
        }

        if ($this->status !== self::STATUS_NO_CONTENT && $this->status !== self::STATUS_NOT_MODIFIED) {
            echo $this->body;
        }
    }
}
