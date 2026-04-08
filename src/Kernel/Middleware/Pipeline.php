<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Container\Container;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;

/**
 * Pipeline de middlewares estilo onion.
 *
 * Recibe una lista de FQCN de middlewares + un handler final (router dispatcher).
 * Resuelve cada middleware vía el Container al ejecutarse, no antes
 * (lazy resolution: middlewares no usados en una ruta no se construyen).
 */
final class Pipeline implements RequestHandlerInterface
{
    /** @var list<class-string<MiddlewareInterface>> */
    private array $middlewares;
    private int $index = 0;

    /**
     * @param list<class-string<MiddlewareInterface>> $middlewares
     */
    public function __construct(
        private readonly Container $container,
        array $middlewares,
        private readonly RequestHandlerInterface $finalHandler,
    ) {
        $this->middlewares = $middlewares;
    }

    public function handle(Request $request): Response
    {
        if ($this->index >= count($this->middlewares)) {
            return $this->finalHandler->handle($request);
        }

        $middlewareClass = $this->middlewares[$this->index];
        $this->index++;

        $middleware = $this->container->get($middlewareClass);
        if (!$middleware instanceof MiddlewareInterface) {
            throw new \RuntimeException(
                "El middleware '{$middlewareClass}' no implementa MiddlewareInterface."
            );
        }

        return $middleware->process($request, $this);
    }
}
