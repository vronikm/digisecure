<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Http;

use DigiSecure\Security\Session\SessionManager;

/**
 * Mensajes "flash" que viven una sola request — el patron post-redirect-get.
 *
 * Un controlador emite un mensaje tras un POST y hace redirect; en la siguiente
 * GET el middleware los extrae y los comparte con el layout, que los renderiza
 * como toasts y los vacia (se consumen una unica vez).
 */
final class FlashBag
{
    private const SESSION_KEY = '_flash.messages';

    public function __construct(
        private readonly SessionManager $session,
    ) {
    }

    /**
     * Tipos soportados: success | error | warning | info
     */
    public function add(string $tipo, string $mensaje, ?string $titulo = null): void
    {
        $bag = $this->session->get(self::SESSION_KEY, []);
        if (!\is_array($bag)) {
            $bag = [];
        }

        $bag[] = [
            'tipo' => $this->normalizeType($tipo),
            'titulo' => $titulo,
            'mensaje' => $mensaje,
        ];

        $this->session->set(self::SESSION_KEY, $bag);
    }

    public function success(string $mensaje, ?string $titulo = null): void
    {
        $this->add('success', $mensaje, $titulo);
    }

    public function error(string $mensaje, ?string $titulo = null): void
    {
        $this->add('error', $mensaje, $titulo);
    }

    public function warning(string $mensaje, ?string $titulo = null): void
    {
        $this->add('warning', $mensaje, $titulo);
    }

    public function info(string $mensaje, ?string $titulo = null): void
    {
        $this->add('info', $mensaje, $titulo);
    }

    /**
     * Devuelve los mensajes pendientes y los remueve de la sesion (consumo unico).
     *
     * @return list<array{tipo: string, titulo: ?string, mensaje: string}>
     */
    public function pull(): array
    {
        $bag = $this->session->get(self::SESSION_KEY, []);
        if (!\is_array($bag) || $bag === []) {
            return [];
        }

        $this->session->remove(self::SESSION_KEY);

        return array_values(array_filter(
            $bag,
            static fn($m): bool => \is_array($m) && isset($m['mensaje']),
        ));
    }

    private function normalizeType(string $tipo): string
    {
        return match (strtolower($tipo)) {
            'success', 'ok' => 'success',
            'error', 'danger', 'fail' => 'error',
            'warning', 'warn' => 'warning',
            default => 'info',
        };
    }
}
