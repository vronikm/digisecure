<?php
/**
 * Pagina "gracias" tras registrar la empresa (Fase 12).
 * Variables: $email:string
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Revisa tu correo — DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>">
</head>
<body>
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark tone-success"><i class="bi bi-envelope-check-fill"></i></span>
            <div>
                <h1>Revisa tu correo</h1>
                <div class="subtitle">Te enviamos un enlace para activar la cuenta</div>
            </div>
        </div>

        <div class="login-success" role="status">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>Solicitud recibida</strong>
                Enviamos el enlace de activacion a:
                <div style="margin-top: .35rem;">
                    <code style="font-family: ui-monospace, Consolas, monospace; font-size: .82rem; background: rgba(255,255,255,.6); padding: .15rem .4rem; border-radius: 4px; color: #064e3b;">
                        <i class="bi bi-envelope"></i> <?= $e($email) ?>
                    </code>
                </div>
            </div>
        </div>

        <div class="login-info" role="status">
            <i class="bi bi-info-circle"></i>
            <div>
                <strong>Que sigue?</strong>
                Haz clic en el enlace del correo para activar tu cuenta.
                El enlace expira en <strong>48 horas</strong>.
                Si no lo ves, revisa tu carpeta de spam o promociones.
            </div>
        </div>

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
