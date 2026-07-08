<?php
/**
 * Formulario de solicitud de recuperacion de contraseña.
 *
 * Variables: $csrfToken, $sent (bool), $email, $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$email = $email ?? '';
$sent = $sent ?? false;
$debugPasswordReset = !empty($debugPasswordReset);
$debugPasswordResetResultado = is_array($debugPasswordResetResultado ?? null) ? $debugPasswordResetResultado : null;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Recuperar contraseña - DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260708-password-debug">
</head>
<body>
    <div class="login-shell is-single">
        <section class="login-form">
            <div class="login-form-header">
                <span class="mark tone-warning"><i class="bi bi-key-fill"></i></span>
                <div>
                    <h1>Recuperar contraseña</h1>
                    <div class="subtitle">Te enviaremos un enlace seguro a tu correo</div>
                </div>
            </div>

            <?php if ($sent): ?>
                <div class="login-success" role="status">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <strong>Solicitud recibida</strong>
                        Si el correo corresponde a una cuenta activa,
                        recibiras un enlace para restablecer tu contraseña en los proximos minutos.
                    </div>
                </div>

                <div class="login-info" role="status">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>Que sigue?</strong>
                        El enlace expira en 60 minutos. Revisa tambien tu carpeta de spam o promociones.
                    </div>
                </div>

                <?php if ($debugPasswordReset && $debugPasswordResetResultado !== null): ?>
                    <div class="login-info reenvio-debug" role="status">
                        <i class="bi bi-bug"></i>
                        <div>
                            <strong>Diagnostico temporal de recuperacion</strong>
                            <ul>
                                <li>Estado: <?= $e((string) ($debugPasswordResetResultado['status'] ?? '')) ?></li>
                                <li>Correo aceptado por el canal: <?= !empty($debugPasswordResetResultado['sent']) ? 'si' : 'no' ?></li>
                                <li>Detalle: <?= $e((string) ($debugPasswordResetResultado['message'] ?? '')) ?></li>
                                <?php if (!empty($debugPasswordResetResultado['mail_code'])): ?>
                                    <li>Codigo mail: <?= $e((string) $debugPasswordResetResultado['mail_code']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($debugPasswordResetResultado['mail_message'])): ?>
                                    <li>Respuesta mail: <?= $e((string) $debugPasswordResetResultado['mail_message']) ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

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
                    Te enviaremos un enlace seguro para crear una nueva contraseña.
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
