<?php
/**
 * Formulario publico de registro de empresas (Fase 12).
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
    <title>Registra tu empresa — DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>">
</head>
<body>
<div class="login-shell is-wide">
    <div class="reg-strip">
        <div class="mark"><i class="bi bi-shield-lock-fill"></i></div>
        <h1>DigiSecure</h1>
        <p>Registra tu empresa y activa tu prueba gratuita de 30 dias</p>
    </div>

    <div class="reg-grid">
        <!-- Panel izquierdo: planes -->
        <aside class="reg-panel is-plans">
            <div class="reg-panel-header">
                <h3><i class="bi bi-gem"></i>Planes disponibles</h3>
                <small>Inicias con <strong>Trial</strong> gratis. Puedes cambiar cuando quieras.</small>
            </div>
            <div class="reg-panel-body">
                <?php foreach ($planes as $plan): ?>
                    <div class="plan-card <?= $plan->codigo === 'profesional' ? 'is-featured' : '' ?>">
                        <div class="plan-name">
                            <?= $e($plan->nombre) ?>
                            <?php if ($plan->codigo === 'profesional'): ?>
                                <span class="plan-tag"><i class="bi bi-star-fill"></i>Recomendado</span>
                            <?php endif; ?>
                        </div>
                        <div class="plan-price">
                            <?php if ($plan->esGratuito()): ?>
                                Gratis<small> &middot; 30 dias</small>
                            <?php else: ?>
                                $<?= number_format($plan->precioMensualUsd, 0) ?><small> /mes</small>
                            <?php endif; ?>
                        </div>
                        <ul class="plan-features">
                            <li><i class="bi bi-check2"></i><?= $plan->maxUsuarios ?: 'Ilimitados' ?> usuarios</li>
                            <li><i class="bi bi-check2"></i><?= $plan->maxGuardias ?: 'Ilimitados' ?> guardias</li>
                            <li><i class="bi bi-check2"></i><?= $plan->maxStorageMb >= 1024 ? number_format($plan->maxStorageMb / 1024, 1) . ' GB' : $plan->maxStorageMb . ' MB' ?> de almacenamiento</li>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Panel derecho: formulario -->
        <section class="reg-panel">
            <div class="reg-panel-header">
                <h3><i class="bi bi-building-add"></i>Crea tu cuenta empresarial</h3>
                <small>Toma menos de 2 minutos. Recibiras un correo para confirmar tu cuenta.</small>
            </div>
            <div class="reg-panel-body">
                <div class="trial-banner">
                    <i class="bi bi-gift-fill"></i>
                    <div>
                        <strong>30 dias de prueba gratis</strong>
                        <small>Sin tarjeta de credito &middot; Cancela cuando quieras</small>
                    </div>
                </div>

                <?php if ($error !== null): ?>
                    <div class="login-error" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <strong>No se pudo crear la cuenta</strong>
                            <?= $e($error) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="ds-form-main" method="POST" action="<?= $view->url('/registro') ?>" novalidate autocomplete="on">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                    <div class="form-section-title"><i class="bi bi-building"></i>Datos de la empresa</div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="login-field" style="margin: 0;">
                                <label for="razon_social">Razon social <span class="req-mark">*</span></label>
                                <input type="text" class="login-input no-icon" id="razon_social" name="razon_social"
                                       required maxlength="255" value="<?= $e($val('razon_social')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="ruc">RUC (13 digitos) <span class="req-mark">*</span></label>
                                <input type="text" class="login-input no-icon" id="ruc" name="ruc" required
                                       inputmode="numeric" pattern="\d{13}" maxlength="13"
                                       value="<?= $e($val('ruc')) ?>">
                                <span class="login-help"><i class="bi bi-info-circle"></i>Se valida con el algoritmo del SRI</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="telefono">Telefono de contacto</label>
                                <input type="tel" class="login-input no-icon" id="telefono" name="telefono"
                                       maxlength="30" value="<?= $e($val('telefono')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="ciudad">Ciudad</label>
                                <input type="text" class="login-input no-icon" id="ciudad" name="ciudad"
                                       maxlength="100" value="<?= $e($val('ciudad')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="email">Correo corporativo <span class="req-mark">*</span></label>
                                <div class="login-input-wrap">
                                    <i class="bi bi-envelope field-icon"></i>
                                    <input type="email" class="login-input" id="email" name="email"
                                           required maxlength="190" value="<?= $e($val('email')) ?>">
                                </div>
                                <span class="login-help"><i class="bi bi-info-circle"></i>Te enviaremos el enlace de activacion</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="bi bi-person-badge"></i>Administrador principal</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="admin_nombres">Nombres <span class="req-mark">*</span></label>
                                <input type="text" class="login-input no-icon" id="admin_nombres" name="admin_nombres"
                                       required maxlength="120" value="<?= $e($val('admin_nombres')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="admin_apellidos">Apellidos <span class="req-mark">*</span></label>
                                <input type="text" class="login-input no-icon" id="admin_apellidos" name="admin_apellidos"
                                       required maxlength="120" value="<?= $e($val('admin_apellidos')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="password">Contrasena <span class="req-mark">*</span></label>
                                <div class="login-input-wrap">
                                    <i class="bi bi-key field-icon"></i>
                                    <input type="password" class="login-input" id="password" name="password"
                                           required minlength="10" autocomplete="new-password">
                                </div>
                                <span class="login-help"><i class="bi bi-info-circle"></i>Minimo 10 caracteres, 1 letra y 1 numero</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-field" style="margin: 0;">
                                <label for="password_confirmacion">Confirmar contrasena <span class="req-mark">*</span></label>
                                <div class="login-input-wrap">
                                    <i class="bi bi-check2-square field-icon"></i>
                                    <input type="password" class="login-input" id="password_confirmacion" name="password_confirmacion"
                                           required minlength="10" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="login-check">
                        <input type="checkbox" name="acepta_terminos" id="acepta_terminos" value="1"
                               <?= $checked('acepta_terminos') ? 'checked' : '' ?>>
                        <label for="acepta_terminos">
                            Acepto los <a href="#" target="_blank">Terminos de servicio</a> y la
                            <a href="#" target="_blank">Politica de privacidad (LOPDP)</a>.
                        </label>
                    </div>

                    <button type="submit" class="login-submit" style="margin-top: 1.25rem;">
                        <i class="bi bi-rocket-takeoff"></i>Crear cuenta y comenzar
                    </button>

                    <div class="login-links">
                        <span class="muted">Ya tienes cuenta?</span>
                        <a href="<?= $view->url('/login') ?>">Inicia sesion</a>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="login-foot-mini" style="color: rgba(255,255,255,.55); margin-top: 1.5rem;">
        DigiSecure &middot; Seguridad privada para Ecuador &middot; <?= date('Y') ?>
    </div>
</div>
</body>
</html>
