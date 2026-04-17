<?php
/**
 * Confirmación de activación exitosa (Fase 12).
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
    <title>DigiSecure — Cuenta activada</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background:linear-gradient(135deg,#1a3a5c 0%,#0f2744 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',system-ui,sans-serif; padding:2rem; }
        .card-ok { max-width:560px; width:100%; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.3); }
        .card-ok .head { background:linear-gradient(90deg,#27ae60,#2ecc71); color:#fff; padding:2rem; text-align:center; }
        .card-ok .head i { font-size:4rem; animation:pop .6s ease-out; }
        @keyframes pop { 0%{transform:scale(.3);opacity:0} 60%{transform:scale(1.15)} 100%{transform:scale(1);opacity:1} }
        .card-ok .body { background:#fff; padding:2rem; text-align:center; }
        .info { background:#f8f9fa; border-radius:8px; padding:1rem; margin:1rem 0; }
        .info strong { color:#1a3a5c; }
    </style>
</head>
<body>
<div class="card-ok">
    <div class="head">
        <div><i class="bi bi-patch-check-fill"></i></div>
        <h3 class="mt-2 mb-0">¡Cuenta activada!</h3>
    </div>
    <div class="body">
        <p class="lead mb-1">Bienvenido a DigiSecure</p>
        <p class="text-muted">La cuenta de <strong><?= $e($razon_social) ?></strong> ya está activa.</p>

        <div class="info text-start small">
            <div class="mb-1"><strong>Tu usuario:</strong> <?= $e($email_admin) ?></div>
            <div><strong>Plan inicial:</strong> Trial (30 días gratis)</div>
        </div>

        <p class="small text-muted">
            Ya puedes iniciar sesión y comenzar a cargar tus primeros guardias y expedientes.
        </p>

        <a href="<?= $view->url('/login') ?>" class="btn btn-primary btn-lg mt-2">
            <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión
        </a>
    </div>
</div>
</body>
</html>
