<?php
/**
 * Pagina de login (standalone, sin layout de sidebar).
 *
 * Variables: $csrfToken, $error, $email, $returnTo
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$error = $error ?? null;
$email = $email ?? '';
$returnTo = $returnTo ?? null;

// Estado del modal de registro de aspirantes (postulantes a guardia).
$registroError = $registroError ?? null;
$registroForm  = $registroForm ?? [];
$registroOpen  = $registroOpen ?? false;
$registroErrorText = $registroError !== null ? (string) $registroError : '';
$registroDocumentoDuplicado = $registroErrorText !== ''
    && str_contains(mb_strtolower($registroErrorText, 'UTF-8'), 'postul')
    && str_contains(mb_strtolower($registroErrorText, 'UTF-8'), 'documento');
$recaptchaSiteKey = $recaptchaSiteKey ?? '';
$rv   = static fn (string $k, string $d = ''): string => (string) ($registroForm[$k] ?? $d);
$rsel = static fn (string $cur): string => ($rv('tipo_documento') === $cur) ? 'selected' : '';
$racc = !empty($registroForm['acepta_politicas']);
$maxFechaNac = date('Y-m-d', strtotime('-18 years'));
$minFechaNac = date('Y-m-d', strtotime('-70 years'));
$tiposDoc = ['cedula' => 'Cédula', 'pasaporte' => 'Pasaporte'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d1f36">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $view->url('/assets/img/favicon-32.png') ?>">
    <link rel="apple-touch-icon" href="<?= $view->url('/assets/img/apple-touch-icon.png') ?>">
    <title>Iniciar sesion - GEMESEG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-auth.css') ?>?v=gemeseg-login-20260922-correo2">
</head>
<body class="gemeseg-login">
    <div class="login-shell">
        <aside class="login-aside">
            <div class="login-brand gemeseg-brand">
                <img class="login-company-logo" src="<?= $view->url('/assets/img/logoGemeseg.png') ?>" alt="GEMESEG Seguridad">
            </div>
            <div class="login-foot">
                &copy; <?= date('Y') ?> GEMESEG Seguridad &middot; Plataforma DigiSecure
            </div>
        </aside>

        <section class="login-form">
            <?php if ($error !== null): ?>
                <div class="login-error" role="alert">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <div>
                        <strong>No se pudo iniciar sesión</strong>
                        <?= $e($error) ?>
                    </div>
                </div>
            <?php endif; ?>

            <form class="ds-form-main" method="POST" action="<?= $view->url('/login') ?>" autocomplete="on" novalidate>
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <?php if ($returnTo !== null): ?>
                    <input type="hidden" name="return_to" value="<?= $e($returnTo) ?>">
                <?php endif; ?>

                <div class="login-field">
                    <h1 for="email">Accede a nuestra plataforma digital</h1>
                </div></br>
                <div class="login-field">
                    <label for="email">Correo electrónico</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-envelope field-icon"></i>
                        <input type="email" class="login-input" id="email" name="email"
                               placeholder="usuario@gemeseg.com" value="<?= $e($email) ?>"
                               required autofocus autocomplete="email">
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Contraseña</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-key field-icon"></i>
                        <input type="password" class="login-input" id="password" name="password"
                               placeholder="Tu contraseña" required autocomplete="current-password">
                    </div>
                </div>
                <div class="login-actions">
                    <button type="submit" class="login-submit">
                        <i class="bi bi-box-arrow-in-right"></i>Iniciar sesión
                    </button>
                    <button type="button" class="login-register-btn" id="btn-open-registro"
                            aria-haspopup="dialog" aria-controls="registro-modal">
                        <i class="bi bi-person-plus"></i>Regístrate aquí
                    </button>
                </div>

                <div class="login-links">
                    <a href="<?= $view->url('/password/forgot') ?>">
                        <i class="bi bi-question-circle"></i> Olvidé mi contraseña
                    </a>
                </div>
            </form>
        </section>
    </div>

    <!-- ============ Modal de Registro (aspirantes / postulantes) ============ -->
    <div class="ds-modal" id="registro-modal" role="dialog" aria-modal="true"
         aria-labelledby="registro-modal-title" <?= $registroOpen ? '' : 'hidden' ?>>
        <div class="ds-modal-backdrop" data-close></div>
        <div class="ds-modal-dialog" role="document">
            <div class="ds-modal-header">
                <div>
                    <h2 id="registro-modal-title"><i class="bi bi-person-vcard"></i> Postula con GEMESEG</h2>
                    <p>Completa tus datos para crear tu cuenta de usuario en nuestra plataforma.</p>
                </div>
                <button type="button" class="ds-modal-close" data-close aria-label="Cerrar">&times;</button>
            </div>

            <div class="ds-modal-body">
                <?php if ($registroError !== null): ?>
                    <div class="login-error" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <strong>No se pudo completar tu registro</strong>
                            <?= $e($registroError) ?>
                            <?php if ($registroDocumentoDuplicado): ?>
                                <div class="registro-error-help">
                                    <p>Si no sabes si tu correo ya fue confirmado, usa estas opciones:</p>
                                    <div class="registro-error-actions">
                                        <a href="<?= $view->url('/password/forgot') ?>" class="registro-error-action">
                                            <i class="bi bi-key"></i>
                                            <span>Ya confirmé mi correo: recuperar contraseña</span>
                                        </a>
                                        <a href="<?= $view->url('/verificar/reenviar') ?>" class="registro-error-action">
                                            <i class="bi bi-envelope-arrow-up"></i>
                                            <span>Aún no confirmé mi correo: reenviar enlace</span>
                                        </a>
                                    </div>
                                    <p class="registro-error-note">
                                        Si el enlace de verificación no llega en unos minutos, prueba recuperar contraseña.
                                        Si ninguna opción llega a tu correo, solicita a reclutamiento que revise tu documento y correo registrados.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $view->url('/aspirantes') ?>" class="ds-modal-form" autocomplete="on" novalidate>
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                    <div class="login-error ds-client-errors" id="reg-client-errors" role="alert" hidden>
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Revisa los siguientes campos</strong>
                            <ul id="reg-client-errors-list" style="margin:.25rem 0 0; padding-left:1.1rem;"></ul>
                        </div>
                    </div>

                    <div class="ds-field-grid">
                        <div class="login-field">
                            <label for="reg_tipo_documento">Tipo de documento</label>
                            <select class="login-input login-select no-icon" id="reg_tipo_documento" name="tipo_documento" required>
                                <option value="" disabled <?= $rv('tipo_documento') === '' ? 'selected' : '' ?>>Selecciona…</option>
                                <?php foreach ($tiposDoc as $val => $lbl): ?>
                                    <option value="<?= $e($val) ?>" <?= $rsel($val) ?>><?= $e($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="login-field">
                            <label for="reg_documento">Número de documento</label>
                            <input type="text" class="login-input no-icon" id="reg_documento" name="documento"
                                   maxlength="20" value="<?= $e($rv('documento')) ?>"
                                   placeholder="0102030405" required>
                        </div>

                        <div class="login-field">
                            <label for="reg_apellidos">Apellidos</label>
                            <input type="text" class="login-input no-icon" id="reg_apellidos" name="apellidos"
                                   maxlength="120" value="<?= $e($rv('apellidos')) ?>" required autocomplete="family-name">
                        </div>
                        <div class="login-field">
                            <label for="reg_nombres">Nombres</label>
                            <input type="text" class="login-input no-icon" id="reg_nombres" name="nombres"
                                   maxlength="120" value="<?= $e($rv('nombres')) ?>" required autocomplete="given-name">
                        </div>

                        <div class="login-field">
                            <label for="reg_correo">Correo electrónico</label>
                            <input type="email" class="login-input no-icon" id="reg_correo" name="correo"
                                   maxlength="190" value="<?= $e($rv('correo')) ?>"
                                   placeholder="tucorreo@ejemplo.com" required autocomplete="email">
                        </div>
                        <div class="login-field">
                            <label for="reg_correo_confirmacion">Confirmar correo electrónico</label>
                            <input type="email" class="login-input no-icon" id="reg_correo_confirmacion" name="correo_confirmacion"
                                   maxlength="190" value="<?= $e($rv('correo_confirmacion')) ?>"
                                   placeholder="Escríbelo de nuevo" required autocomplete="off">
                        </div>

                        <div class="login-field">
                            <label for="reg_celular">Número de celular</label>
                            <input type="tel" class="login-input no-icon" id="reg_celular" name="celular"
                                   inputmode="numeric" maxlength="10" pattern="\d{10}"
                                   value="<?= $e($rv('celular')) ?>"
                                   placeholder="0991234567" required autocomplete="tel">
                        </div>
                        <div class="login-field">
                            <label for="reg_estatura">Estatura (m)</label>
                            <input type="number" class="login-input no-icon" id="reg_estatura" name="estatura"
                                   step="0.01" min="1.20" max="2.50" value="<?= $e($rv('estatura')) ?>"
                                   placeholder="1.75" required>
                        </div>

                        <div class="login-field is-full">
                            <label for="reg_fecha_nacimiento">Fecha de nacimiento</label>
                            <input type="date" class="login-input no-icon" id="reg_fecha_nacimiento" name="fecha_nacimiento"
                                   value="<?= $e($rv('fecha_nacimiento')) ?>"
                                   min="<?= $e($minFechaNac) ?>" max="<?= $e($maxFechaNac) ?>" required>
                        </div>

                        <div class="login-field">
                            <label for="reg_password">Contraseña</label>
                            <input type="password" class="login-input no-icon" id="reg_password" name="password"
                                   minlength="8" placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                            <span class="login-help"><i class="bi bi-shield-lock"></i>Mín. 8 caracteres, con mayúscula, minúscula y número.</span>
                        </div>
                        <div class="login-field">
                            <label for="reg_password_confirmacion">Confirmar contraseña</label>
                            <input type="password" class="login-input no-icon" id="reg_password_confirmacion" name="password_confirmacion"
                                   minlength="8" placeholder="Repite tu contraseña" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="login-check">
                        <input type="checkbox" id="reg_acepta_politicas" name="acepta_politicas" value="1" <?= $racc ? 'checked' : '' ?> required>
                        <label for="reg_acepta_politicas">
                            Acepto la <a href="<?= $view->url('/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf') ?>" target="_blank" rel="noopener noreferrer">Política de Privacidad y Tratamiento de Datos Personales</a>.
                        </label>
                    </div>

                    <?php if ($recaptchaSiteKey !== ''): ?>
                        <div class="ds-recaptcha">
                            <div class="g-recaptcha" data-sitekey="<?= $e($recaptchaSiteKey) ?>"></div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="login-submit" style="margin-top: 1rem;">
                        <i class="bi bi-send-check"></i>Registrarme
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($recaptchaSiteKey !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <script>
        (function () {
            var modal = document.getElementById('registro-modal');
            if (!modal) { return; }
            var openBtn = document.getElementById('btn-open-registro');
            var form = modal.querySelector('.ds-modal-form');
            var errorBox = document.getElementById('reg-client-errors');
            var errorList = document.getElementById('reg-client-errors-list');
            var hasRecaptcha = <?= $recaptchaSiteKey !== '' ? 'true' : 'false' ?>;

            function openModal() {
                modal.hidden = false;
                document.body.classList.add('ds-modal-open');
                var first = modal.querySelector('select, input:not([type=hidden])');
                if (first) { first.focus(); }
            }
            function closeModal() {
                modal.hidden = true;
                document.body.classList.remove('ds-modal-open');
                if (openBtn) { openBtn.focus(); }
            }

            if (openBtn) { openBtn.addEventListener('click', openModal); }
            modal.querySelectorAll('[data-close]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modal.hidden) { closeModal(); }
            });

            // Celular: permitir solo digitos y maximo 10.
            var cel = document.getElementById('reg_celular');
            if (cel) {
                cel.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 10);
                });
            }

            function val(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
            function showErrors(list) {
                errorList.innerHTML = '';
                list.forEach(function (msg) {
                    var li = document.createElement('li');
                    li.textContent = msg;
                    errorList.appendChild(li);
                });
                errorBox.hidden = list.length === 0;
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    var errors = [];
                    var pwd = document.getElementById('reg_password').value;
                    var pwd2 = document.getElementById('reg_password_confirmacion').value;

                    if (!val('reg_tipo_documento')) { errors.push('Selecciona el tipo de documento.'); }
                    if (!val('reg_documento')) { errors.push('Ingresa tu número de documento.'); }
                    if (!val('reg_apellidos')) { errors.push('Ingresa tus apellidos.'); }
                    if (!val('reg_nombres')) { errors.push('Ingresa tus nombres.'); }
                    if (!/^\d{10}$/.test(val('reg_celular'))) { errors.push('El celular debe tener exactamente 10 dígitos.'); }
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('reg_correo'))) { errors.push('Ingresa un correo electrónico válido.'); }
                    else if (val('reg_correo').toLowerCase() !== val('reg_correo_confirmacion').toLowerCase()) { errors.push('Los correos no coinciden: revisa que esté bien escrito.'); }
                    if (!val('reg_estatura')) { errors.push('Ingresa tu estatura.'); }
                    if (!val('reg_fecha_nacimiento')) { errors.push('Ingresa tu fecha de nacimiento.'); }
                    if (pwd.length < 8 || !/[A-Z]/.test(pwd) || !/[a-z]/.test(pwd) || !/\d/.test(pwd)) {
                        errors.push('La contraseña debe tener mínimo 8 caracteres, con mayúscula, minúscula y número.');
                    }
                    if (pwd !== pwd2) { errors.push('Las contraseñas no coinciden.'); }
                    if (!document.getElementById('reg_acepta_politicas').checked) {
                        errors.push('Debes aceptar las Políticas de Privacidad y Tratamiento de Datos Personales.');
                    }
                    if (hasRecaptcha && typeof grecaptcha !== 'undefined' && !grecaptcha.getResponse()) {
                        errors.push('Confirma que no eres un robot.');
                    }

                    if (errors.length > 0) {
                        e.preventDefault();
                        showErrors(errors);
                        errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            }

            <?php if ($registroOpen): ?>
            document.body.classList.add('ds-modal-open');
            <?php endif; ?>
        })();
    </script>
</body>
</html>
