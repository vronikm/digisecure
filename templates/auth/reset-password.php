<?php
/**
 * Formulario para establecer nueva contraseña (a partir de token válido).
 *
 * Variables: $csrfToken, $token, $email, $valid (bool), $error, $success (bool)
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$email = $email ?? '';
$token = $token ?? '';
$valid = $valid ?? false;
$success = $success ?? false;
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>DigiSecure — Nueva contraseña</title>
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
            max-width: 480px;
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
            letter-spacing: 0.5px;
        }
        .btn-accent:hover { background: #1e6fbf; color: #fff; }
        .auth-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .password-strength {
            height: 6px;
            border-radius: 3px;
            background: #e9ecef;
            overflow: hidden;
            margin-top: 6px;
        }
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width .2s ease, background-color .2s ease;
            background: #dc3545;
        }
        .requirement {
            font-size: 0.8rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
        }
        .requirement.met { color: #198754; }
        .requirement i { font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon">
                <?php if ($success): ?>
                    <i class="bi bi-check-circle-fill"></i>
                <?php elseif (!$valid && !$success): ?>
                    <i class="bi bi-x-octagon-fill"></i>
                <?php else: ?>
                    <i class="bi bi-shield-lock-fill"></i>
                <?php endif; ?>
            </div>
            <h1>
                <?php if ($success): ?>
                    Contraseña actualizada
                <?php elseif (!$valid): ?>
                    Enlace no válido
                <?php else: ?>
                    Nueva contraseña
                <?php endif; ?>
            </h1>
            <?php if ($valid && !$success): ?>
                <div class="subtitle">Cuenta: <?= $e($email) ?></div>
            <?php endif; ?>
        </div>
        <div class="auth-body">

            <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-start" role="alert">
                    <i class="bi bi-check-circle-fill fs-3 me-3 mt-1"></i>
                    <div>
                        <strong>¡Listo!</strong><br>
                        Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión con tus nuevas credenciales.
                    </div>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <a href="<?= $view->url('/login') ?>" class="btn btn-accent">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión
                    </a>
                </div>

            <?php elseif (!$valid): ?>
                <div class="alert alert-danger d-flex align-items-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3 mt-1"></i>
                    <div>
                        <strong>Enlace inválido o expirado.</strong><br>
                        <?= $e($error ?? 'El enlace de recuperación no es válido. Solicita uno nuevo desde la página de inicio de sesión.') ?>
                    </div>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <a href="<?= $view->url('/password/forgot') ?>" class="btn btn-accent">
                        <i class="bi bi-arrow-clockwise me-1"></i>Solicitar un nuevo enlace
                    </a>
                    <a href="<?= $view->url('/login') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
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
                    Crea una contraseña segura. Debe tener al menos <strong>8 caracteres</strong>,
                    incluyendo letras y números.
                </p>

                <form method="POST" action="<?= $view->url('/password/reset/' . $token) ?>" novalidate>
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                    <div class="form-floating mb-2">
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Nueva contraseña" required minlength="8" autofocus
                               autocomplete="new-password">
                        <label for="password"><i class="bi bi-key me-1"></i>Nueva contraseña</label>
                    </div>
                    <div class="password-strength mb-1"><div class="password-strength-bar" id="strengthBar"></div></div>
                    <div class="requirement" id="req-length"><i class="bi bi-circle"></i>Al menos 8 caracteres</div>
                    <div class="requirement" id="req-letter"><i class="bi bi-circle"></i>Incluye al menos una letra</div>
                    <div class="requirement" id="req-digit"><i class="bi bi-circle"></i>Incluye al menos un número</div>

                    <div class="form-floating my-3">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               placeholder="Confirmar contraseña" required minlength="8"
                               autocomplete="new-password">
                        <label for="password_confirm"><i class="bi bi-check2-square me-1"></i>Confirmar contraseña</label>
                    </div>
                    <div class="requirement" id="req-match"><i class="bi bi-circle"></i>Las contraseñas coinciden</div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-accent">
                            <i class="bi bi-shield-check me-1"></i>Actualizar contraseña
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php if ($valid && !$success): ?>
    <script>
        (function () {
            const pwd = document.getElementById('password');
            const confirm = document.getElementById('password_confirm');
            const bar = document.getElementById('strengthBar');
            const reqs = {
                length: document.getElementById('req-length'),
                letter: document.getElementById('req-letter'),
                digit: document.getElementById('req-digit'),
                match: document.getElementById('req-match'),
            };

            function setMet(el, met) {
                el.classList.toggle('met', met);
                el.querySelector('i').className = 'bi ' + (met ? 'bi-check-circle-fill' : 'bi-circle');
            }

            function evaluate() {
                const v = pwd.value;
                const hasLen = v.length >= 8;
                const hasLetter = /[A-Za-z]/.test(v);
                const hasDigit = /\d/.test(v);
                const hasSymbol = /[^A-Za-z0-9]/.test(v);

                setMet(reqs.length, hasLen);
                setMet(reqs.letter, hasLetter);
                setMet(reqs.digit, hasDigit);
                setMet(reqs.match, confirm.value !== '' && confirm.value === v);

                let score = 0;
                if (hasLen) score++;
                if (hasLetter) score++;
                if (hasDigit) score++;
                if (hasSymbol) score++;
                if (v.length >= 12) score++;

                const colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#198754'];
                const widths = [10, 30, 55, 80, 100];
                const idx = Math.max(0, Math.min(score - 1, 4));
                bar.style.width = (v === '' ? 0 : widths[idx]) + '%';
                bar.style.background = colors[idx];
            }

            pwd.addEventListener('input', evaluate);
            confirm.addEventListener('input', evaluate);
        })();
    </script>
    <?php endif; ?>
</body>
</html>
