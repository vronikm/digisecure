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
    <title>Iniciar sesion - GEMESEG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260625">
</head>
<body class="gemeseg-login">
    <div class="login-shell">
        <aside class="login-aside">
            <div class="login-brand gemeseg-brand">
                <img src="<?= $view->url('/assets/img/gemeseg-logo.png') ?>" alt="GEMESEG Seguridad">
                <span class="brand-system">Plataforma DigiSecure</span>
            </div>

            <div class="login-foot">
                &copy; <?= date('Y') ?> GEMESEG Seguridad &middot; Plataforma DigiSecure
            </div>
        </aside>

        <section class="login-form">
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
                               placeholder="usuario@gemeseg.com" value="<?= $e($email) ?>"
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
            </form>
        </section>
    </div>
</body>
</html>
