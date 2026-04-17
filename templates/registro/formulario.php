<?php
/**
 * Formulario público de registro de empresas (Fase 12).
 * Standalone — sin sidebar.
 *
 * Variables:
 *   $csrfToken:string
 *   $planes:list<\DigiSecure\Subscription\Domain\Plan>
 *   $form:array<string,mixed>
 *   $error:?string
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$form = $form ?? [];
$planes = $planes ?? [];
$val = static fn(string $k, string $d = ''): string => (string) ($form[$k] ?? $d);
$checked = static fn(string $k): bool => !empty($form[$k]);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DigiSecure — Registra tu empresa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --ds-primary:#1a3a5c; --ds-accent:#2e86de; }
        body { background:linear-gradient(135deg,#1a3a5c 0%,#0f2744 100%); min-height:100vh; font-family:'Segoe UI',system-ui,-apple-system,sans-serif; }
        .brand-strip { color:#fff; padding:2rem 0 1rem; text-align:center; }
        .brand-strip h1 { font-weight:700; letter-spacing:1px; margin:0; }
        .brand-strip .subtitle { opacity:.8; font-size:.9rem; margin-top:.25rem; }
        .reg-card { background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden; }
        .reg-header { background:var(--ds-primary); color:#fff; padding:1.5rem 2rem; }
        .reg-body { padding:2rem; }
        .plan-card { border:2px solid #e9ecef; border-radius:10px; padding:1.25rem; height:100%; transition:all .2s; }
        .plan-card.featured { border-color:var(--ds-accent); box-shadow:0 4px 16px rgba(46,134,222,.18); }
        .plan-card h5 { margin:0 0 .5rem; color:var(--ds-primary); }
        .plan-card .precio { font-size:1.75rem; font-weight:700; color:var(--ds-accent); }
        .plan-card .precio small { font-size:.8rem; color:#6c757d; font-weight:400; }
        .plan-card ul { padding-left:1.2rem; font-size:.85rem; margin-bottom:0; color:#495057; }
        .form-section-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6c757d; border-bottom:1px solid #e9ecef; padding-bottom:.4rem; margin:1.25rem 0 1rem; }
        .btn-registro { background:var(--ds-accent); border:none; font-weight:600; padding:.75rem; letter-spacing:.5px; }
        .btn-registro:hover { background:#1e6fbf; }
        .trial-banner { background:linear-gradient(90deg,#27ae60,#2ecc71); color:#fff; padding:.9rem 1.25rem; border-radius:8px; display:flex; align-items:center; gap:.75rem; margin-bottom:1.5rem; }
        .trial-banner i { font-size:1.5rem; }
        footer { color:#fff; opacity:.6; text-align:center; padding:1.5rem 0; font-size:.8rem; }
        .form-text { font-size:.78rem; }
    </style>
</head>
<body>
<div class="brand-strip">
    <div class="shield" style="font-size:2.2rem;margin-bottom:.25rem"><i class="bi bi-shield-lock-fill"></i></div>
    <h1>DigiSecure</h1>
    <div class="subtitle">Registra tu empresa y activa tu prueba gratuita de 30 días</div>
</div>

<div class="container pb-5">
    <div class="row g-4 justify-content-center">
        <!-- Panel izquierdo: planes -->
        <div class="col-lg-4">
            <div class="reg-card">
                <div class="reg-header">
                    <h5 class="mb-0"><i class="bi bi-gem me-2"></i>Planes disponibles</h5>
                    <small class="opacity-75">Inicias con <strong>Trial</strong> gratis. Puedes cambiar de plan cuando quieras.</small>
                </div>
                <div class="reg-body p-3">
                    <?php foreach ($planes as $plan): ?>
                        <div class="plan-card mb-2 <?= $plan->codigo === 'profesional' ? 'featured' : '' ?>">
                            <h5><?= $e($plan->nombre) ?>
                                <?php if ($plan->codigo === 'profesional'): ?>
                                    <span class="badge bg-primary ms-1">Recomendado</span>
                                <?php endif; ?>
                            </h5>
                            <div class="precio">
                                <?php if ($plan->esGratuito()): ?>
                                    Gratis <small>· 30 días</small>
                                <?php else: ?>
                                    $<?= number_format($plan->precioMensualUsd, 0) ?><small> /mes</small>
                                <?php endif; ?>
                            </div>
                            <ul class="mt-2">
                                <li><?= $plan->maxUsuarios ?: '∞' ?> usuarios</li>
                                <li><?= $plan->maxGuardias ?: '∞' ?> guardias</li>
                                <li><?= $plan->maxStorageMb >= 1024 ? number_format($plan->maxStorageMb/1024, 1) . ' GB' : $plan->maxStorageMb . ' MB' ?> almacenamiento</li>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Panel derecho: formulario -->
        <div class="col-lg-7">
            <div class="reg-card">
                <div class="reg-header">
                    <h4 class="mb-0"><i class="bi bi-building-add me-2"></i>Crea tu cuenta empresarial</h4>
                    <small class="opacity-75">Toma menos de 2 minutos. Recibirás un correo para confirmar tu cuenta.</small>
                </div>
                <div class="reg-body">
                    <div class="trial-banner">
                        <i class="bi bi-gift-fill"></i>
                        <div>
                            <strong>30 días de prueba gratis</strong><br>
                            <small>Sin tarjeta de crédito · Cancela cuando quieras</small>
                        </div>
                    </div>

                    <?php if ($error !== null): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= $view->url('/registro') ?>" novalidate autocomplete="on">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                        <div class="form-section-title">Datos de la empresa</div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Razón social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="razon_social" required maxlength="255"
                                       value="<?= $e($val('razon_social')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">RUC (13 dígitos) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="ruc" required
                                       inputmode="numeric" pattern="\d{13}" maxlength="13"
                                       value="<?= $e($val('ruc')) ?>">
                                <div class="form-text">Se valida con el algoritmo del SRI.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de contacto</label>
                                <input type="tel" class="form-control" name="telefono" maxlength="30"
                                       value="<?= $e($val('telefono')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad" maxlength="100"
                                       value="<?= $e($val('ciudad')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Correo corporativo <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required maxlength="190"
                                       value="<?= $e($val('email')) ?>">
                                <div class="form-text">A esta cuenta enviaremos el enlace de activación.</div>
                            </div>
                        </div>

                        <div class="form-section-title">Administrador principal</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombres <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="admin_nombres" required maxlength="120"
                                       value="<?= $e($val('admin_nombres')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="admin_apellidos" required maxlength="120"
                                       value="<?= $e($val('admin_apellidos')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="10"
                                       autocomplete="new-password">
                                <div class="form-text">Mínimo 10 caracteres, al menos 1 letra y 1 número.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password_confirmacion" required minlength="10"
                                       autocomplete="new-password">
                            </div>
                        </div>

                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="acepta_terminos" id="acepta_terminos" value="1"
                                   <?= $checked('acepta_terminos') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="acepta_terminos">
                                Acepto los <a href="#" target="_blank">Términos de servicio</a> y la
                                <a href="#" target="_blank">Política de privacidad (LOPDP)</a>.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-registro w-100 mt-4">
                            <i class="bi bi-rocket-takeoff me-1"></i>Crear cuenta y comenzar
                        </button>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                ¿Ya tienes cuenta?
                                <a href="<?= $view->url('/login') ?>" class="text-decoration-none">Inicia sesión</a>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<footer>
    DigiSecure · Seguridad privada para Ecuador · <?= date('Y') ?>
</footer>
</body>
</html>
