<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Dashboard temporal (Fase 0.7).
 *
 * Muestra un dashboard placeholder que confirma que Auth + RBAC + TenantContext
 * funcionan correctamente. Se reemplazará en Fase 4 con KPIs reales.
 */
final class DashboardController
{
    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $rolesHtml = '';
        foreach ($user->roles as $rol) {
            $rolesHtml .= '<span class="badge bg-info me-1">' . $e($rol['nombre']) . '</span>';
        }

        $permisosCount = count($user->permisos);
        $permisosHtml = '';
        foreach ($user->permisos as $p) {
            $permisosHtml .= '<span class="badge bg-secondary me-1 mb-1">' . $e($p) . '</span>';
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>DigiSecure — Dashboard</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1p8Rbdc5E3QYkQ5jU3TmgE7pC7dMf1" crossorigin="anonymous">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                :root { --ds-primary: #1a3a5c; --ds-accent: #2e86de; }
                .navbar { background: var(--ds-primary) !important; }
                .card-stat { border-left: 4px solid var(--ds-accent); }
            </style>
        </head>
        <body class="bg-light">
            <nav class="navbar navbar-dark px-3">
                <span class="navbar-brand fw-bold">
                    <i class="bi bi-shield-lock-fill me-2"></i>DigiSecure
                </span>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-white-50 small">
                        <i class="bi bi-building me-1"></i>{$e($user->tenantNombre)}
                    </span>
                    <span class="text-white small">
                        <i class="bi bi-person-circle me-1"></i>{$e($user->nombreCompleto())}
                    </span>
                    <form method="POST" action="/logout" class="d-inline">
                        <input type="hidden" name="_csrf_token" value="{$e($csrf->token())}">
                        <button type="submit" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión
                        </button>
                    </form>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-1">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </h4>
                        <p class="text-muted mb-0">Bienvenido, {$e($user->nombres)}. Fase 0.7 — Auth + RBAC + Multi-tenant verificados.</p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card card-stat shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Usuario</div>
                                <div class="fw-bold">{$e($user->email)}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Tenant</div>
                                <div class="fw-bold">{$e($user->tenantNombre)}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Roles</div>
                                <div>{$rolesHtml}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat shadow-sm">
                            <div class="card-body">
                                <div class="text-muted small">Permisos</div>
                                <div class="fw-bold">{$permisosCount} activos</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-bold">
                                <i class="bi bi-check2-circle me-2 text-success"></i>Verificación de Fase 0.7
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr><td><i class="bi bi-check-lg text-success"></i> Autenticación</td><td>Sesión activa, Argon2id</td></tr>
                                        <tr><td><i class="bi bi-check-lg text-success"></i> CSRF</td><td>Token válido en sesión</td></tr>
                                        <tr><td><i class="bi bi-check-lg text-success"></i> Multi-tenant</td><td>Tenant ID: {$e($user->tenantId)}</td></tr>
                                        <tr><td><i class="bi bi-check-lg text-success"></i> RBAC</td><td>{$permisosCount} permisos cargados</td></tr>
                                        <tr><td><i class="bi bi-check-lg text-success"></i> Auditoría</td><td>Login registrado en tabla auditoria</td></tr>
                                        <tr><td><i class="bi bi-check-lg text-success"></i> Rate limiting</td><td>5 intentos / 15 min bloqueo</td></tr>
                                        <tr><td><i class="bi bi-check-lg text-success"></i> Session fixation</td><td>session_id regenerado post-login</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-bold">
                                <i class="bi bi-shield-fill-check me-2 text-primary"></i>Tus permisos
                            </div>
                            <div class="card-body" style="max-height:300px;overflow-y:auto">
                                {$permisosHtml}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        </body>
        </html>
        HTML;

        return Response::html($html);
    }
}
