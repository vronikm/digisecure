<?php
/**
 * Corrección de los datos de registro de un aspirante (rectificación LOPDP).
 *
 * Variables: $user, $csrfToken, $currentPath, $aspirante, $input,
 *            $tieneFicha (bool), $error (?string)
 */

use DigiSecure\Aspirante\Domain\TipoDocumento;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$a = $aspirante;
$id = (string) $a['id'];
$nombre = trim($a['nombres'] . ' ' . $a['apellidos']);
$verificado = (int) ($a['email_verificado'] ?? 0) === 1;
$tieneUsuario = trim((string) ($a['usuario_id'] ?? '')) !== '';
$correoActual = (string) $a['correo'];
$estaturaCm = (int) ($a['estatura_cm'] ?? 0);

$actual = [
    'tipo_documento'   => (string) $a['tipo_documento'],
    'documento'        => (string) $a['documento'],
    'apellidos'        => (string) $a['apellidos'],
    'nombres'          => (string) $a['nombres'],
    'celular'          => (string) $a['celular'],
    'correo'           => $correoActual,
    'estatura'         => $estaturaCm > 0 ? number_format($estaturaCm / 100, 2, '.', '') : '',
    'fecha_nacimiento' => (string) $a['fecha_nacimiento'],
];
$reenviado = $input !== [];
$valor = static fn(string $k): string => (string) ($reenviado ? ($input[$k] ?? '') : ($actual[$k] ?? ''));
$tipoSeleccionado = $tieneFicha ? $actual['tipo_documento'] : $valor('tipo_documento');
$correoCambiado = mb_strtolower(trim($valor('correo')), 'UTF-8') !== mb_strtolower($correoActual, 'UTF-8');
$vence = (string) ($a['verificacion_token_expira_at'] ?? '');
?>

<?php $view->section('title') ?>Editar aspirante - Reclutamiento<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/reclutamiento') ?>">Reclutamiento</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/reclutamiento/' . $id) ?>"><?= $e($nombre) ?></a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ra-correo-actual { display: inline-flex; align-items: center; gap: .35rem; margin-top: .3rem; font-size: .75rem; color: var(--ds-text-soft); }
    .ra-correo-actual code { color: var(--ds-text); background: #eef2f7; padding: .05rem .35rem; border-radius: 5px; }
    .ra-confirmacion[hidden] { display: none !important; }
    .ra-match { font-size: .73rem; margin-top: .25rem; display: none; }
    .ra-match.is-ok { display: block; color: #047857; }
    .ra-match.is-bad { display: block; color: #b91c1c; }
    .ra-check { display: flex; gap: .6rem; align-items: flex-start; font-size: .86rem; color: var(--ds-text); cursor: pointer; }
    .ra-check input { width: 1.05rem; height: 1.05rem; margin-top: .15rem; accent-color: var(--ds-primary); flex-shrink: 0; }
    .ra-check .ds-form-help { display: block; margin-top: .1rem; }
</style>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Corregir datos del aspirante</h1>
            <small><?= $e($nombre) ?> &middot; <span class="ds-cell-mono"><?= $e($actual['documento']) ?></span></small>
        </div>
    </div>
    <a href="<?= $view->url('/reclutamiento/' . $id) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al detalle
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="ds-callout tone-danger" role="alert">
    <i class="bi bi-exclamation-octagon-fill"></i>
    <div><strong>No se pudieron guardar los cambios</strong><?= $e($error) ?></div>
</div>
<?php endif ?>

<?php if (!$verificado): ?>
<div class="ds-callout tone-info" role="status">
    <i class="bi bi-envelope-exclamation"></i>
    <div>
        <strong>El correo aún no está verificado</strong>
        Si lo corriges, se enviará automáticamente un nuevo enlace de verificación al correo corregido (válido 48 horas)
        y el enlace anterior dejará de funcionar.
        <?php if ($vence !== ''): ?><p>Enlace actual vence: <strong style="display:inline"><?= $e($vence) ?></strong></p><?php endif ?>
    </div>
</div>
<?php elseif ($tieneUsuario): ?>
<div class="ds-callout tone-warning" role="status">
    <i class="bi bi-shield-exclamation"></i>
    <div>
        <strong>El aspirante ya tiene cuenta</strong>
        Su correo es también su usuario de inicio de sesión: si lo cambias, deberá ingresar con el correo nuevo
        (la contraseña no cambia). El cambio queda registrado en auditoría.
    </div>
</div>
<?php endif ?>

<?php if ($tieneFicha): ?>
<div class="ds-callout" role="status">
    <i class="bi bi-clipboard2-check"></i>
    <div>
        <strong>Ya completó su ficha de ingreso</strong>
        El documento no se puede cambiar aquí. Estos cambios no modifican la ficha: corrígela desde Guardias si hace falta.
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url('/reclutamiento/' . $id . '/editar') ?>" novalidate>
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-envelope-at"></i></span>
            <div>
                <div class="ds-card-title">Contacto</div>
                <div class="ds-card-subtitle">El correo recibe el enlace de verificación y es el usuario de acceso</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-correo">Correo electrónico <span class="ds-required">*</span></label>
                        <input type="email" id="ra-correo" name="correo" class="form-control" required maxlength="190"
                               autocomplete="off" spellcheck="false"
                               data-original="<?= $e(mb_strtolower($correoActual, 'UTF-8')) ?>"
                               value="<?= $e($valor('correo')) ?>">
                        <span class="ra-correo-actual"><i class="bi bi-clock-history"></i>Registrado: <code><?= $e($correoActual) ?></code></span>
                    </div>
                </div>
                <div class="col-md-6 ra-confirmacion" id="ra-confirmacion-wrap" <?= $correoCambiado ? '' : 'hidden' ?>>
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-correo-confirmacion">Confirma el correo nuevo <span class="ds-required">*</span></label>
                        <input type="email" id="ra-correo-confirmacion" name="correo_confirmacion" class="form-control" maxlength="190"
                               autocomplete="off" spellcheck="false"
                               value="<?= $e($reenviado ? (string) ($input['correo_confirmacion'] ?? '') : '') ?>">
                        <span class="ds-form-help">Escríbelo de nuevo para evitar otro error de digitación.</span>
                        <span class="ra-match" id="ra-match" aria-live="polite"></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-celular">Celular <span class="ds-required">*</span></label>
                        <input type="tel" id="ra-celular" name="celular" class="form-control" required maxlength="20"
                               inputmode="tel" placeholder="0991234567"
                               value="<?= $e($valor('celular')) ?>">
                    </div>
                </div>
                <?php if (!$verificado): ?>
                <div class="col-12">
                    <label class="ra-check" for="ra-reenviar">
                        <input type="checkbox" id="ra-reenviar" name="reenviar_enlace" value="1"
                               <?= $reenviado && !empty($input['reenviar_enlace']) ? 'checked' : '' ?>>
                        <span>
                            Enviar un nuevo enlace de verificación aunque el correo no cambie
                            <span class="ds-form-help">Útil si el enlace anterior venció o el aspirante no lo encuentra.</span>
                        </span>
                    </label>
                </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
            <div>
                <div class="ds-card-title">Identificación y datos personales</div>
                <div class="ds-card-subtitle">Se validan con las mismas reglas del registro</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-tipo">Tipo de documento <span class="ds-required">*</span></label>
                        <select id="ra-tipo" name="tipo_documento" class="form-select" required <?= $tieneFicha ? 'disabled' : '' ?>>
                            <?php foreach (TipoDocumento::casos() as $tipo): ?>
                            <option value="<?= $e($tipo->value) ?>" <?= $tipoSeleccionado === $tipo->value ? 'selected' : '' ?>><?= $e($tipo->label()) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?php if ($tieneFicha): ?>
                        <input type="hidden" name="tipo_documento" value="<?= $e($actual['tipo_documento']) ?>">
                        <?php endif ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-documento">Número de documento <span class="ds-required">*</span></label>
                        <input type="text" id="ra-documento" name="documento" class="form-control ds-cell-mono" required maxlength="20"
                               <?= $tieneFicha ? 'readonly' : '' ?>
                               value="<?= $e($tieneFicha ? $actual['documento'] : $valor('documento')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-fecha">Fecha de nacimiento <span class="ds-required">*</span></label>
                        <input type="date" id="ra-fecha" name="fecha_nacimiento" class="form-control" required
                               value="<?= $e($valor('fecha_nacimiento')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-estatura">Estatura (m) <span class="ds-required">*</span></label>
                        <input type="text" id="ra-estatura" name="estatura" class="form-control" required maxlength="6"
                               inputmode="decimal" placeholder="1.75"
                               value="<?= $e($valor('estatura')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-apellidos">Apellidos <span class="ds-required">*</span></label>
                        <input type="text" id="ra-apellidos" name="apellidos" class="form-control" required maxlength="120"
                               value="<?= $e($valor('apellidos')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="ra-nombres">Nombres <span class="ds-required">*</span></label>
                        <input type="text" id="ra-nombres" name="nombres" class="form-control" required maxlength="120"
                               value="<?= $e($valor('nombres')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/reclutamiento/' . $id) ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>

<script>
(function () {
    var correo = document.getElementById('ra-correo');
    var wrap = document.getElementById('ra-confirmacion-wrap');
    var confirmacion = document.getElementById('ra-correo-confirmacion');
    var match = document.getElementById('ra-match');
    if (!correo || !wrap || !confirmacion || !match) { return; }

    var norm = function (v) { return (v || '').trim().toLowerCase(); };

    var sync = function () {
        var cambiado = norm(correo.value) !== correo.dataset.original;
        wrap.hidden = !cambiado;
        confirmacion.required = cambiado;
        match.className = 'ra-match';
        match.textContent = '';
        if (!cambiado || confirmacion.value === '') { return; }
        var ok = norm(confirmacion.value) === norm(correo.value);
        match.className = 'ra-match ' + (ok ? 'is-ok' : 'is-bad');
        match.textContent = ok ? 'Los correos coinciden.' : 'Los correos no coinciden.';
    };

    correo.addEventListener('input', sync);
    confirmacion.addEventListener('input', sync);
    sync();
})();
</script>
<?php $view->endSection() ?>
