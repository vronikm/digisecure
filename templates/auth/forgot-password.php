<?php
/**
 * Formulario de solicitud de recuperacion de contrasena.
 *
 * Variables: $csrfToken, $sent (bool), $email, $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$email = $email ?? '';
$sent = $sent ?? false;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Recuperar contrasena - DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>">
</head>
<body>
    <div class="login-shell is-single">
        <section class="login-form">
            <div class="login-form-header">
                <span class="mark tone-warning"><i class="bi bi-key-fill"></i></span>
                <div>
                    <h1>Recuperar contrasena</h1>
                    <div class="subtitle">Te enviaremos un enlace seguro a tu correo</div>
                </div>
            </div>

            <?php if ($sent): ?>
                <div class="login-success" role="status">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <strong>Solicitud recibida</strong>
                        Si el correo corresponde a una cuenta activa,
                        recibiras un enlace para restablecer tu contrasena en los proximos minutos.
                    </div>
                </div>

                <div class="login-info" role="status">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>Que sigue?</strong>
                        El enlace expira en 60 minutos. Revisa tambien tu carpeta de spam o promociones.
                    </div>
                </div>

                <a href="<?= $view->url('/login') ?>" class="login-submit" style="text-decoration: none;">
                    <i class="bi bi-box-arrow-in-right"></i>Volver al inicio de sesion
                </a>

            <?php else: ?>

                <?php if ($error !== null): ?>
                    <div class="login-error" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <strong>No se pudo procesar la solicitud</strong>
                            <?= $e($error) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="subtitle" style="margin-bottom: 1.25rem;">
                    Ingresa el correo electronico asociado a tu cuenta.
                    Te enviaremos un enlace seguro para crear una nueva contrasena.
                </p>

                <form class="ds-form-main" method="POST" action="<?= $view->url('/password/forgot') ?>" autocomplete="on" novalidate>
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                    <div class="login-field">
                        <label for="email">Correo electronico</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-envelope field-icon"></i>
                            <input type="email" class="login-input" id="email" name="email"
                                   placeholder="correo@empresa.com" value="<?= $e($email) ?>"
                                   required autofocus autocomplete="email">
                        </div>
                    </div>

                    <button type="submit" class="login-submit">
                        <i class="bi bi-send"></i>Enviar enlace de recuperacion
                    </button>

                    <div class="login-links">
                        <a href="<?= $view->url('/login') ?>">
                            <i class="bi bi-arrow-left"></i> Volver al inicio de sesion
                        </a>
                    </div>
                </form>
            <?php endif; ?>

            <div class="login-foot-mini">
                &copy; <?= date('Y') ?> DigiSecure
            </div>
        </section>
    </div>
</body>
</html>
