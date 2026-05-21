<?php
/**
 * Formulario de creación de ficha de guardia.
 *
 * Variables: $user, $csrfToken, $currentPath, $cursos, $input (array para repoblar)
 * Opcionales: $error (string)
 */

use DigiSecure\Ficha\Domain\Enum\EstadoCivil;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoSangre;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$old = static fn(string $key, string $default = '') => $e((string)($input[$key] ?? $default));
$prefillTrabajos = array_values(is_array($input['trabajos_anteriores'] ?? null) ? $input['trabajos_anteriores'] : []);
$prefillHijos = array_values(is_array($input['hijos_menores'] ?? null) ? $input['hijos_menores'] : []);
$prefillReferencias = array_values(is_array($input['referencias'] ?? null) ? $input['referencias'] : []);
$cursosAsignados = [];
foreach ((array)($input['cursos'] ?? []) as $cursoInput) {
    $cursoId = (string)($cursoInput['curso_id'] ?? '');
    if ($cursoId === '') {
        continue;
    }
    $cursosAsignados[$cursoId] = $cursoInput;
}

$formPageTitle = isset($formPageTitle) && is_string($formPageTitle) && $formPageTitle !== ''
    ? $formPageTitle
    : 'Nueva Ficha';
$formBreadcrumbParentLabel = isset($formBreadcrumbParentLabel) && is_string($formBreadcrumbParentLabel)
    ? $formBreadcrumbParentLabel
    : 'Guardias';
$formBreadcrumbParentUrl = isset($formBreadcrumbParentUrl) && is_string($formBreadcrumbParentUrl) && $formBreadcrumbParentUrl !== ''
    ? $formBreadcrumbParentUrl
    : '/guardias';
$formBreadcrumbCurrent = isset($formBreadcrumbCurrent) && is_string($formBreadcrumbCurrent) && $formBreadcrumbCurrent !== ''
    ? $formBreadcrumbCurrent
    : 'Nueva Ficha';
$formKickerLabel = isset($formKickerLabel) && is_string($formKickerLabel) && $formKickerLabel !== ''
    ? $formKickerLabel
    : 'Nueva Ficha de Guardia';
$formBackUrl = isset($formBackUrl) && is_string($formBackUrl) && $formBackUrl !== ''
    ? $formBackUrl
    : '/guardias';
$formBackLabel = isset($formBackLabel) && is_string($formBackLabel) && $formBackLabel !== ''
    ? $formBackLabel
    : 'Volver';
$formAction = isset($formAction) && is_string($formAction) && $formAction !== ''
    ? $formAction
    : '/guardias/crear';
$formCancelUrl = isset($formCancelUrl) && is_string($formCancelUrl) && $formCancelUrl !== ''
    ? $formCancelUrl
    : '/guardias';
$formCancelLabel = isset($formCancelLabel) && is_string($formCancelLabel) && $formCancelLabel !== ''
    ? $formCancelLabel
    : 'Cancelar';
$formSubmitLabel = isset($formSubmitLabel) && is_string($formSubmitLabel) && $formSubmitLabel !== ''
    ? $formSubmitLabel
    : 'Crear Ficha';
$formSubmitIcon = isset($formSubmitIcon) && is_string($formSubmitIcon) && $formSubmitIcon !== ''
    ? $formSubmitIcon
    : 'bi bi-save';
$formShowCancel = isset($formShowCancel) ? (bool)$formShowCancel : true;
$formSuccessMessage = isset($formSuccessMessage) && is_string($formSuccessMessage)
    ? $formSuccessMessage
    : '';
$formRequireConfirm = isset($formRequireConfirm) ? (bool)$formRequireConfirm : false;
$formConfirmTitle = isset($formConfirmTitle) && is_string($formConfirmTitle) && $formConfirmTitle !== ''
    ? $formConfirmTitle
    : 'Confirmar cambios';
$formConfirmText = isset($formConfirmText) && is_string($formConfirmText) && $formConfirmText !== ''
    ? $formConfirmText
    : 'Se actualizará la ficha con la información ingresada.';
$formConfirmButton = isset($formConfirmButton) && is_string($formConfirmButton) && $formConfirmButton !== ''
    ? $formConfirmButton
    : 'Sí, guardar';
$formConfirmCancel = isset($formConfirmCancel) && is_string($formConfirmCancel) && $formConfirmCancel !== ''
    ? $formConfirmCancel
    : 'Cancelar';
$defaultTipoFicha = isset($input['tipo_ficha']) && is_string($input['tipo_ficha']) && $input['tipo_ficha'] !== ''
    ? $input['tipo_ficha']
    : 'INGRESO';
?>

<?php $view->section('title') ?><?= $e($formPageTitle) ?> — DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-form-kicker {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .85rem;
        padding: .38rem .72rem;
        border-radius: 999px;
        background: rgba(47, 128, 237, .12);
        color: #1d4d8f;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .ds-create-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .75rem;
        flex-wrap: nowrap;
    }
    .ds-create-header .ds-form-kicker {
        margin-bottom: 0;
    }
    .ds-create-header .ds-btn {
        margin-left: auto;
        white-space: nowrap;
    }
    .ds-breadcrumb {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .ds-breadcrumb .breadcrumb-item {
        display: inline-flex;
        align-items: center;
        list-style: none;
    }
    .ds-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
        content: '/';
        margin: 0 .4rem;
        color: var(--ds-text-soft);
        font-weight: 500;
    }
    .ds-step-nav {
        position: sticky;
        top: 1rem;
    }
    .ds-step-nav-card,
    .ds-summary-card {
        border: 0;
        border-radius: 24px;
        box-shadow: 0 16px 30px rgba(18, 45, 77, .08);
    }
    .ds-step-nav-card .card-body,
    .ds-summary-card .card-body {
        padding: 1.1rem;
    }
    .ds-step-list {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
    }
    @media (max-width: 991.98px) {
        .ds-step-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .ds-step-list { grid-template-columns: 1fr; }
    }
    .ds-step-link {
        display: flex;
        align-items: center;
        gap: .65rem;
        width: 100%;
        padding: .75rem .8rem;
        border: 1px solid rgba(21, 48, 79, .08);
        border-radius: 16px;
        background: #fff;
        color: #16324f;
        text-align: left;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .ds-step-link.active {
        border-color: rgba(47, 128, 237, .28);
        box-shadow: 0 12px 24px rgba(18, 45, 77, .08);
        transform: translateY(-1px);
    }
    .ds-step-index {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: rgba(47, 128, 237, .12);
        color: #2f80ed;
        font-weight: 700;
        flex: 0 0 auto;
        font-size: .9rem;
    }
    .ds-step-copy {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1 1 auto;
    }
    .ds-step-copy strong {
        font-size: .88rem;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ds-step-copy span {
        color: #607489;
        font-size: .72rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ds-step-status {
        margin-left: auto;
        font-size: .7rem;
        font-weight: 700;
        flex: 0 0 auto;
    }
    .ds-step-panel {
        display: none;
    }
    .ds-step-panel.active {
        display: block;
    }
    .ds-form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    .ds-progress-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        margin-bottom: .5rem;
    }
    .ds-summary-card .progress {
        height: 12px;
        border-radius: 999px;
        background: rgba(21, 48, 79, .08);
    }
    .ds-summary-list {
        display: grid;
        gap: .7rem;
        margin-top: 1rem;
    }
    .ds-summary-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem .9rem;
        border-radius: 16px;
        background: rgba(21, 48, 79, .04);
        border: 1px solid transparent;
    }
    .ds-summary-item.active {
        border-color: rgba(47, 128, 237, .25);
        background: rgba(47, 128, 237, .08);
    }
    .ds-summary-note {
        margin-top: .85rem;
        padding: .8rem .9rem;
        border-radius: 16px;
        background: rgba(21, 48, 79, .04);
        color: #607489;
        font-size: .84rem;
    }
    .ds-repeat-card {
        padding: 1rem;
        border-radius: 16px;
        background: rgba(21, 48, 79, .03);
        border: 1px solid rgba(21, 48, 79, .08);
    }
    .ds-repeat-card + .ds-repeat-card {
        margin-top: .85rem;
    }
    @media (max-width: 1199.98px) {
        .ds-step-nav {
            position: static;
        }
    }

    /* ====== Estilos del formulario alineados con ds-system ====== */
    /* Scoped al form para no afectar otros componentes de la pagina */

    #fichaForm .card {
        background: #fff;
        border: 1px solid rgba(21, 48, 79, 0.08);
        border-radius: 14px;
        box-shadow: 0 4px 14px rgba(9, 29, 53, 0.05);
        overflow: hidden;
        margin-bottom: 0.9rem;
    }
    #fichaForm .card.ds-step-nav-card {
        /* la stepper card mantiene su propio look mas redondeado */
        border-radius: 20px;
        box-shadow: 0 12px 24px rgba(18, 45, 77, 0.07);
    }

    #fichaForm .card-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1rem;
        background: linear-gradient(180deg, #fbfcfe, #f6f8fb);
        border-bottom: 1px solid #eef1f5;
        font-weight: 700;
        font-size: 0.88rem;
        color: #16324f;
    }
    #fichaForm .card-header > i.bi {
        width: 26px; height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: rgba(47, 128, 237, 0.12);
        color: #2f80ed;
        font-size: 0.9rem;
        margin-right: 0.35rem;
    }

    #fichaForm .card-body {
        padding: 0.85rem 1rem 0.95rem;
        background: #fff;
    }

    /* Sub-header para agrupar campos dentro de una card */
    #fichaForm .ds-subheader {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #607489;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px dashed rgba(21, 48, 79, .12);
    }
    /* Mayor separación cuando hay un sub-header después del bloque anterior */
    #fichaForm .ds-subheader.mt-3 {
        margin-top: 1.5rem !important;
        padding-top: 0.85rem;
        border-top: 1px solid rgba(21, 48, 79, .06);
    }
    #fichaForm .ds-subheader i.bi {
        color: #2f80ed;
        font-size: 0.85rem;
    }

    /* Espaciado vertical entre campos del grid (col-md-*) */
    #fichaForm .row > [class*="col-"] {
        margin-bottom: 0.55rem;
    }
    #fichaForm .row > [class*="col-"]:last-child {
        margin-bottom: 0;
    }

    /* ====== CSS Grid auto-fit para campos compactos (independiente de Bootstrap) ====== */
    #fichaForm .ff-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.6rem 0.75rem;
        align-items: end; /* alinear inputs al fondo aunque labels tengan distintas alturas */
    }
    #fichaForm .ff-row > div {
        display: flex;
        flex-direction: column;
    }
    #fichaForm .ff-row > div > .form-label {
        margin-top: auto; /* empuja label hacia abajo si la celda es alta */
    }
    #fichaForm .ff-row > .ff-span-2 { grid-column: span 2; }
    #fichaForm .ff-row > .ff-span-3 { grid-column: span 3; }
    #fichaForm .ff-row > .ff-span-4 { grid-column: span 4; }
    #fichaForm .ff-row > .ff-span-full { grid-column: 1 / -1; }
    /* Campos angostos (numero, edad) */
    #fichaForm .ff-row > .ff-narrow {
        max-width: 130px;
    }

    /* Input con icono de validación (cédula) */
    #fichaForm .ff-input-with-icon {
        position: relative;
    }
    #fichaForm .ff-input-with-icon > .form-control {
        padding-right: 2.2rem;
    }
    #fichaForm .ff-input-icon {
        position: absolute;
        right: 0.65rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1rem;
        line-height: 1;
        pointer-events: none;
        display: none;
    }
    #fichaForm .ff-input-with-icon.is-valid > .ff-input-icon {
        display: inline-block;
        color: #059669;
    }
    #fichaForm .ff-input-with-icon.is-invalid > .ff-input-icon {
        display: inline-block;
        color: #dc2626;
    }
    #fichaForm .ff-input-with-icon.is-valid > .form-control {
        border-color: #059669;
    }
    #fichaForm .ff-input-with-icon.is-invalid > .form-control {
        border-color: #dc2626;
    }
    #fichaForm .ff-input-with-icon.is-valid > .form-control:focus {
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
    }
    #fichaForm .ff-input-with-icon.is-invalid > .form-control:focus {
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
    }
    #fichaForm .ff-error-text {
        display: none;
        font-size: 0.72rem;
        font-weight: 600;
        color: #dc2626;
        margin-top: 0.25rem;
        line-height: 1.2;
    }
    #fichaForm .ff-error-text.show {
        display: block;
    }
    @media (max-width: 480px) {
        #fichaForm .ff-row {
            grid-template-columns: 1fr 1fr;
        }
        #fichaForm .ff-row > .ff-span-2,
        #fichaForm .ff-row > .ff-span-3,
        #fichaForm .ff-row > .ff-span-4,
        #fichaForm .ff-row > .ff-span-full {
            grid-column: 1 / -1;
        }
        #fichaForm .ff-row > .ff-narrow { max-width: 100%; }
    }

    /* Labels y campos */
    #fichaForm .form-label {
        display: block; /* etiqueta en su propia linea */
        font-size: 0.74rem;
        font-weight: 600;
        color: #16324f;
        margin-bottom: 0.2rem;
    }
    #fichaForm .form-label .text-danger {
        color: #dc2626 !important;
        font-weight: 700;
        margin-left: 0.15rem;
    }

    #fichaForm .form-control,
    #fichaForm .form-select {
        display: block;
        width: 100%;
        border: 1px solid #d8dfe7;
        border-radius: 9px;
        padding: 0.38rem 0.7rem;
        font-size: 0.84rem;
        color: #16324f;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
        box-shadow: 0 1px 0 rgba(21, 48, 79, 0.02);
    }
    #fichaForm .form-control:focus,
    #fichaForm .form-select:focus {
        border-color: #2f80ed;
        box-shadow: 0 0 0 3px rgba(47, 128, 237, 0.15);
        outline: 0;
    }
    #fichaForm .form-control:disabled,
    #fichaForm .form-select:disabled {
        background: #f4f7fb;
        color: #5f7288;
    }
    #fichaForm .form-control.is-invalid:not(:focus) {
        border-color: #dc2626;
    }
    #fichaForm .form-text {
        font-size: 0.7rem;
        color: #5f7288;
        margin-top: 0.15rem;
        line-height: 1.2;
    }

    /* Botones del form: usar el look del sistema */
    #fichaForm .btn {
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
        transition: transform .12s, box-shadow .12s;
    }
    #fichaForm .btn:not(:disabled):hover {
        transform: translateY(-1px);
    }
    #fichaForm .btn-primary {
        background: linear-gradient(135deg, #2f80ed, #1e63c5);
        border: 0;
        color: #fff;
        box-shadow: 0 4px 14px rgba(47, 128, 237, 0.32);
    }
    #fichaForm .btn-primary:hover {
        box-shadow: 0 8px 20px rgba(47, 128, 237, 0.42);
    }
    #fichaForm .btn-outline-secondary {
        background: #fff;
        color: #16324f;
        border: 1px solid #d8dfe7;
    }
    #fichaForm .btn-outline-secondary:hover {
        background: #f4f7fb;
        color: #16324f;
        border-color: #c4cdd9;
    }
    #fichaForm .btn-outline-primary {
        background: transparent;
        color: #2f80ed;
        border: 1px solid rgba(47, 128, 237, 0.4);
    }
    #fichaForm .btn-outline-primary:hover {
        background: rgba(47, 128, 237, 0.08);
        color: #1e63c5;
        border-color: #2f80ed;
    }
    #fichaForm .btn-outline-danger {
        background: transparent;
        color: #dc2626;
        border: 1px solid rgba(220, 38, 38, 0.3);
    }
    #fichaForm .btn-outline-danger:hover {
        background: rgba(220, 38, 38, 0.08);
        color: #b91c1c;
    }
    #fichaForm .btn-sm {
        padding: 0.35rem 0.7rem;
        font-size: 0.78rem;
        border-radius: 8px;
    }

    /* Bottom action bar override (override del .ds-form-actions del top) */
    #fichaForm .ds-form-actions {
        margin-top: 1rem;
        padding: 1rem 0;
        border-top: 1px solid #eef1f5;
    }

    /* Tablas internas (cursos) */
    #fichaForm table.table {
        font-size: 0.85rem;
    }
    #fichaForm table.table th,
    #fichaForm table.table td {
        padding: 0.55rem 0.65rem;
        vertical-align: middle;
    }

    /* Sub-tarjetas para repeticiones (trabajos, hijos, referencias) */
    #fichaForm .trabajo-row,
    #fichaForm .hijo-row,
    #fichaForm .ref-row {
        padding: 0.85rem;
        border: 1px solid #eef1f5;
        border-radius: 12px;
        background: #fbfcfe;
        margin-bottom: 0.65rem;
    }

    /* Sidebar de resumen */
    .ds-summary-card .progress-bar {
        background: linear-gradient(90deg, #2f80ed, #1e63c5);
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<?php if ($formBreadcrumbParentLabel !== ''): ?>
<li class="breadcrumb-item"><a href="<?= $view->url($formBreadcrumbParentUrl) ?>"><?= $e($formBreadcrumbParentLabel) ?></a></li>
<?php endif ?>
<li class="breadcrumb-item active" aria-current="page"><?= $e($formBreadcrumbCurrent) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-create-header">
    <span class="ds-form-kicker"><i class="bi bi-ui-checks-grid"></i><?= $e($formKickerLabel) ?></span>
    <a href="<?= $view->url($formBackUrl) ?>" class="ds-btn ds-btn-outline ds-btn-sm">
        <i class="bi bi-arrow-left"></i><?= $e($formBackLabel) ?>
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>
<?php if ($formSuccessMessage !== ''): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= $e($formSuccessMessage) ?></div>
<?php endif ?>

<div class="row g-4">
    <div class="col-xl-8">
<form method="post" action="<?= $view->url($formAction) ?>" id="fichaForm" class="ds-form-main" novalidate>
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="card ds-step-nav-card shadow-sm mb-4">
        <div class="card-body">
            <div class="ds-step-list">
                <button type="button" class="ds-step-link active" data-step-target="base">
                    <span class="ds-step-index">1</span>
                    <span class="ds-step-copy">
                        <strong>Base</strong>
                        <span>Tipo y datos personales</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="base">0/6</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="contacto">
                    <span class="ds-step-index">2</span>
                    <span class="ds-step-copy">
                        <strong>Contacto</strong>
                        <span>Domicilio y contacto</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="contacto">0/0</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="historial">
                    <span class="ds-step-index">3</span>
                    <span class="ds-step-copy">
                        <strong>Historial</strong>
                        <span>Finanzas, instruccion y referencias</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="historial">0/0</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="cursos">
                    <span class="ds-step-index">4</span>
                    <span class="ds-step-copy">
                        <strong>Cursos</strong>
                        <span>Seleccion y confirmacion</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="cursos">0/0</span>
                </button>
            </div>
        </div>
    </div>

    <div class="ds-step-panel active" data-step-panel="base">

    <!-- Datos Personales (incluye Tipo de Ficha) -->
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-person me-2"></i>Datos Personales</div>
        <div class="card-body">
            <div class="ds-subheader"><i class="bi bi-tag"></i>Identificación</div>
            <div class="ff-row">
                <div>
                    <label class="form-label">Tipo de Ficha <span class="text-danger">*</span></label>
                    <select name="tipo_ficha" class="form-select" required>
                        <?php foreach (TipoFicha::cases() as $tipo): ?>
                        <option value="<?= $tipo->value ?>" <?= $defaultTipoFicha === $tipo->value ? 'selected' : '' ?>>
                            <?= $e($tipo->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Cédula <span class="text-danger">*</span></label>
                    <div class="ff-input-with-icon" id="cedula-wrap">
                        <input type="text" name="cedula" class="form-control" required maxlength="10" minlength="10"
                               pattern="\d{10}" inputmode="numeric" autocomplete="off"
                               placeholder="Ej: 1712345678" value="<?= $old('cedula') ?>">
                        <i class="bi ff-input-icon" id="cedula-icon" aria-hidden="true"></i>
                    </div>
                    <div class="ff-error-text" id="cedula-error"></div>
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" name="apellidos" class="form-control" required maxlength="120" value="<?= $old('apellidos') ?>">
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Nombres <span class="text-danger">*</span></label>
                    <input type="text" name="nombres" class="form-control" required maxlength="120" value="<?= $old('nombres') ?>">
                </div>
            </div>

            <div class="ds-subheader mt-3"><i class="bi bi-person-vcard"></i>Datos demográficos</div>
            <div class="ff-row">
                <div>
                    <label class="form-label">Género <span class="text-danger">*</span></label>
                    <select name="genero" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach (Genero::cases() as $g): ?>
                        <option value="<?= $g->value ?>" <?= ($input['genero'] ?? '') === $g->value ? 'selected' : '' ?>>
                            <?= $e($g->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_nacimiento" class="form-control" required value="<?= $old('fecha_nacimiento') ?>">
                </div>
                <div>
                    <label class="form-label">Estado Civil</label>
                    <select name="estado_civil" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach (EstadoCivil::cases() as $ec): ?>
                        <option value="<?= $ec->value ?>" <?= ($input['estado_civil'] ?? '') === $ec->value ? 'selected' : '' ?>>
                            <?= $e($ec->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tipo de Sangre</label>
                    <select name="tipo_sangre" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach (TipoSangre::cases() as $ts): ?>
                        <option value="<?= $e($ts->value) ?>" <?= ($input['tipo_sangre'] ?? '') === $ts->value ? 'selected' : '' ?>>
                            <?= $e($ts->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Estatura (m)</label>
                    <input type="number" name="estatura" class="form-control" step="0.01" min="1.00" max="2.50"
                           placeholder="1.70" value="<?= $old('estatura') ?>">
                </div>
                <div>
                    <label class="form-label">Ciudad de Nacimiento</label>
                    <input type="text" name="lugar_nacimiento_ciudad" class="form-control" maxlength="100" value="<?= $old('lugar_nacimiento_ciudad') ?>">
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Provincia de Nacimiento</label>
                    <input type="text" name="lugar_nacimiento_provincia" class="form-control" maxlength="100" value="<?= $old('lugar_nacimiento_provincia') ?>">
                </div>
            </div>

            <div class="ds-subheader mt-3"><i class="bi bi-telephone"></i>Contacto directo</div>
            <div class="ff-row">
                <div>
                    <label class="form-label">Celular</label>
                    <input type="tel" name="celular" class="form-control" maxlength="30" value="<?= $old('celular') ?>">
                </div>
                <div class="ff-span-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="190" value="<?= $old('email') ?>">
                </div>
            </div>
        </div>
    </div>

    </div>

    <div class="ds-step-panel" data-step-panel="contacto">
    <!-- Contacto -->
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-house me-2"></i>Domicilio y contacto</div>
        <div class="card-body">
            <div class="ds-subheader"><i class="bi bi-geo-alt"></i>Dirección</div>
            <div class="ff-row">
                <div>
                    <label class="form-label">Ciudad</label>
                    <input type="text" name="domicilio_ciudad" class="form-control" maxlength="100" value="<?= $old('domicilio_ciudad') ?>">
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Calle</label>
                    <input type="text" name="domicilio_calle" class="form-control" maxlength="255" value="<?= $old('domicilio_calle') ?>">
                </div>
                <div class="ff-narrow">
                    <label class="form-label">Número</label>
                    <input type="text" name="domicilio_numero" class="form-control" maxlength="30" value="<?= $old('domicilio_numero') ?>">
                </div>
                <div>
                    <label class="form-label">Barrio</label>
                    <input type="text" name="domicilio_barrio" class="form-control" maxlength="150" value="<?= $old('domicilio_barrio') ?>">
                </div>
                <div>
                    <label class="form-label">Tipo de Vivienda</label>
                    <select name="domicilio_tipo_vivienda" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach (['PROPIA' => 'Propia', 'ARRENDADA' => 'Arrendada', 'FAMILIAR' => 'Familiar'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($input['domicilio_tipo_vivienda'] ?? '') === $v ? 'selected' : '' ?>>
                            <?= $l ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Teléfono Domicilio</label>
                    <input type="tel" name="domicilio_telefono" class="form-control" maxlength="30" value="<?= $old('domicilio_telefono') ?>">
                </div>
                <div class="ff-span-full">
                    <label class="form-label">Referencia (cómo llegar)</label>
                    <input type="text" name="domicilio_referencia" class="form-control" maxlength="255" value="<?= $old('domicilio_referencia') ?>">
                </div>
            </div>

            <div class="ds-subheader mt-3"><i class="bi bi-telephone-plus"></i>Contacto de emergencia</div>
            <div class="ff-row">
                <div class="ff-span-2">
                    <label class="form-label">Nombre del Contacto</label>
                    <input type="text" name="contacto_emergencia_nombre" class="form-control" maxlength="200" value="<?= $old('contacto_emergencia_nombre') ?>">
                </div>
                <div>
                    <label class="form-label">Teléfono del Contacto</label>
                    <input type="tel" name="contacto_emergencia_telefono" class="form-control" maxlength="30" value="<?= $old('contacto_emergencia_telefono') ?>">
                </div>
            </div>
        </div>
    </div>

    </div>

    <div class="ds-step-panel" data-step-panel="historial">
    <!-- Finanzas e instrucción -->
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-bank me-2"></i>Finanzas e instrucción</div>
        <div class="card-body">
            <div class="ds-subheader"><i class="bi bi-cash-coin"></i>Datos financieros</div>
            <div class="ff-row">
                <div class="ff-span-2">
                    <label class="form-label">Banco</label>
                    <input type="text" name="banco" class="form-control" maxlength="120" value="<?= $old('banco') ?>">
                </div>
                <div>
                    <label class="form-label">Tipo de Cuenta</label>
                    <select name="tipo_cuenta" class="form-select">
                        <option value="">Seleccione...</option>
                        <option value="AHORROS" <?= ($input['tipo_cuenta'] ?? '') === 'AHORROS' ? 'selected' : '' ?>>Ahorros</option>
                        <option value="CORRIENTE" <?= ($input['tipo_cuenta'] ?? '') === 'CORRIENTE' ? 'selected' : '' ?>>Corriente</option>
                    </select>
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Número de Cuenta Bancaria</label>
                    <input type="text" name="cuenta_bancaria" class="form-control" maxlength="30" value="<?= $old('cuenta_bancaria') ?>">
                </div>
            </div>

            <div class="ds-subheader mt-3"><i class="bi bi-mortarboard"></i>Nivel de instrucción</div>
            <div class="ff-row">
                <div>
                    <label class="form-label">Instrucción Primaria</label>
                    <input type="text" name="instruccion_primaria" class="form-control" maxlength="200"
                           placeholder="Nombre de la institución" value="<?= $old('instruccion_primaria') ?>">
                </div>
                <div>
                    <label class="form-label">Instrucción Secundaria</label>
                    <input type="text" name="instruccion_secundaria" class="form-control" maxlength="200"
                           placeholder="Bachiller" value="<?= $old('instruccion_secundaria') ?>">
                </div>
                <div>
                    <label class="form-label">Instrucción Superior</label>
                    <input type="text" name="instruccion_superior" class="form-control" maxlength="200" value="<?= $old('instruccion_superior') ?>">
                </div>
                <div>
                    <label class="form-label">Fecha Último Reentrenamiento</label>
                    <input type="date" name="fecha_ultimo_reentrenamiento" class="form-control" value="<?= $old('fecha_ultimo_reentrenamiento') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Repeaters: Trabajos, Hijos, Referencias -->
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-collection me-2"></i>Historial laboral y referencias</div>
        <div class="card-body">
            <div class="ds-subheader d-flex justify-content-between align-items-center">
                <span><i class="bi bi-briefcase"></i>Trabajos anteriores</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTrabajo()">
                    <i class="bi bi-plus-lg me-1"></i>Agregar
                </button>
            </div>
            <div id="trabajos-container">
                <p class="text-muted small mb-0" id="trabajos-empty">
                    <i class="bi bi-info-circle me-1"></i>Requisito básico: al menos 1 trabajo anterior.
                </p>
            </div>

            <div class="ds-subheader d-flex justify-content-between align-items-center mt-3">
                <span><i class="bi bi-people"></i>Hijos menores</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addHijo()">
                    <i class="bi bi-plus-lg me-1"></i>Agregar
                </button>
            </div>
            <div id="hijos-container">
                <p class="text-muted small mb-0" id="hijos-empty">Sin hijos menores registrados.</p>
            </div>

            <div class="ds-subheader d-flex justify-content-between align-items-center mt-3">
                <span><i class="bi bi-person-lines-fill"></i>Referencias personales</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addReferencia()">
                    <i class="bi bi-plus-lg me-1"></i>Agregar
                </button>
            </div>
            <div id="referencias-container">
                <p class="text-muted small mb-0" id="referencias-empty">Sin referencias registradas.</p>
            </div>
        </div>
    </div>

    </div>

    <div class="ds-step-panel" data-step-panel="cursos">
    <!-- 9. Cursos de Seguridad -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-award me-2"></i>Cursos de Seguridad</div>
        <div class="card-body">
            <?php if ($cursos === []): ?>
            <p class="text-muted small">No hay cursos en el catálogo.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th style="width:30px"></th><th>Código</th><th>Curso</th><th>Fecha Obtención</th><th>Observación</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $i => $curso):
                            $asignado = $cursosAsignados[$curso['id']] ?? null;
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input curso-check"
                                       data-index="<?= $i ?>" id="curso_<?= $i ?>" <?= $asignado ? 'checked' : '' ?>>
                            </td>
                            <td><label for="curso_<?= $i ?>"><code><?= $e($curso['codigo']) ?></code></label></td>
                            <td><label for="curso_<?= $i ?>"><?= $e($curso['nombre']) ?></label></td>
                            <td>
                                <input type="date" class="form-control form-control-sm curso-fecha" data-index="<?= $i ?>"
                                       value="<?= $e((string)($asignado['fecha_obtencion'] ?? '')) ?>" <?= $asignado ? '' : 'disabled' ?>>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm curso-obs" data-index="<?= $i ?>"
                                       maxlength="200" <?= $asignado ? '' : 'disabled' ?> placeholder="Opcional"
                                       value="<?= $e((string)($asignado['observacion'] ?? '')) ?>">
                            </td>
                            <input type="hidden" class="curso-id" data-index="<?= $i ?>" value="<?= $e($curso['id']) ?>">
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>

    </div>

    <!-- Botones -->
    <div class="ds-form-actions mb-5">
        <button type="button" class="btn btn-outline-secondary" data-step-prev disabled>
            <i class="bi bi-arrow-left me-1"></i>Anterior
        </button>
        <div class="d-flex flex-wrap justify-content-end gap-2">
            <?php if ($formShowCancel): ?>
            <a href="<?= $view->url($formCancelUrl) ?>" class="btn btn-outline-secondary"><?= $e($formCancelLabel) ?></a>
            <?php endif ?>
            <button type="button" class="btn btn-primary" data-step-next>
                Siguiente<i class="bi bi-arrow-right ms-1"></i>
            </button>
            <button type="submit" class="btn btn-primary d-none" data-step-submit>
                <i class="<?= $e($formSubmitIcon) ?> me-1"></i><?= $e($formSubmitLabel) ?>
            </button>
        </div>
    </div>
</form>
    </div>

    <div class="col-xl-4">
        <aside class="ds-step-nav">
            <div class="card ds-summary-card mb-4">
                <div class="card-body">                    

                    <div class="ds-progress-label">
                        <span class="small text-muted">Campos obligatorios completos</span>
                        <strong id="requiredProgressText">0%</strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" id="requiredProgressBar" style="width:0%"></div>
                    </div>

                </div>
            </div>
        </aside>
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<?php if ($formRequireConfirm): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php endif ?>
<script>
const STEP_ORDER = ['base', 'contacto', 'historial', 'cursos'];
const prefillTrabajos = <?= json_encode($prefillTrabajos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const prefillHijos = <?= json_encode($prefillHijos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const prefillReferencias = <?= json_encode($prefillReferencias, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const REQUIRE_SUBMIT_CONFIRM = <?= $formRequireConfirm ? 'true' : 'false' ?>;
const SUBMIT_CONFIRM_CONFIG = <?= json_encode([
    'title' => $formConfirmTitle,
    'text' => $formConfirmText,
    'confirmButtonText' => $formConfirmButton,
    'cancelButtonText' => $formConfirmCancel,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let currentStepIndex = 0;

function esc(v) {
    if (v == null) return '';
    const d = document.createElement('div');
    d.textContent = String(v);
    return d.innerHTML;
}

function getPanel(step) {
    return document.querySelector(`[data-step-panel="${step}"]`);
}

function getStepFields(step) {
    const panel = getPanel(step);
    if (!panel) return [];
    return Array.from(panel.querySelectorAll('input, select, textarea')).filter(field => {
        return field.type !== 'hidden' && !field.disabled && field.willValidate;
    });
}

function updateFieldState(field, force = false) {
    if (!field || field.type === 'hidden' || !field.willValidate || field.disabled) return;
    const touched = field.dataset.touched === '1' || force;
    const hasValue = field.type === 'checkbox' ? field.checked : String(field.value || '').trim() !== '';
    if (!touched && !field.required && !hasValue) {
        field.classList.remove('is-valid', 'is-invalid');
        return;
    }
    field.classList.toggle('is-invalid', !field.checkValidity());
    field.classList.toggle('is-valid', field.checkValidity() && (hasValue || field.required));
}

function refreshSummary() {
    let requiredTotal = 0;
    let requiredValid = 0;
    STEP_ORDER.forEach(step => {
        const fields = getStepFields(step).filter(field => field.required);
        const validCount = fields.filter(field => field.checkValidity()).length;
        requiredTotal += fields.length;
        requiredValid += validCount;
        document.querySelectorAll(`[data-step-status="${step}"], [data-summary-status="${step}"]`).forEach(badge => {
            badge.textContent = `${validCount}/${fields.length}`;
            badge.className = `badge ${fields.length > 0 && validCount === fields.length ? 'text-bg-success' : 'text-bg-light'}`;
        });
    });
    const percent = requiredTotal > 0 ? Math.round((requiredValid / requiredTotal) * 100) : 0;
    const bar = document.getElementById('requiredProgressBar');
    const text = document.getElementById('requiredProgressText');
    if (bar) bar.style.width = `${percent}%`;
    if (text) text.textContent = `${percent}%`;
}

function showStep(index) {
    currentStepIndex = Math.max(0, Math.min(index, STEP_ORDER.length - 1));
    STEP_ORDER.forEach((step, idx) => {
        const active = idx === currentStepIndex;
        getPanel(step)?.classList.toggle('active', active);
        document.querySelector(`[data-step-target="${step}"]`)?.classList.toggle('active', active);
        document.querySelector(`[data-summary-step="${step}"]`)?.classList.toggle('active', active);
    });
    document.querySelector('[data-step-prev]')?.toggleAttribute('disabled', currentStepIndex === 0);
    document.querySelector('[data-step-next]')?.classList.toggle('d-none', currentStepIndex === STEP_ORDER.length - 1);
    document.querySelector('[data-step-submit]')?.classList.toggle('d-none', currentStepIndex !== STEP_ORDER.length - 1);
}

function validateStep(step) {
    let firstInvalid = null;
    getStepFields(step).forEach(field => {
        field.dataset.touched = '1';
        updateFieldState(field, true);
        if (!field.checkValidity() && firstInvalid === null) firstInvalid = field;
    });
    if (!firstInvalid) return true;
    firstInvalid.reportValidity();
    firstInvalid.focus({ preventScroll: false });
    return false;
}

// ---- Trabajos Anteriores ----
let trabajoIdx = 0;
function addTrabajo(data = {}) {
    document.getElementById('trabajos-empty').style.display = 'none';
    const c = document.getElementById('trabajos-container');
    const i = trabajoIdx++;
    const div = document.createElement('div');
    div.className = 'ff-row trabajo-row ds-repeat-card';
    div.innerHTML = `
        <div class="ff-span-2">
            <label class="form-label small">Nombre de la Empresa</label>
            <input type="text" name="trabajos_anteriores[${i}][empresa]" class="form-control form-control-sm" required value="${esc(data.empresa)}">
        </div>
        <div class="ff-span-2">
            <label class="form-label small">Cargo Desempeñado</label>
            <input type="text" name="trabajos_anteriores[${i}][cargo]" class="form-control form-control-sm" value="${esc(data.cargo)}">
        </div>
        <div>
            <label class="form-label small">Tiempo de Servicio</label>
            <input type="text" name="trabajos_anteriores[${i}][tiempo]" class="form-control form-control-sm" placeholder="Ej: 2 años">
        </div>
        <div class="ff-span-2">
            <label class="form-label small">Dirección de la Empresa</label>
            <input type="text" name="trabajos_anteriores[${i}][direccion]" class="form-control form-control-sm">
        </div>
        <div>
            <label class="form-label small">Teléfonos de Contacto</label>
            <input type="text" name="trabajos_anteriores[${i}][telefonos]" class="form-control form-control-sm">
        </div>
        <div class="d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.trabajo-row').remove();checkEmpty('trabajos');refreshSummary()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
    div.querySelector(`[name="trabajos_anteriores[${i}][empresa]"]`).value = data.empresa || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][cargo]"]`).value = data.cargo || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][tiempo]"]`).value = data.tiempo || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][direccion]"]`).value = data.direccion || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][telefonos]"]`).value = data.telefonos || '';
    refreshSummary();
}

// ---- Hijos Menores ----
let hijoIdx = 0;
function addHijo(data = {}) {
    document.getElementById('hijos-empty').style.display = 'none';
    const c = document.getElementById('hijos-container');
    const i = hijoIdx++;
    const div = document.createElement('div');
    div.className = 'ff-row hijo-row ds-repeat-card';
    div.innerHTML = `
        <div class="ff-span-2">
            <label class="form-label small">Nombre del Hijo/a</label>
            <input type="text" name="hijos_menores[${i}][nombre]" class="form-control form-control-sm" required>
        </div>
        <div class="ff-narrow">
            <label class="form-label small">Edad</label>
            <input type="number" name="hijos_menores[${i}][edad]" class="form-control form-control-sm" min="0" max="17">
        </div>
        <div>
            <label class="form-label small">Justifica con Cédula</label>
            <select name="hijos_menores[${i}][justifica_con_cedula]" class="form-select form-select-sm">
                <option value="0">No</option>
                <option value="1">Sí</option>
            </select>
        </div>
        <div class="d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.hijo-row').remove();checkEmpty('hijos');refreshSummary()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
    div.querySelector(`[name="hijos_menores[${i}][nombre]"]`).value = data.nombre || '';
    div.querySelector(`[name="hijos_menores[${i}][edad]"]`).value = data.edad || '';
    div.querySelector(`[name="hijos_menores[${i}][justifica_con_cedula]"]`).value = String(data.justifica_con_cedula || '0');
    refreshSummary();
}

// ---- Referencias ----
let refIdx = 0;
function addReferencia(data = {}) {
    document.getElementById('referencias-empty').style.display = 'none';
    const c = document.getElementById('referencias-container');
    const i = refIdx++;
    const div = document.createElement('div');
    div.className = 'ff-row ref-row ds-repeat-card';
    div.innerHTML = `
        <div>
            <label class="form-label small">Parentesco</label>
            <select name="referencias[${i}][parentesco]" class="form-select form-select-sm">
                <option value="PADRE">Padre</option>
                <option value="MADRE">Madre</option>
                <option value="HERMANO">Hermano/a</option>
                <option value="OTRO">Otro</option>
            </select>
        </div>
        <div class="ff-span-2">
            <label class="form-label small">Nombre Completo</label>
            <input type="text" name="referencias[${i}][nombre]" class="form-control form-control-sm" required>
        </div>
        <div class="ff-span-2">
            <label class="form-label small">Dirección de la Referencia</label>
            <input type="text" name="referencias[${i}][direccion]" class="form-control form-control-sm">
        </div>
        <div>
            <label class="form-label small">Teléfono de Contacto</label>
            <input type="text" name="referencias[${i}][telefono]" class="form-control form-control-sm">
        </div>
        <div class="d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.ref-row').remove();checkEmpty('referencias');refreshSummary()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
    div.querySelector(`[name="referencias[${i}][parentesco]"]`).value = data.parentesco || 'OTRO';
    div.querySelector(`[name="referencias[${i}][nombre]"]`).value = data.nombre || '';
    div.querySelector(`[name="referencias[${i}][direccion]"]`).value = data.direccion || '';
    div.querySelector(`[name="referencias[${i}][telefono]"]`).value = data.telefono || '';
    refreshSummary();
}

function checkEmpty(section) {
    const container = document.getElementById(section + '-container');
    const empty = document.getElementById(section + '-empty');
    const selectors = {
        trabajos: '.trabajo-row',
        hijos: '.hijo-row',
        referencias: '.ref-row'
    };
    if (!container || !empty) return;
    empty.style.display = container.querySelector(selectors[section]) ? 'none' : '';
}

// ---- Cursos: habilitar campos al marcar checkbox ----
document.querySelectorAll('.curso-check').forEach(cb => {
    const row = cb.closest('tr');
    row?.classList.toggle('table-primary', cb.checked);
    cb.addEventListener('change', function() {
        const idx = this.dataset.index;
        const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`);
        const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`);
        fecha.disabled = !this.checked;
        obs.disabled = !this.checked;
        this.closest('tr')?.classList.toggle('table-primary', this.checked);
        refreshSummary();
    });
});

const fichaForm = document.getElementById('fichaForm');
let submitConfirmed = false;

/**
 * Valida una cédula ecuatoriana usando el algoritmo módulo 10.
 * Reglas:
 *  - 10 dígitos numéricos
 *  - Primeros 2 dígitos: código de provincia (01-24, o 30 para extranjeros)
 *  - Tercer dígito < 6 (personas naturales)
 *  - Décimo dígito = dígito verificador calculado por algoritmo módulo 10
 *
 * @param {string} cedula
 * @returns {{valid: boolean, reason?: string}}
 */
function validarCedulaEcuador(cedula) {
    if (typeof cedula !== 'string') cedula = String(cedula ?? '');
    cedula = cedula.trim();

    if (!/^\d{10}$/.test(cedula)) {
        return { valid: false, reason: 'La cédula debe tener exactamente 10 dígitos numéricos' };
    }

    const provincia = parseInt(cedula.substring(0, 2), 10);
    if ((provincia < 1 || provincia > 24) && provincia !== 30) {
        return { valid: false, reason: 'Código de provincia inválido (debe estar entre 01-24 o ser 30)' };
    }

    const tercerDigito = parseInt(cedula.charAt(2), 10);
    if (tercerDigito >= 6) {
        return { valid: false, reason: 'El tercer dígito debe ser menor a 6 (personas naturales)' };
    }

    const coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    let suma = 0;
    for (let i = 0; i < 9; i++) {
        let producto = parseInt(cedula.charAt(i), 10) * coeficientes[i];
        if (producto >= 10) producto -= 9;
        suma += producto;
    }
    const digitoVerificador = (10 - (suma % 10)) % 10;
    const digitoCedula = parseInt(cedula.charAt(9), 10);

    if (digitoVerificador !== digitoCedula) {
        return { valid: false, reason: 'Dígito verificador incorrecto (la cédula no es válida)' };
    }

    return { valid: true };
}

if (fichaForm) {
    prefillTrabajos.forEach(row => addTrabajo(row));
    prefillHijos.forEach(row => addHijo(row));
    prefillReferencias.forEach(row => addReferencia(row));

    checkEmpty('trabajos');
    checkEmpty('hijos');
    checkEmpty('referencias');

    // Validación de cédula ecuatoriana (en vivo: solo dígitos + verificación al salir del campo)
    const cedulaInput = fichaForm.querySelector('input[name="cedula"]');
    const cedulaWrap = document.getElementById('cedula-wrap');
    const cedulaIcon = document.getElementById('cedula-icon');
    const cedulaError = document.getElementById('cedula-error');

    const setCedulaEstado = (estado, mensaje = '') => {
        if (!cedulaWrap || !cedulaIcon || !cedulaError) return;
        cedulaWrap.classList.remove('is-valid', 'is-invalid');
        cedulaIcon.classList.remove('bi-check-circle-fill', 'bi-x-circle-fill');
        cedulaError.classList.remove('show');
        cedulaError.textContent = '';
        if (estado === 'valid') {
            cedulaWrap.classList.add('is-valid');
            cedulaIcon.classList.add('bi-check-circle-fill');
        } else if (estado === 'invalid') {
            cedulaWrap.classList.add('is-invalid');
            cedulaIcon.classList.add('bi-x-circle-fill');
            cedulaError.textContent = mensaje || 'Cédula incorrecta';
            cedulaError.classList.add('show');
        }
    };

    if (cedulaInput) {
        cedulaInput.addEventListener('input', () => {
            cedulaInput.value = cedulaInput.value.replace(/\D/g, '').slice(0, 10);
            cedulaInput.setCustomValidity('');
            // Validación en vivo solo cuando completa los 10 dígitos
            if (cedulaInput.value.length === 10) {
                const r = validarCedulaEcuador(cedulaInput.value);
                if (r.valid) {
                    setCedulaEstado('valid');
                } else {
                    setCedulaEstado('invalid', 'Cédula incorrecta');
                    cedulaInput.setCustomValidity(r.reason);
                }
            } else {
                setCedulaEstado('none');
            }
        });
        const verificarCedula = () => {
            const val = cedulaInput.value;
            if (val === '') {
                cedulaInput.setCustomValidity('');
                setCedulaEstado('none');
                return;
            }
            const r = validarCedulaEcuador(val);
            if (r.valid) {
                setCedulaEstado('valid');
                cedulaInput.setCustomValidity('');
            } else {
                setCedulaEstado('invalid', 'Cédula incorrecta');
                cedulaInput.setCustomValidity(r.reason);
            }
        };
        cedulaInput.addEventListener('blur', verificarCedula);
        cedulaInput.addEventListener('change', verificarCedula);
        // Si viene precargada del servidor, validar al cargar
        if (cedulaInput.value.length === 10) verificarCedula();
    }

    fichaForm.addEventListener('input', event => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        updateFieldState(field);
        refreshSummary();
    });

    fichaForm.addEventListener('change', event => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        field.dataset.touched = '1';
        updateFieldState(field, true);
        refreshSummary();
    });

    fichaForm.addEventListener('focusout', event => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        field.dataset.touched = '1';
        updateFieldState(field, true);
    });

    document.querySelectorAll('[data-step-target]').forEach(button => {
        button.addEventListener('click', () => {
            const index = STEP_ORDER.indexOf(button.dataset.stepTarget);
            if (index >= 0) showStep(index);
        });
    });

    document.querySelector('[data-step-prev]')?.addEventListener('click', () => showStep(currentStepIndex - 1));
    document.querySelector('[data-step-next]')?.addEventListener('click', () => {
        if (validateStep(STEP_ORDER[currentStepIndex])) showStep(currentStepIndex + 1);
    });

    fichaForm.addEventListener('submit', function(event) {
        // Validar cédula antes del submit
        const cedInput = this.querySelector('input[name="cedula"]');
        if (cedInput && cedInput.value !== '') {
            const r = validarCedulaEcuador(cedInput.value);
            cedInput.setCustomValidity(r.valid ? '' : r.reason);
        }

        this.querySelectorAll('.curso-hidden').forEach(el => el.remove());

        let ci = 0;
        document.querySelectorAll('.curso-check:checked').forEach(cb => {
            const idx = cb.dataset.index;
            const cursoId = document.querySelector(`.curso-id[data-index="${idx}"]`).value;
            const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`).value;
            const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`).value;

            const addHidden = (name, val) => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = name;
                inp.value = val;
                inp.className = 'curso-hidden';
                this.appendChild(inp);
            };

            addHidden(`cursos[${ci}][curso_id]`, cursoId);
            addHidden(`cursos[${ci}][fecha_obtencion]`, fecha);
            addHidden(`cursos[${ci}][observacion]`, obs);
            ci++;
        });

        if (!this.checkValidity()) {
            event.preventDefault();
            const firstInvalid = this.querySelector(':invalid');
            if (!firstInvalid) return;
            const targetPanel = firstInvalid.closest('[data-step-panel]');
            if (targetPanel) {
                const step = targetPanel.getAttribute('data-step-panel');
                const stepIndex = STEP_ORDER.indexOf(step);
                if (stepIndex >= 0) showStep(stepIndex);
            }
            firstInvalid.dataset.touched = '1';
            updateFieldState(firstInvalid, true);
            firstInvalid.reportValidity();
            refreshSummary();
            return;
        }

        if (!REQUIRE_SUBMIT_CONFIRM || submitConfirmed) {
            return;
        }

        event.preventDefault();
        const submitAfterConfirm = () => {
            submitConfirmed = true;
            if (typeof this.requestSubmit === 'function') {
                this.requestSubmit();
                return;
            }
            this.submit();
        };

        if (typeof Swal === 'undefined') {
            submitAfterConfirm();
            return;
        }

        Swal.fire({
            icon: 'question',
            title: SUBMIT_CONFIRM_CONFIG.title,
            text: SUBMIT_CONFIRM_CONFIG.text,
            showCancelButton: true,
            confirmButtonText: SUBMIT_CONFIRM_CONFIG.confirmButtonText,
            cancelButtonText: SUBMIT_CONFIRM_CONFIG.cancelButtonText,
            reverseButtons: true,
            focusCancel: true,
        }).then(result => {
            if (result.isConfirmed) {
                submitAfterConfirm();
            }
        });
    });

    showStep(0);
    refreshSummary();
}
</script>
<?php $view->endSection() ?>
