<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Error\ErrorHandler;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;

/**
 * Middleware que captura cualquier excepción del pipeline downstream
 * y la convierte en Response usando el ErrorHandler.
 *
 * Debe ser SIEMPRE el primero del pipeline.
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ErrorHandler $errorHandler,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        try {
            return $next->handle($request);
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
            return $this->errorHandler->renderException($e);
        }
    }
}
