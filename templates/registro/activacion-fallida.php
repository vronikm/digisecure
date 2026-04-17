<?php
/**
 * Error de activación (token inválido o expirado) — Fase 12.
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
    <title>DigiSecure — Enlace inválido</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background:linear-gradient(135deg,#1a3a5c 0%,#0f2744 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',system-ui,sans-serif; padding:2rem; }
        .card-err { max-width:520px; width:100%; border-radius:12px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.3); }
        .card-err .head { background:linear-gradient(90deg,#c0392b,#e74c3c); color:#fff; padding:2rem; text-align:center; }
        .card-err .head i { font-size:3.5rem; }
        .card-err .body { background:#fff; padding:2rem; text-align:center; }
    </style>
</head>
<body>
<div class="card-err">
    <div class="head">
        <div><i class="bi bi-exclamation-octagon-fill"></i></div>
        <h3 class="mt-2 mb-0">No pudimos activar tu cuenta</h3>
    </div>
    <div class="body">
        <p><?= $e($mensaje) ?></p>
        <p class="text-muted small">
            Si necesitas ayuda, puedes reiniciar el registro o contactar a soporte.
        </p>
        <div class="d-flex gap-2 justify-content-center mt-3">
            <a href="<?= $view->url('/registro') ?>" class="btn btn-primary">
                <i class="bi bi-arrow-repeat me-1"></i>Registrar nuevamente
            </a>
            <a href="<?= $view->url('/login') ?>" class="btn btn-outline-secondary">
                Ir al inicio de sesión
            </a>
        </div>
    </div>
</div>
</body>
</html>
