<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Error;

use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\Routing\MethodNotAllowedException;
use DigiSecure\Kernel\Routing\RouteNotFoundException;
use DigiSecure\Security\Csrf\CsrfException;

/**
 * Handler centralizado de errores y excepciones.
 *
 * Modos:
 *   - debug=true:  muestra trace y mensaje completo (solo desarrollo)
 *   - debug=false: muestra página genérica, registra el error (producción)
 *
 * SIEMPRE registra en el log, sin importar el modo.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly bool $debug,
        private readonly string $logPath,
    ) {
    }

    /**
     * Registra los handlers globales de PHP. Llamar una sola vez en el bootstrap.
     */
    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug ? '1' : '0');
        ini_set('display_startup_errors', $this->debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $this->logPath);

        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleUncaughtException(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleUncaughtException(\Throwable $e): void
    {
        $this->logException($e);
        $response = $this->renderException($e);
        $response->send();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $exception = new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        );
        $this->logException($exception);
        if (!headers_sent()) {
            $this->renderException($exception)->send();
        }
    }

    /**
     * Convierte una excepción en Response. Pública para que el ErrorMiddleware
     * pueda usarla.
     */
    public function renderException(\Throwable $e): Response
    {
        $status = $this->statusForException($e);

        if ($this->debug) {
            return $this->renderDebug($e, $status);
        }
        return $this->renderProduction($e, $status);
    }

    private function statusForException(\Throwable $e): int
    {
        return match (true) {
            $e instanceof RouteNotFoundException => Response::STATUS_NOT_FOUND,
            $e instanceof MethodNotAllowedException => Response::STATUS_METHOD_NOT_ALLOWED,
            $e instanceof CsrfException => Response::STATUS_FORBIDDEN,
            default => Response::STATUS_INTERNAL_ERROR,
        };
    }

    private function renderDebug(\Throwable $e, int $status): Response
    {
        $body = sprintf(
            "DigiSecure :: ERROR (debug)\n\n%s: %s\n\nFile: %s:%d\n\nTrace:\n%s\n",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );
        return Response::text($body, $status);
    }

    private function renderProduction(\Throwable $e, int $status): Response
    {
        $messages = [
            Response::STATUS_NOT_FOUND => 'Recurso no encontrado.',
            Response::STATUS_METHOD_NOT_ALLOWED => 'Método HTTP no permitido para este recurso.',
            Response::STATUS_FORBIDDEN => 'No tiene permisos para acceder a este recurso.',
            Response::STATUS_INTERNAL_ERROR => 'Ocurrió un error interno. El equipo técnico ha sido notificado.',
        ];
        $msg = $messages[$status] ?? 'Error inesperado.';

        $body = sprintf(
            "<!doctype html><html lang=\"es\"><head><meta charset=\"utf-8\"><title>DigiSecure - Error %d</title></head><body><h1>Error %d</h1><p>%s</p></body></html>",
            $status,
            $status,
            htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        return Response::html($body, $status);
    }

    private function logException(\Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );
        @error_log($line, 3, $this->logPath);
    }
}
