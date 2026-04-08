<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Routing;

/**
 * Resultado de un match exitoso del Router.
 */
final class RouteMatch
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public readonly Route $route,
        public readonly array $params,
    ) {
    }
}
