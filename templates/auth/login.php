<?php
/**
 * Pagina de login (standalone, sin layout de sidebar).
 *
 * Variables: $csrfToken, $error, $email, $returnTo
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$email = $email ?? '';
$returnTo = $returnTo ?? null;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Iniciar sesion — DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>">
</head>
<body>
    <div class="login-shell">
        <aside class="login-aside">
            <div class="login-brand">
                <span class="mark"><i class="bi bi-shield-lock-fill"></i></span>
                <div>
                    <strong>DigiSecure</strong>
                    <small>Plataforma de seguridad privada</small>
                </div>
            </div>

            <div>
                <h2>Gestiona a tu equipo de seguridad con confianza</h2>
                <p class="lead">
                    Fichas, expedientes, vencimientos, auditoria y cumplimiento LOPDP en un solo lugar.
                </p>
                <ul class="login-features">
                    <li><i class="bi bi-shield-check"></i>Cumplimiento LOPDP Ecuador</li>
                    <li><i class="bi bi-clock-history"></i>Alertas de vencimientos automaticas</li>
                    <li><i class="bi bi-people"></i>Multi-tenant aislado por empresa</li>
                </ul>
            </div>

            <div class="login-foot">
                &copy; <?= date('Y') ?> DigiSecure &middot; Todos los derechos reservados
            </div>
        </aside>

        <section class="login-form">
            <h1>Bienvenido de vuelta</h1>
            <div class="subtitle">Ingresa tus credenciales para acceder al panel.</div>

            <?php if ($error !== null): ?>
                <div class="login-error" role="alert">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <div>
                        <strong>No se pudo iniciar sesion</strong>
                        <?= $e($error) ?>
                    </div>
                </div>
            <?php endif; ?>

            <form class="ds-form-main" method="POST" action="<?= $view->url('/login') ?>" autocomplete="on" novalidate>
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <?php if ($returnTo !== null): ?>
                    <input type="hidden" name="return_to" value="<?= $e($returnTo) ?>">
                <?php endif; ?>

                <div class="login-field">
                    <label for="email">Correo electronico</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-envelope field-icon"></i>
                        <input type="email" class="login-input" id="email" name="email"
                               placeholder="correo@empresa.com" value="<?= $e($email) ?>"
                               required autofocus autocomplete="email">
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Contrasena</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-key field-icon"></i>
                        <input type="password" class="login-input" id="password" name="password"
                               placeholder="Tu contrasena" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="login-submit">
                    <i class="bi bi-box-arrow-in-right"></i>Iniciar sesion
                </button>

                <div class="login-links">
                    <a href="<?= $view->url('/password/forgot') ?>">
                        <i class="bi bi-question-circle"></i> Olvide mi contrasena
                    </a>
                </div>

                <div class="login-divider">o</div>

                <div class="login-links">
                    <span class="muted">Tu empresa aun no esta en DigiSecure?</span><br>
                    <a href="<?= $view->url('/registro') ?>">
                        <i class="bi bi-building-add"></i> Registra tu empresa
                    </a>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
