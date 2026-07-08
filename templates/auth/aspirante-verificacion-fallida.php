<?php
/**
 * Resultado fallido de la verificacion de email del aspirante.
 *
 * Variables:
 *   $mensaje:string
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$mensaje = $mensaje ?? 'El enlace de verificacion no es valido.';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>No se pudo verificar - GEMESEG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260708-verificacion-post">
</head>
<body class="gemeseg-login">
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark tone-danger"><i class="bi bi-exclamation-octagon-fill"></i></span>
            <div>
                <h1>No pudimos verificar tu correo</h1>
                <div class="subtitle">El enlace no es valido o ya expiro</div>
            </div>
        </div>

        <div class="login-error" role="alert">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                <strong>Verificacion no completada</strong>
                <?= $e($mensaje) ?>
            </div>
        </div>

        <div class="login-info" role="status">
            <i class="bi bi-info-circle"></i>
            <div>
                <strong>Que puedo hacer?</strong>
                Si el enlace expiro, solicita uno nuevo desde esta pantalla. Si
                ya confirmaste tu correo antes, intenta iniciar sesion con tu
                correo y la contrasena registrada.
            </div>
        </div>

        <a href="<?= $view->url('/verificar/reenviar') ?>" class="login-submit" style="text-decoration:none;">
            <i class="bi bi-envelope-arrow-up"></i>Solicitar un nuevo enlace
        </a>

        <a href="<?= $view->url('/login') ?>" class="login-secondary-btn">
            <i class="bi bi-box-arrow-in-right"></i>Volver al inicio de sesion
        </a>

        <div class="login-foot-mini">
            &copy; <?= date('Y') ?> GEMESEG Seguridad &middot; Plataforma DigiSecure
        </div>
    </section>
</div>
</body>
</html>
