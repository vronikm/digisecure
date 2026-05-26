<?php
/**
 * Formulario para establecer nueva contrasena (a partir de token valido).
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

if ($success) {
    $headerIcon = 'bi-check-circle-fill';
    $headerTone = 'tone-success';
    $headerTitle = 'Contrasena actualizada';
    $headerSubtitle = 'Ya puedes iniciar sesion';
} elseif (!$valid) {
    $headerIcon = 'bi-x-octagon-fill';
    $headerTone = 'tone-danger';
    $headerTitle = 'Enlace no valido';
    $headerSubtitle = 'El token expiro o no existe';
} else {
    $headerIcon = 'bi-shield-lock-fill';
    $headerTone = '';
    $headerTitle = 'Nueva contrasena';
    $headerSubtitle = 'Cuenta: ' . $email;
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Nueva contrasena - DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>">
</head>
<body>
    <div class="login-shell is-single">
        <section class="login-form">
            <div class="login-form-header">
                <span class="mark <?= $e($headerTone) ?>"><i class="bi <?= $e($headerIcon) ?>"></i></span>
                <div>
                    <h1><?= $e($headerTitle) ?></h1>
                    <div class="subtitle"><?= $e($headerSubtitle) ?></div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="login-success" role="status">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <strong>Listo!</strong>
                        Tu contrasena se actualizo correctamente.
                        Ya puedes iniciar sesion con tus nuevas credenciales.
                    </div>
                </div>
                <a href="<?= $view->url('/login') ?>" class="login-submit tone-success" style="text-decoration: none;">
                    <i class="bi bi-box-arrow-in-right"></i>Iniciar sesion
                </a>

            <?php elseif (!$valid): ?>
                <div class="login-error" role="alert">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <div>
                        <strong>Enlace invalido o expirado</strong>
                        <?= $e($error ?? 'El enlace de recuperacion no es valido. Solicita uno nuevo desde la pagina de inicio de sesion.') ?>
                    </div>
                </div>
                <a href="<?= $view->url('/password/forgot') ?>" class="login-submit" style="text-decoration: none;">
                    <i class="bi bi-arrow-clockwise"></i>Solicitar un nuevo enlace
                </a>
                <div class="login-links">
                    <a href="<?= $view->url('/login') ?>">
                        <i class="bi bi-arrow-left"></i> Volver al inicio de sesion
                    </a>
                </div>

            <?php else: ?>
                <?php if ($error !== null): ?>
                    <div class="login-error" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <strong>No se pudo actualizar la contrasena</strong>
                            <?= $e($error) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="subtitle" style="margin-bottom: 1.25rem;">
                    Crea una contrasena segura. Debe tener al menos <strong>8 caracteres</strong>,
                    incluyendo letras y numeros.
                </p>

                <form class="ds-form-main" method="POST" action="<?= $view->url('/password/reset/' . $token) ?>" novalidate>
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                    <div class="login-field">
                        <label for="password">Nueva contrasena</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-key field-icon"></i>
                            <input type="password" class="login-input" id="password" name="password"
                                   placeholder="Crea una contrasena segura" required minlength="8" autofocus
                                   autocomplete="new-password">
                        </div>
                        <div class="password-strength"><div class="password-strength-bar" id="strengthBar"></div></div>
                        <div class="requirement" id="req-length"><i class="bi bi-circle"></i>Al menos 8 caracteres</div>
                        <div class="requirement" id="req-letter"><i class="bi bi-circle"></i>Incluye al menos una letra</div>
                        <div class="requirement" id="req-digit"><i class="bi bi-circle"></i>Incluye al menos un numero</div>
                    </div>

                    <div class="login-field">
                        <label for="password_confirm">Confirmar contrasena</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-check2-square field-icon"></i>
                            <input type="password" class="login-input" id="password_confirm" name="password_confirm"
                                   placeholder="Repite la misma contrasena" required minlength="8"
                                   autocomplete="new-password">
                        </div>
                        <div class="requirement" id="req-match"><i class="bi bi-circle"></i>Las contrasenas coinciden</div>
                    </div>

                    <button type="submit" class="login-submit">
                        <i class="bi bi-shield-check"></i>Actualizar contrasena
                    </button>
                </form>
            <?php endif; ?>

            <div class="login-foot-mini">
                &copy; <?= date('Y') ?> DigiSecure
            </div>
        </section>
    </div>

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

                const colors = ['#dc2626', '#f59e0b', '#facc15', '#10b981', '#059669'];
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
