<?php
/**
 * Error de activacion (token invalido o expirado) — Fase 12.
 * Variables: $mensaje:string
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enlace invalido — DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>">
</head>
<body>
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark tone-danger"><i class="bi bi-exclamation-octagon-fill"></i></span>
            <div>
                <h1>No pudimos activar tu cuenta</h1>
                <div class="subtitle">El enlace puede haber expirado o haberse usado antes</div>
            </div>
        </div>

        <div class="login-error" role="alert">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                <strong>Activacion no completada</strong>
                <?= $e($mensaje) ?>
            </div>
        </div>

        <div class="login-info" role="status">
            <i class="bi bi-lightbulb"></i>
            <div>
                <strong>Que puedes hacer?</strong>
                Reinicia el registro con tu RUC y te enviaremos un nuevo enlace.
                Si necesitas ayuda, contacta a soporte.
            </div>
        </div>

        <a href="<?= $view->url('/registro') ?>" class="login-submit" style="text-decoration: none;">
            <i class="bi bi-arrow-repeat"></i>Registrar nuevamente
        </a>

        <a href="<?= $view->url('/login') ?>" class="login-secondary-btn">
            <i class="bi bi-box-arrow-in-right"></i>Ir al inicio de sesion
        </a>

        <div class="login-foot-mini">
            &copy; <?= date('Y') ?> DigiSecure
        </div>
    </section>
</div>
</body>
</html>
