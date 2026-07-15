<?php
/**
 * Pagina tras registrar una postulacion (aspirante): pide verificar el email.
 * Standalone - sin sidebar.
 *
 * Variables:
 *   $nombre:string  Nombre completo del aspirante
 *   $correo:string  Correo de contacto
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$nombre = $nombre ?? '';
$correo = $correo ?? '';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Revisa tu correo - GEMESEG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260626-registro2">
    <style>
        /* Ventana de notificacion mas compacta y boton a su tamano (solo esta pantalla). */
        .gemeseg-login .login-shell.is-single {
            max-width: 420px;
        }
        .login-form .login-secondary-btn {
            width: auto;
            align-self: center;
            padding: .5rem 1.15rem;
            font-size: .82rem;
        }
    </style>
</head>
<body class="gemeseg-login">
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark tone-success"><i class="bi bi-envelope-check-fill"></i></span>
            <div>
                <h1>¡Casi listo! Revisa tu correo</h1>
                <div class="subtitle">Confirma tu correo para continuar el proceso</div>
            </div>
        </div>

        <div class="login-success" role="status">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong><?= $nombre !== '' ? $e($nombre) . ',' : 'Hola,' ?></strong>
                Recibimos tu solicitud. Te enviamos un enlace de verificación
                <?= $correo !== '' ? 'a:' : 'a tu correo.' ?>
                <?php if ($correo !== ''): ?>
                    <div style="margin-top: .35rem;">
                        <code style="font-family: ui-monospace, Consolas, monospace; font-size: .82rem; background: rgba(255,255,255,.6); padding: .15rem .4rem; border-radius: 4px; color: #064e3b;">
                            <i class="bi bi-envelope"></i> <?= $e($correo) ?>
                        </code>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="login-info" role="status">
            <i class="bi bi-info-circle"></i>
            <div>
                <strong>¿Qué sigue?</strong>
                Abre el enlace del correo y presiona <strong>Confirmar mi correo</strong>.
                El enlace expira en <strong>48 horas</strong>. Si no lo ves,
                revisa tu carpeta de spam o promociones.
            </div>
        </div>

        <a href="<?= $view->url('/login') ?>" class="login-secondary-btn">
            <i class="bi bi-box-arrow-in-right"></i>Volver al inicio de sesión
        </a>

        <div class="login-foot-mini">
            &copy; <?= date('Y') ?> GEMESEG Seguridad &middot; Plataforma DigiSecure
        </div>
    </section>
</div>
</body>
</html>
