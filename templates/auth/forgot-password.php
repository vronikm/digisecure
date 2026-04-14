<?php
/**
 * Formulario de solicitud de recuperación de contraseña.
 *
 * Variables: $csrfToken, $sent (bool), $email, $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$email = $email ?? '';
$sent = $sent ?? false;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>DigiSecure — Recuperar contraseña</title>
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
        .auth-card {
            width: 100%;
            max-width: 460px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .auth-header {
            background: var(--ds-primary);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .auth-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .auth-header .subtitle {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        .auth-body {
            padding: 2rem;
            background: #fff;
            border-radius: 0 0 12px 12px;
        }
        .form-floating > .form-control:focus {
            border-color: var(--ds-accent);
            box-shadow: 0 0 0 0.2rem rgba(46, 134, 222, 0.25);
        }
        .btn-accent {
            background: var(--ds-accent);
            border: none;
            padding: 0.75rem;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
        }
        .btn-accent:hover { background: #1e6fbf; color: #fff; }
        .auth-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .back-link { font-size: 0.9rem; color: #6c757d; text-decoration: none; }
        .back-link:hover { color: var(--ds-accent); }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon"><i class="bi bi-key-fill"></i></div>
            <h1>Recuperar contraseña</h1>
            <div class="subtitle">Te enviaremos un enlace para crear una nueva</div>
        </div>
        <div class="auth-body">

            <?php if ($sent): ?>
                <div class="alert alert-success d-flex align-items-start" role="alert">
                    <i class="bi bi-check-circle-fill fs-3 me-3 mt-1"></i>
                    <div>
                        <strong>Solicitud recibida.</strong><br>
                        Si el correo <code><?= $e($email) ?></code> corresponde a una cuenta activa,
                        recibirás un enlace para restablecer tu contraseña en los próximos minutos.
                        <hr class="my-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            El enlace expira en 60 minutos. Revisa también tu carpeta de spam.
                        </small>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <a href="<?= $view->url('/login') ?>" class="btn btn-accent">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Volver al inicio de sesión
                    </a>
                </div>
            <?php else: ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $e($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <p class="text-muted small mb-4">
                    Ingresa el correo electrónico asociado a tu cuenta.
                    Te enviaremos un enlace seguro para restablecer tu contraseña.
                </p>

                <form method="POST" action="<?= $view->url('/password/forgot') ?>" autocomplete="on" novalidate>
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                    <div class="form-floating mb-4">
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="correo@empresa.com" value="<?= $e($email) ?>"
                               required autofocus autocomplete="email">
                        <label for="email"><i class="bi bi-envelope me-1"></i>Correo electrónico</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-accent">
                            <i class="bi bi-send me-1"></i>Enviar enlace de recuperación
                        </button>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <a href="<?= $view->url('/login') ?>" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
