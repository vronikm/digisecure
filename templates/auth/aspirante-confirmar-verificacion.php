<?php
/**
 * Pantalla intermedia para confirmar el correo del aspirante.
 *
 * Variables:
 *   $csrfToken:string
 *   $token:string
 *   $nombre:string
 *   $correo:string
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$token = (string) ($token ?? '');
$nombre = (string) ($nombre ?? '');
$correo = (string) ($correo ?? '');
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Confirmar correo - GEMESEG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260708-verificacion-post">
    <style>
        /* Botones de accion en una sola linea y de menor tamano (solo esta pantalla). */
        .verificacion-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            margin-top: .75rem;
        }
        .verificacion-actions form {
            flex: 1 1 0;
            min-width: 0;
            margin: 0;
        }
        .verificacion-actions > .login-secondary-btn {
            flex: 1 1 0;
            min-width: 0;
        }
        .verificacion-actions .login-submit,
        .verificacion-actions .login-secondary-btn {
            width: 100%;
            margin: 0;
            padding: .55rem .7rem;
            font-size: .82rem;
            white-space: nowrap;
        }
    </style>
</head>
<body class="gemeseg-login">
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark tone-success"><i class="bi bi-envelope-check-fill"></i></span>
            <div>
                <h1>Confirma tu correo</h1>
                <div class="subtitle">Solo falta confirmar para activar tu cuenta</div>
            </div>
        </div>

        <div class="login-info" role="status">
            <i class="bi bi-info-circle"></i>
            <div>
                <strong><?= $nombre !== '' ? $e($nombre) . ',' : 'Hola,' ?></strong>
                vamos a validar el correo <?= $correo !== '' ? '<code>' . $e($correo) . '</code>' : 'registrado' ?>.
            </div>
        </div>

        <div class="verificacion-actions">
            <form method="POST" action="<?= $view->url('/verificar') ?>" novalidate>
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="token" value="<?= $e($token) ?>">

                <button type="submit" class="login-submit">
                    <i class="bi bi-patch-check-fill"></i>Confirmar mi correo
                </button>
            </form>

            <a href="<?= $view->url('/verificar/reenviar') ?>" class="login-secondary-btn">
                <i class="bi bi-envelope-arrow-up"></i>Solicitar un nuevo enlace
            </a>
        </div>

        <div class="login-foot-mini">
            &copy; <?= date('Y') ?> GEMESEG Seguridad &middot; Plataforma DigiSecure
        </div>
    </section>
</div>
</body>
</html>
