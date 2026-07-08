<?php
/**
 * Solicitar / reenviar el enlace de verificación del aspirante.
 *
 * Variables:
 *   $csrfToken:string
 *   $sent:bool      true tras procesar el reenvío (respuesta neutra)
 *   $email:string
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$sent = $sent ?? false;
$email = $email ?? '';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Reenviar enlace de verificación - GEMESEG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260708-prod">
</head>
<body class="gemeseg-login">
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark"><i class="bi bi-envelope-arrow-up"></i></span>
            <div>
                <h1>Reenviar enlace</h1>
                <div class="subtitle">Te enviamos un nuevo enlace de verificación</div>
            </div>
        </div>

        <?php if ($sent): ?>
            <div class="login-success" role="status">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <strong>Listo</strong>
                    Si tu correo está pendiente de verificación, te enviamos un nuevo
                    enlace válido por <strong>48 horas</strong>. Revisa tu bandeja de
                    entrada y la carpeta de spam.
                </div>
            </div>
            <div class="login-info" role="status">
                <i class="bi bi-info-circle"></i>
                <div>
                    <strong>Si no recibes el enlace</strong>
                    Es posible que tu correo ya este verificado. Intenta recuperar tu
                    contrasena o solicita a reclutamiento que revise el correo registrado.
                </div>
            </div>
            <a href="<?= $view->url('/password/forgot') ?>" class="login-secondary-btn" style="margin-bottom:.75rem;">
                <i class="bi bi-key"></i>Recuperar contrasena
            </a>
            <a href="<?= $view->url('/login') ?>" class="login-secondary-btn">
                <i class="bi bi-box-arrow-in-right"></i>Volver al inicio de sesión
            </a>
        <?php else: ?>
            <p class="subtitle">
                Si tu enlace expiró, ingresa tu correo y te enviaremos uno nuevo
                para continuar con tu registro.
            </p>
            <form method="POST" action="<?= $view->url('/verificar/reenviar') ?>" autocomplete="on" novalidate>
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <div class="login-field">
                    <label for="email">Correo electrónico</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-envelope field-icon"></i>
                        <input type="email" class="login-input" id="email" name="email"
                               placeholder="tucorreo@ejemplo.com" value="<?= $e($email) ?>"
                               required autofocus autocomplete="email">
                    </div>
                </div>
                <button type="submit" class="login-submit">
                    <i class="bi bi-send"></i>Enviarme un nuevo enlace
                </button>
                <div class="login-links">
                    <a href="<?= $view->url('/login') ?>"><i class="bi bi-arrow-left"></i> Volver al inicio de sesión</a>
                </div>
            </form>
        <?php endif ?>

        <div class="login-foot-mini">
            &copy; <?= date('Y') ?> GEMESEG Seguridad &middot; Plataforma DigiSecure
        </div>
    </section>
</div>
</body>
</html>
