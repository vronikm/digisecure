<?php
/**
 * Página de login (standalone, sin layout de sidebar).
 *
 * Variables: $csrfToken, $error, $email, $returnTo
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$email = $email ?? '';
$returnTo = $returnTo ?? null;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>DigiSecure — Iniciar sesión</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1p8Rbdc5E3QYkQ5jU3TmgE7pC7dMf1" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --ds-primary: #1a3a5c;
            --ds-accent: #2e86de;
        }
        body {
            background: linear-gradient(135deg, var(--ds-primary) 0%, #0f2744 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-header {
            background: var(--ds-primary);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 1px;
        }
        .login-header .subtitle {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        .login-body {
            padding: 2rem;
            background: #fff;
            border-radius: 0 0 12px 12px;
        }
        .form-floating > .form-control:focus {
            border-color: var(--ds-accent);
            box-shadow: 0 0 0 0.2rem rgba(46, 134, 222, 0.25);
        }
        .btn-login {
            background: var(--ds-accent);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
        }
        .btn-login:hover { background: #1e6fbf; }
        .shield-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="shield-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <h1>DigiSecure</h1>
            <div class="subtitle">Sistema de Gestión de Seguridad</div>
        </div>
        <div class="login-body">
            <?php if ($error !== null): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $e($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $view->url('/login') ?>" autocomplete="on" novalidate>
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <?php if ($returnTo !== null): ?>
                    <input type="hidden" name="return_to" value="<?= $e($returnTo) ?>">
                <?php endif; ?>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="correo@empresa.com" value="<?= $e($email) ?>"
                           required autofocus autocomplete="email">
                    <label for="email"><i class="bi bi-envelope me-1"></i>Correo electrónico</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Contraseña" required autocomplete="current-password">
                    <label for="password"><i class="bi bi-key me-1"></i>Contraseña</label>
                </div>

                <button type="submit" class="btn btn-login btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión
                </button>

                <div class="text-center">
                    <a href="<?= $view->url('/password/forgot') ?>" class="text-decoration-none small text-muted">
                        <i class="bi bi-question-circle me-1"></i>¿Olvidaste tu contraseña?
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
