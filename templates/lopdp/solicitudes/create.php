<?php
/**
 * Formulario de registro de solicitud ARCO-P.
 *
 * Variables: $user, $csrfToken, $currentPath, $input, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Nueva solicitud ARCO-P - LOPDP<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp/solicitudes') ?>">Solicitudes</a></li>
<li class="breadcrumb-item active">Nueva</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-plus-circle"></i></span>
        <div>
            <h1>Nueva solicitud ARCO-P</h1>
            <small>Registra el ejercicio de derechos del titular sobre sus datos personales</small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo registrar la solicitud</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Plazo legal de respuesta</strong>
        El plazo de respuesta es de <strong>15 dias calendario</strong> desde la fecha de recepcion (Art. 32 LOPDP Ecuador).
    </div>
</div>

<form method="post" action="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="ds-form-main" id="arcop-create-form">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
    <input type="hidden" name="titular_id" id="titular_id" value="<?= $e($input['titular_id'] ?? '') ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-tag"></i></span>
            <div>
                <div class="ds-card-title">Tipo y canal</div>
                <div class="ds-card-subtitle">Como se recibio la solicitud</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo de derecho ARCO-P <span class="ds-required">*</span></label>
                        <select name="tipo" class="form-select" required>
                            <option value="">- Seleccione -</option>
                            <?php foreach (['acceso','rectificacion','cancelacion','oposicion','portabilidad'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($input['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Canal de recepcion <span class="ds-required">*</span></label>
                        <select name="canal_recepcion" class="form-select" required>
                            <?php foreach (['email','telefono','presencial','formulario_web'] as $c): ?>
                            <option value="<?= $c ?>" <?= ($input['canal_recepcion'] ?? 'email') === $c ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $c)) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha de recepcion <span class="ds-required">*</span></label>
                        <input type="date" name="fecha_recepcion" class="form-control"
                               value="<?= $e($input['fecha_recepcion'] ?? date('Y-m-d')) ?>" required>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
            <div>
                <div class="ds-card-title">Datos del titular</div>
                <div class="ds-card-subtitle">Persona que ejerce el derecho</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo de titular <span class="ds-required">*</span></label>
                        <select name="titular_tipo" id="titular_tipo" class="form-select" required>
                            <option value="">- Seleccione -</option>
                            <?php foreach (['guardia','usuario','externo'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($input['titular_tipo'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Cedula</label>
                        <input type="text" name="titular_cedula" id="titular_cedula" class="form-control"
                               maxlength="10" inputmode="numeric" autocomplete="off"
                               placeholder="Solo 10 digitos"
                               value="<?= $e($input['titular_cedula'] ?? '') ?>">
                        <span id="titular_lookup_status" class="ds-form-help" aria-live="polite">
                            Ingresa la cedula para autocompletar los datos del titular.
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre completo <span class="ds-required">*</span></label>
                        <input type="text" name="titular_nombre" id="titular_nombre" class="form-control" maxlength="200"
                               value="<?= $e($input['titular_nombre'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email</label>
                        <input type="email" name="titular_email" id="titular_email" class="form-control" maxlength="190"
                               value="<?= $e($input['titular_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="titular_telefono" id="titular_telefono" class="form-control" maxlength="30"
                               value="<?= $e($input['titular_telefono'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-warning">
            <span class="ds-card-mark"><i class="bi bi-card-text"></i></span>
            <div>
                <div class="ds-card-title">Descripcion</div>
                <div class="ds-card-subtitle">Detalla el derecho ejercido y los datos a los que se refiere</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-form-field">
                <label class="ds-form-label">Detalle de la solicitud <span class="ds-required">*</span></label>
                <textarea name="descripcion" class="form-control" rows="6" required minlength="10"
                          placeholder="Describe el derecho que el titular ejerce y los datos a los que se refiere..."><?= $e($input['descripcion'] ?? '') ?></textarea>
                <span class="ds-form-help">Minimo 10 caracteres. Se conservara como evidencia ante una eventual auditoria de la SPDP.</span>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Registrar solicitud
        </button>
    </div>
</form>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
(() => {
    const tipoInput = document.getElementById('titular_tipo');
    const cedulaInput = document.getElementById('titular_cedula');
    const nombreInput = document.getElementById('titular_nombre');
    const emailInput = document.getElementById('titular_email');
    const telefonoInput = document.getElementById('titular_telefono');
    const titularIdInput = document.getElementById('titular_id');
    const statusNode = document.getElementById('titular_lookup_status');
    const tokenInput = document.querySelector('#arcop-create-form input[name="_token"]');

    if (!tipoInput || !cedulaInput || !nombreInput || !emailInput || !telefonoInput || !titularIdInput || !statusNode || !tokenInput) {
        return;
    }

    const endpoint = <?= json_encode($view->url('/lopdp/solicitudes/titular/buscar'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let debounceTimer = null;
    let inFlight = null;
    let lastLookupKey = '';

    const setStatus = (message, tone = 'muted') => {
        statusNode.textContent = message;
        statusNode.style.color = tone === 'ok'
            ? '#166534'
            : (tone === 'warn'
                ? '#b45309'
                : (tone === 'error' ? '#b91c1c' : 'var(--ds-text-soft)'));
    };

    const normalizeCedula = (value) => String(value || '').replace(/\D+/g, '').slice(0, 10);

    const clearLink = () => {
        titularIdInput.value = '';
    };

    const applyTitularData = (titular) => {
        titularIdInput.value = String(titular.titular_id || '');
        nombreInput.value = String(titular.titular_nombre || '');
        emailInput.value = String(titular.titular_email || '');
        telefonoInput.value = String(titular.titular_telefono || '');

        const origen = String(titular.origen || '').trim();
        const referencia = String(titular.referencia || '').trim();
        if (referencia !== '') {
            setStatus(`Datos cargados automaticamente (${origen}: ${referencia}).`, 'ok');
            return;
        }
        setStatus('Datos cargados automaticamente.', 'ok');
    };

    const runLookup = async () => {
        const tipo = String(tipoInput.value || '').trim().toLowerCase();
        const cedula = normalizeCedula(cedulaInput.value);
        cedulaInput.value = cedula;

        if (tipo === '' || tipo === 'externo') {
            clearLink();
            setStatus(tipo === 'externo'
                ? 'Titular externo: completa los datos manualmente.'
                : 'Selecciona el tipo de titular para habilitar autocompletado.');
            return;
        }

        if (cedula.length < 10) {
            clearLink();
            setStatus('Ingresa los 10 digitos de la cedula para buscar.', 'muted');
            return;
        }

        const lookupKey = `${tipo}|${cedula}`;
        if (lookupKey === lastLookupKey) {
            return;
        }
        lastLookupKey = lookupKey;

        if (inFlight instanceof AbortController) {
            inFlight.abort();
        }
        inFlight = new AbortController();

        setStatus('Buscando titular...', 'muted');

        const payload = new URLSearchParams();
        payload.set('_token', String(tokenInput.value || ''));
        payload.set('titular_tipo', tipo);
        payload.set('cedula', cedula);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload.toString(),
                signal: inFlight.signal,
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.ok === false) {
                clearLink();
                setStatus(String(data.message || 'No fue posible completar los datos con la cedula ingresada.'), 'warn');
                return;
            }

            if (data.found !== true || typeof data.titular !== 'object' || data.titular === null) {
                clearLink();
                setStatus(String(data.message || 'No se encontro coincidencia en esta empresa.'), 'warn');
                return;
            }

            applyTitularData(data.titular);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            clearLink();
            setStatus('No se pudo consultar el titular en este momento.', 'error');
        }
    };

    const scheduleLookup = () => {
        if (debounceTimer !== null) {
            window.clearTimeout(debounceTimer);
        }
        debounceTimer = window.setTimeout(runLookup, 350);
    };

    tipoInput.addEventListener('change', () => {
        lastLookupKey = '';
        if (String(tipoInput.value || '').trim().toLowerCase() === 'externo') {
            clearLink();
        }
        scheduleLookup();
    });

    cedulaInput.addEventListener('input', () => {
        cedulaInput.value = normalizeCedula(cedulaInput.value);
        lastLookupKey = '';
        scheduleLookup();
    });

    cedulaInput.addEventListener('blur', scheduleLookup);
    runLookup();
})();
</script>
<?php $view->endSection() ?>
