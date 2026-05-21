<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Csrf\CsrfException;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Valida el token CSRF en métodos no seguros (POST/PUT/PATCH/DELETE).
 *
 * Requiere que SessionMiddleware ya haya iniciado la sesión.
 *
 * Fuentes del token aceptadas (en orden):
 *  1. Campo `_token` del body POST.
 *  2. Cabecera `X-CSRF-Token` (para AJAX/JSON).
 *
 * Métodos seguros (GET, HEAD, OPTIONS) no se validan: por definición no
 * deben mutar estado y la cookie de sesión + SameSite=Strict ya cubren la
 * defensa en profundidad.
 *
 * Si el token falla, lanza CsrfException → ErrorHandlerMiddleware la
 * convierte en respuesta 419/403 según la configuración.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const LEGACY_FIELD_NAME = '_csrf';

    public function __construct(
        private readonly CsrfGuard $csrf,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (\in_array($request->method, self::SAFE_METHODS, true)) {
            // Asegurar que el token existe para que las vistas puedan renderizarlo.
            $this->csrf->token();
            return $next->handle($request);
        }

        $submitted = $this->extractToken($request);
        if (!$this->csrf->isValid($submitted)) {
            throw new CsrfException(
                'Token CSRF inválido o ausente para ' . $request->method . ' ' . $request->path
            );
        }

        return $next->handle($request);
    }

    private function extractToken(Request $request): ?string
    {
        $fromPost = $request->post(CsrfGuard::FIELD_NAME);
        if (\is_string($fromPost) && $fromPost !== '') {
            return $fromPost;
        }

        // Compatibilidad retroactiva con formularios legacy.
        $fromLegacyPost = $request->post(self::LEGACY_FIELD_NAME);
        if (\is_string($fromLegacyPost) && $fromLegacyPost !== '') {
            return $fromLegacyPost;
        }

        $fromHeader = $request->header(CsrfGuard::HEADER_NAME);
        if ($fromHeader !== null && $fromHeader !== '') {
            return $fromHeader;
        }

        // JSON body: { "_token": "..." }
        if (\str_contains((string)$request->header('content-type'), 'application/json')) {
            $json = $request->json();
            $fromJson = $json[CsrfGuard::FIELD_NAME] ?? null;
            if (\is_string($fromJson) && $fromJson !== '') {
                return $fromJson;
            }

            $fromLegacyJson = $json[self::LEGACY_FIELD_NAME] ?? null;
            if (\is_string($fromLegacyJson) && $fromLegacyJson !== '') {
                return $fromLegacyJson;
            }
        }

        return null;
    }
}
