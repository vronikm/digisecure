<?php
/**
 * Resultado exitoso de la verificacion de email del aspirante.
 *
 * Variables:
 *   $nombre:string        Nombre completo del aspirante
 *   $yaVerificado:bool     true si el correo ya estaba verificado
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$nombre = $nombre ?? '';
$yaVerificado = $yaVerificado ?? false;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Correo verificado - GEMESEG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260626-registro2">
</head>
<body class="gemeseg-login">
<div class="login-shell is-single">
    <section class="login-form">
        <div class="login-form-header">
            <span class="mark tone-success"><i class="bi bi-patch-check-fill"></i></span>
            <div>
                <h1><?= $yaVerificado ? 'Tu correo ya estaba confirmado' : '¡Correo confirmado!' ?></h1>
                <div class="subtitle">Verificación de identidad completada</div>
            </div>
        </div>

        <div class="login-success" role="status">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong><?= $nombre !== '' ? $e($nombre) . ',' : '¡Listo!' ?></strong>
                Confirmamos que tu correo electrónico es válido. Tu postulación
                quedará en revisión por nuestro equipo de reclutamiento.
            </div>
        </div>

        <div class="login-info" role="status">
            <i class="bi bi-info-circle"></i>
            <div>
                <strong>¿Qué sigue?</strong>
                Aún no puedes operar en la plataforma: la verificación solo
                confirma que tu correo es real. Si tu perfil avanza, te
                contactaremos con los siguientes pasos.
            </div>
        </div>

        <a href="<?= $view->url('/login') ?>" class="login-secondary-btn">
            <i class="bi bi-box-arrow-in-right"></i>Ir al inicio de sesión
        </a>

        <div class="login-foot-mini">
            &copy; <?= date('Y') ?> GEMESEG Seguridad &middot; Plataforma DigiSecure
        </div>
    </section>
</div>
</body>
</html>
