<?php
/**
 * Página "gracias" tras registrar la empresa (Fase 12).
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
    <title>DigiSecure — Revisa tu correo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background:linear-gradient(135deg,#1a3a5c 0%,#0f2744 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',system-ui,sans-serif; padding:2rem; }
        .card-ok { max-width:520px; width:100%; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.3); }
        .card-ok .head { background:linear-gradient(90deg,#27ae60,#2ecc71); color:#fff; padding:2rem; text-align:center; }
        .card-ok .head i { font-size:3.5rem; }
        .card-ok .body { background:#fff; padding:2rem; text-align:center; }
        .email-highlight { background:#f8f9fa; border:1px dashed #adb5bd; border-radius:6px; padding:.75rem 1rem; margin:1rem 0; font-weight:600; color:#1a3a5c; }
    </style>
</head>
<body>
<div class="card-ok">
    <div class="head">
        <div><i class="bi bi-envelope-check-fill"></i></div>
        <h3 class="mt-2 mb-0">Revisa tu correo</h3>
    </div>
    <div class="body">
        <p class="mb-2">Enviamos un enlace de activación a:</p>
        <div class="email-highlight"><i class="bi bi-envelope me-1"></i><?= $e($email) ?></div>
        <p class="text-muted small">
            Haz clic en el enlace para activar tu cuenta. El enlace expira en <strong>48 horas</strong>.
        </p>
        <div class="alert alert-info small mt-3 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Si no ves el correo, revisa la carpeta de spam o no deseados.
        </div>
        <a href="<?= $view->url('/login') ?>" class="btn btn-outline-primary mt-2">
            <i class="bi bi-box-arrow-in-right me-1"></i>Ir al inicio de sesión
        </a>
    </div>
</div>
</body>
</html>
