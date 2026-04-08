<?php
/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$e = \DigiSecure\Kernel\View\ViewRenderer::e(...);
$permiso = $permiso ?? '';
$userName = $userName ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Acceso denegado | DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1p8Rbdc5E3QYkQ5jU3TmgE7pC7dMf1" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-card { max-width: 480px; text-align: center; }
        .error-icon { font-size: 5rem; color: #e74c3c; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon"><i class="bi bi-shield-x"></i></div>
        <h1 class="h2 mt-3">403 — Acceso denegado</h1>
        <p class="text-muted mt-2">No tienes permisos para acceder a esta sección.</p>
        <?php if ($permiso !== ''): ?>
            <p class="small text-secondary">Permiso requerido: <code><?= $e($permiso) ?></code></p>
        <?php endif; ?>
        <a href="/dashboard" class="btn btn-primary mt-3">
            <i class="bi bi-arrow-left me-1"></i>Volver al inicio
        </a>
    </div>
</body>
</html>
