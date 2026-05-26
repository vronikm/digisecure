<?php
/**
 * Confirmacion de activacion exitosa (Fase 12).
 * Variables: $razon_social:string, $email_admin:string
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cuenta activada - DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>">
    <style>
        .activado-mark { animation: pop .55s ease-out; }
        @keyframes pop {
            0%   { transform: scale(.3); opacity: 0; }
            60%  { transform: scale(1.12); }
            100% { transform: scale(1); opacity: 1; }
        }
        .activado-info {
            background: #f4f7fb;
            border-radius: 10px;
            padding: .85rem 1rem;
            margin: 1rem 0 1.25rem;
            font-size: .85rem;
            color: var(--ds-text);
        }
        .activado-info .row + .row { margin-top: .35rem; }
        .activado-info strong { color: var(--ds-text); margin-right: .35rem; }
        .activado-info .label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--ds-text-soft);
            font-weight: 700;
            display: block;
            margin-bottom: .1rem;
        }
    </style>
</head>
<body>
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark tone-success activado-mark"><i class="bi bi-patch-check-fill"></i></span>
            <div>
                <h1>Cuenta activada!</h1>
                <div class="subtitle">Bienvenido a DigiSecure</div>
            </div>
        </div>

        <div class="login-success" role="status">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>Listo para usar</strong>
                La cuenta de <strong><?= $e($razon_social) ?></strong> ya esta activa.
                Puedes iniciar sesion y comenzar a cargar tus primeros guardias y expedientes.
            </div>
        </div>

        <div class="activado-info">
            <div>
                <span class="label">Tu usuario</span>
                <code style="font-family: ui-monospace, Consolas, monospace; font-size: .85rem;"><?= $e($email_admin) ?></code>
            </div>
            <div style="margin-top: .65rem;">
                <span class="label">Plan inicial</span>
                Trial &middot; <strong>30 dias gratis</strong>
            </div>
        </div>

        <a href="<?= $view->url('/login') ?>" class="login-submit tone-success" style="text-decoration: none;">
            <i class="bi bi-box-arrow-in-right"></i>Iniciar sesion
        </a>

        <div class="login-foot-mini">
            &copy; <?= date('Y') ?> DigiSecure
        </div>
    </section>
</div>
</body>
</html>
