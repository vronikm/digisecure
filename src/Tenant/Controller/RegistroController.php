<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Subscription\Infrastructure\PlanRepository;
use DigiSecure\Tenant\Application\ActivarTenantService;
use DigiSecure\Tenant\Application\RegistrarTenantService;

/**
 * Flujo público de auto-registro de empresas (Fase 12).
 *
 * Rutas:
 *   GET  /registro             → formulario de registro (con catálogo de planes visible)
 *   POST /registro             → crea tenant pendiente + envía email de activación
 *   GET  /activar/{token}      → confirma y activa tenant + usuario admin
 *
 * Todas estas rutas son públicas (sin login). La renderización usa
 * layouts standalone (sin sidebar) como /login.
 */
final class RegistroController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly RegistrarTenantService $registrar,
        private readonly ActivarTenantService $activar,
        private readonly PlanRepository $planRepo,
    ) {
    }

    // ============================ GET /registro ============================

    public function showForm(Request $request, CsrfGuard $csrf): Response
    {
        return $this->view->response('registro/formulario', [
            'csrfToken' => $csrf->token(),
            'planes'    => $this->planRepo->listarActivos(true),
            'form'      => $this->formularioVacio(),
            'error'     => null,
            'sent'      => false,
        ]);
    }

    // ============================ POST /registro ============================

    public function procesarForm(Request $request, CsrfGuard $csrf): Response
    {
        $form = [
            'razon_social'          => (string) $request->post('razon_social', ''),
            'ruc'                   => (string) $request->post('ruc', ''),
            'email'                 => (string) $request->post('email', ''),
            'telefono'              => (string) $request->post('telefono', ''),
            'ciudad'                => (string) $request->post('ciudad', ''),
            'admin_nombres'         => (string) $request->post('admin_nombres', ''),
            'admin_apellidos'       => (string) $request->post('admin_apellidos', ''),
            'password'              => (string) $request->post('password', ''),
            'password_confirmacion' => (string) $request->post('password_confirmacion', ''),
            'acepta_terminos'       => $request->post('acepta_terminos') !== null,
        ];

        try {
            $resultado = $this->registrar->execute(
                $form,
                $request->clientIp(),
                $request->userAgent(),
            );
        } catch (DomainException $e) {
            return $this->view->response('registro/formulario', [
                'csrfToken' => $csrf->token(),
                'planes'    => $this->planRepo->listarActivos(true),
                'form'      => $form,
                'error'     => $e->getMessage(),
                'sent'      => false,
            ]);
        }

        return $this->view->response('registro/gracias', [
            'email' => $resultado['email'],
        ]);
    }

    // ============================ GET /activar/{token} ============================

    public function activar(Request $request, string $token): Response
    {
        try {
            $resultado = $this->activar->execute(
                $token,
                $request->clientIp(),
                $request->userAgent(),
            );
        } catch (DomainException $e) {
            return $this->view->response('registro/activacion-fallida', [
                'mensaje' => $e->getMessage(),
            ]);
        }

        return $this->view->response('registro/activado', [
            'razon_social' => $resultado['razon_social'],
            'email_admin'  => $resultado['email_admin'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formularioVacio(): array
    {
        return [
            'razon_social'          => '',
            'ruc'                   => '',
            'email'                 => '',
            'telefono'              => '',
            'ciudad'                => '',
            'admin_nombres'         => '',
            'admin_apellidos'       => '',
            'password'              => '',
            'password_confirmacion' => '',
            'acepta_terminos'       => false,
        ];
    }
}
