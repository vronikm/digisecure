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
use DigiSecure\Shared\Domain\EcuadorGeo;

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
    : 'Nueva Ficha';
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
$prefillByCedulaUrl = isset($prefillByCedulaUrl) && is_string($prefillByCedulaUrl) && $prefillByCedulaUrl !== ''
    ? $prefillByCedulaUrl
    : '/guardias/prefill/cedula';
$formEnableCedulaPrefill = isset($formEnableCedulaPrefill) ? (bool)$formEnableCedulaPrefill : false;
$defaultTipoFicha = isset($input['tipo_ficha']) && is_string($input['tipo_ficha']) && $input['tipo_ficha'] !== ''
    ? $input['tipo_ficha']
    : 'INGRESO';
$currentFichaId = isset($fichaId) && is_string($fichaId) ? $fichaId : (string)($input['id'] ?? '');
$croquisArchivoId = (string)($input['croquis_archivo_id'] ?? '');
$croquisImagenUrl = $currentFichaId !== '' && $croquisArchivoId !== ''
    ? $view->url('/guardias/' . $currentFichaId . '/croquis')
    : '';
?>

<?php $view->section('title') ?><?= $e($formPageTitle) ?> - DigiSecure<?php $view->endSection() ?>

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
    /* Paso activo (sección en la que se está ingresando información): amarillo */
    .ds-step-link.active {
        border-color: #eab308;
        background: #fefce8;
        box-shadow: 0 12px 24px rgba(234, 179, 8, .20);
        transform: translateY(-1px);
    }
    .ds-step-link.active .ds-step-index {
        background: #eab308;
        color: #fff;
    }
    .ds-step-link.active .ds-step-status {
        background-color: #eab308 !important;
        color: #fff !important;
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
    /* Marco para la foto del empleado */
    .ff-foto-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem;
        margin-bottom: 1rem;
    }
    .ff-foto-frame {
        width: 150px;
        height: 180px;
        border: 2px dashed #c4cdd9;
        border-radius: 14px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        transition: border-color .15s ease;
    }
    .ff-foto-frame:hover { border-color: #2f80ed; }
    .ff-foto-frame img { width: 100%; height: 100%; object-fit: cover; }
    .ff-foto-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .35rem;
        color: #94a3b8;
        text-align: center;
        padding: .5rem;
    }
    .ff-foto-placeholder i { font-size: 2.6rem; line-height: 1; }
    .ff-foto-placeholder span { font-size: .72rem; }
    .ff-foto-actions { display: flex; gap: .4rem; flex-wrap: wrap; justify-content: center; }
    /* Encabezado: Tipo de Ficha (izquierda, automático) + Foto (derecha) */
    .ff-ident-header {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .ff-tipo-box {
        flex: 1 1 240px;
        min-width: 220px;
        display: flex;
        flex-direction: column;
        gap: .4rem;
    }
    .ff-foto-col { flex: 0 0 auto; }
    .ff-foto-col .ff-foto-wrap { margin-bottom: 0; }
    .ff-tipo-display {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        padding: .7rem .9rem;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        font-weight: 700;
        font-size: 1.05rem;
        line-height: 1.1;
        width: fit-content;
        cursor: default;
        user-select: none;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }
    .ff-tipo-display i { font-size: 1.15rem; }
    .ff-tipo-display.tipo-ingreso { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
    .ff-tipo-display.tipo-reingreso { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
    .ff-tipo-display.tipo-actualizacion { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
    .ds-step-panel {
        display: none;
    }
    .ds-step-panel.active {
        display: block;
    }

    .ds-croquis-preview {
        display: grid;
        gap: .75rem;
    }

    .ds-croquis-preview-frame {
        position: relative;
        min-height: 230px;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        overflow: hidden;
        display: grid;
        place-items: center;
    }

    .ds-croquis-preview-frame img {
        width: 100%;
        max-height: 360px;
        object-fit: contain;
        background: #fff;
    }

    .ds-croquis-placeholder {
        color: #64748b;
        text-align: center;
        padding: 1.5rem;
        font-size: .9rem;
    }

    .ds-croquis-placeholder i {
        display: block;
        font-size: 2rem;
        margin-bottom: .5rem;
        color: #94a3b8;
    }

    .ds-croquis-modal {
        position: fixed;
        inset: 0;
        z-index: 1080;
    }

    .ds-croquis-modal[hidden] {
        display: none;
    }

    .ds-croquis-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .62);
    }

    .ds-croquis-dialog {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: min(860px, calc(100vw - 2rem));
        max-height: min(82vh, 680px);
        margin: 0;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 22px 70px rgba(15, 23, 42, .35);
        overflow: auto;
        display: flex;
        flex-direction: column;
    }

    .ds-croquis-modal-head,
    .ds-croquis-modal-actions {
        padding: .72rem .85rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .ds-croquis-modal-actions {
        border-bottom: 0;
        border-top: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }

    .ds-croquis-search {
        display: grid;
        grid-template-columns: minmax(200px, 1fr) auto auto;
        gap: .5rem;
        width: 100%;
    }

    .ds-croquis-map-wrap {
        height: min(46vh, 380px);
        min-height: 260px;
        background: #e2e8f0;
    }

    .ds-croquis-tile-map {
        position: relative;
        width: 100%;
        height: 100%;
        background: #e2e8f0;
        overflow: hidden;
        cursor: grab;
        user-select: none;
        touch-action: none;
    }

    .ds-croquis-tile-map.is-dragging {
        cursor: grabbing;
    }

    .ds-croquis-tile {
        position: absolute;
        width: 256px;
        height: 256px;
        max-width: none;
        border: 0;
        display: block;
        user-select: none;
        -webkit-user-drag: none;
    }

    .ds-croquis-marker {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -100%);
        z-index: 4;
        color: #dc2626;
        font-size: 2.35rem;
        line-height: 1;
        text-shadow: 0 2px 6px rgba(15, 23, 42, .45), 0 0 0 #fff;
        pointer-events: none;
    }

    .ds-croquis-map-status {
        position: absolute;
        left: .75rem;
        bottom: .65rem;
        z-index: 5;
        max-width: calc(100% - 1.5rem);
        padding: .35rem .55rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .92);
        color: #334155;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
        font-size: .78rem;
        font-weight: 700;
    }

    .ds-croquis-map-status:empty {
        display: none;
    }

    .ds-croquis-zoom {
        position: absolute;
        top: .7rem;
        right: .7rem;
        z-index: 6;
        display: grid;
        gap: .3rem;
    }

    .ds-croquis-zoom button {
        width: 2.15rem;
        height: 2.15rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: rgba(255, 255, 255, .96);
        color: #0f172a;
        font-weight: 800;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .14);
    }

    @media (max-width: 720px) {
        .ds-croquis-search {
            grid-template-columns: 1fr;
        }

        .ds-croquis-map-wrap {
            min-height: 260px;
        }
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

    /* Mensajes informativos del formulario en ROJO: notas de ayuda y estados
       vacíos (no afecta otras vistas; solo dentro de #fichaForm). */
    #fichaForm .ds-form-help,
    #fichaForm .text-muted {
        color: #dc2626 !important;
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
<?php if (!empty($prefillFromLast)): ?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Se precargo la informacion de la ultima ficha del guardia. Revisa y actualiza solo los cambios necesarios.</div>
<?php endif ?>
<?php if ($formSuccessMessage !== ''): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= $e($formSuccessMessage) ?></div>
<?php endif ?>

<div class="row g-4">
    <div class="col-xl-8">
<form method="post" action="<?= $view->url($formAction) ?>" id="fichaForm" class="ds-form-main" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="card ds-step-nav-card shadow-sm mb-4">
        <div class="card-body">
            <div class="ds-step-list">
                <button type="button" class="ds-step-link active" data-step-target="base">
                    <span class="ds-step-index">1</span>
                    <span class="ds-step-copy">
                        <strong>Datos personales</strong>
                        <span>Información personal</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="base">0/6</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="contacto">
                    <span class="ds-step-index">2</span>
                    <span class="ds-step-copy">
                        <strong>Domicilio</strong>
                        <span>Dirección y contacto emergencia</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="contacto">0/0</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="familiares">
                    <span class="ds-step-index">3</span>
                    <span class="ds-step-copy">
                        <strong>Datos familiares</strong>
                        <span>Familiares, cónyuge e hijos</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="familiares">0/0</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="historial">
                    <span class="ds-step-index">4</span>
                    <span class="ds-step-copy">
                        <strong>Historial laboral</strong>
                        <span>Historial laboral y referencias</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="historial">0/0</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="cursos">
                    <span class="ds-step-index">5</span>
                    <span class="ds-step-copy">
                        <strong>Educación y Cursos</strong>
                        <span>Instrucción y cursos de seguridad</span>
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
<?php
            $tipoFichaMeta = [
                'INGRESO'       => ['cls' => 'tipo-ingreso',       'icon' => 'bi-box-arrow-in-right'],
                'REINGRESO'     => ['cls' => 'tipo-reingreso',     'icon' => 'bi-arrow-repeat'],
                'ACTUALIZACION' => ['cls' => 'tipo-actualizacion', 'icon' => 'bi-pencil-square'],
            ];
            $tipoInicial = TipoFicha::tryFrom($defaultTipoFicha) ?? TipoFicha::INGRESO;
            $tipoMetaInicial = $tipoFichaMeta[$tipoInicial->value];
            ?>
            <div class="ff-ident-header">
                <div class="ff-tipo-box">
                    <label class="form-label mb-0">Tipo de Ficha</label>
                    <div class="ff-tipo-display <?= $tipoMetaInicial['cls'] ?>" id="tipoFichaDisplay">
                        <i class="bi <?= $tipoMetaInicial['icon'] ?>" id="tipoFichaIcon"></i>
                        <span id="tipoFichaText"><?= $e($tipoInicial->label()) ?></span>
                    </div>
                    <input type="hidden" name="tipo_ficha" id="tipoFichaInput" value="<?= $e($tipoInicial->value) ?>">                   
                </div>
                <div class="ff-foto-col">
                    <div class="ds-subheader"><i class="bi bi-image"></i>Foto del empleado</div>
                    <div class="ff-foto-wrap">
                        <div class="ff-foto-frame">
                            <img id="fotoPreview" alt="Foto del empleado" style="display:none;">
                            <div class="ff-foto-placeholder" id="fotoPlaceholder">
                                <i class="bi bi-person-bounding-box"></i>
                                <span>Sin foto</span>
                            </div>
                        </div>
                        <input type="file" id="fotoInput" name="foto" accept="image/png,image/jpeg" hidden>
                        <div class="ff-foto-actions">
                            <label class="ds-btn ds-btn-outline ds-btn-sm" for="fotoInput" style="cursor: pointer;">
                                <i class="bi bi-camera"></i>Subir foto
                            </label>
                            <button type="button" class="ds-btn ds-btn-ghost ds-btn-sm" id="fotoClear" style="display: none;">
                                <i class="bi bi-x-lg"></i>Quitar
                            </button>
                        </div>
                        <span class="ds-form-help" style="text-align: center;">JPG o PNG &middot; máx 2 MB</span>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var input = document.getElementById('fotoInput');
                var preview = document.getElementById('fotoPreview');
                var ph = document.getElementById('fotoPlaceholder');
                var clear = document.getElementById('fotoClear');
                if (!input) { return; }
                var MAX = 2 * 1024 * 1024;
                input.addEventListener('change', function () {
                    var f = input.files && input.files[0];
                    if (!f) { return; }
                    if (!/^image\/(png|jpe?g)$/.test(f.type)) { alert('La foto debe ser JPG o PNG.'); input.value = ''; return; }
                    if (f.size > MAX) { alert('La foto no debe superar 2 MB.'); input.value = ''; return; }
                    var rd = new FileReader();
                    rd.onload = function (e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        ph.style.display = 'none';
                        clear.style.display = 'inline-flex';
                    };
                    rd.readAsDataURL(f);
                });
                clear.addEventListener('click', function () {
                    input.value = '';
                    preview.src = '';
                    preview.style.display = 'none';
                    ph.style.display = 'flex';
                    clear.style.display = 'none';
                });
            })();
            </script>

            <div class="ds-subheader"><i class="bi bi-tag"></i>Identificación</div>
            <div class="ff-row">
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
                <div class="ff-span-2">
                    <label class="form-label">Provincia de Nacimiento</label>
                    <?php $nacProvActual = $old('lugar_nacimiento_provincia'); ?>
                    <select name="lugar_nacimiento_provincia" id="ficha-nac-provincia" class="form-select">
                        <option value="">— Selecciona —</option>
                        <?php foreach (EcuadorGeo::provincias() as $p): ?>
                        <option value="<?= $e($p) ?>" <?= $nacProvActual === $p ? 'selected' : '' ?>><?= $e($p) ?></option>
                        <?php endforeach ?>
                        <?php if ($nacProvActual !== '' && !in_array($nacProvActual, EcuadorGeo::provincias(), true)): ?>
                        <option value="<?= $e($nacProvActual) ?>" selected><?= $e($nacProvActual) ?> (actual)</option>
                        <?php endif ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Ciudad de Nacimiento</label>
                    <select name="lugar_nacimiento_ciudad" id="ficha-nac-ciudad" class="form-select" data-selected="<?= $e($old('lugar_nacimiento_ciudad')) ?>">
                        <option value="">— Selecciona provincia primero —</option>
                    </select>
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

    <!-- Finanzas (movida al final de Datos Personales) -->
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-bank me-2"></i>Finanzas</div>
        <div class="card-body">
            <div class="ds-subheader"><i class="bi bi-cash-coin"></i>Datos financieros</div>
            <div class="ff-row">
                <div class="ff-span-2">
                    <label class="form-label">Banco</label>
                    <?php
                    $institucionesList = $instituciones ?? [];
                    $bancoActual = $old('banco');
                    $institucionNombres = array_map(static fn(array $i): string => (string) $i['nombre'], $institucionesList);
                    $institucionesPorTipo = [];
                    foreach ($institucionesList as $inst) {
                        $institucionesPorTipo[(string) $inst['tipo']][] = (string) $inst['nombre'];
                    }
                    $tipoLabels = ['BANCO' => 'Bancos', 'COOPERATIVA' => 'Cooperativas'];
                    ?>
                    <select name="banco" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($institucionesPorTipo as $tipoKey => $nombres): ?>
                        <optgroup label="<?= $e($tipoLabels[$tipoKey] ?? $tipoKey) ?>">
                            <?php foreach ($nombres as $nombre): ?>
                            <option value="<?= $e($nombre) ?>" <?= $bancoActual === $nombre ? 'selected' : '' ?>><?= $e($nombre) ?></option>
                            <?php endforeach ?>
                        </optgroup>
                        <?php endforeach ?>
                        <?php if ($bancoActual !== '' && !in_array($bancoActual, $institucionNombres, true)): ?>
                        <option value="<?= $e($bancoActual) ?>" selected><?= $e($bancoActual) ?> (actual)</option>
                        <?php endif ?>
                    </select>
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
                <div class="ff-span-3">
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

    <!-- Croquis del domicilio -->
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-geo-alt-fill me-2"></i>Croquis del domicilio</div>
        <div class="card-body">
            <p class="ds-form-help">Abra el mapa, ubique el domicilio y capture la vista. La imagen se guardara en el croquis.</p>
            <input type="hidden" name="croquis_latitud" id="croquis_latitud" value="<?= $e((string) $old('croquis_latitud')) ?>">
            <input type="hidden" name="croquis_longitud" id="croquis_longitud" value="<?= $e((string) $old('croquis_longitud')) ?>">
            <input type="hidden" name="croquis_imagen_data" id="croquis_imagen_data" value="">
            <input type="hidden" name="croquis_eliminar_imagen" id="croquis_eliminar_imagen" value="0">
            <input type="hidden" name="croquis_mapa_estatico" id="croquis_mapa_estatico" value="0">
            <input type="hidden" name="croquis_zoom" id="croquis_zoom" value="16">

            <div class="ds-croquis-preview">
                <div class="ds-croquis-preview-frame">
                    <img id="croquis-preview-img"
                         src="<?= $e($croquisImagenUrl) ?>"
                         alt="Croquis del domicilio"
                         style="<?= $croquisImagenUrl !== '' ? '' : 'display:none;' ?>">
                    <div id="croquis-preview-empty" class="ds-croquis-placeholder" style="<?= $croquisImagenUrl !== '' ? 'display:none;' : '' ?>">
                        <i class="bi bi-map"></i>
                        Aun no hay imagen del croquis. Abra el mapa y capture la ubicacion.
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" id="croquis-open-map"><i class="bi bi-map me-1"></i>Abrir mapa</button>
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" id="croquis-upload-trigger"><i class="bi bi-image me-1"></i>Cargar imagen</button>
                    <button type="button" class="ds-btn ds-btn-ghost ds-btn-sm" id="croquis-delete-image"><i class="bi bi-trash me-1"></i>Borrar imagen</button>
                    <input type="file" id="croquis-file-input" accept="image/png,image/jpeg,image/webp" hidden>
                    <span class="ds-form-help mb-0" id="croquis-coords"></span>
                </div>
            </div>

            <div class="ds-croquis-modal" id="croquis-modal" hidden aria-hidden="true">
                <div class="ds-croquis-backdrop" data-croquis-close></div>
                <div class="ds-croquis-dialog" role="dialog" aria-modal="true" aria-labelledby="croquis-modal-title">
                    <div class="ds-croquis-modal-head">
                        <div>
                            <div class="ds-card-title" id="croquis-modal-title">Ubicar domicilio en el mapa</div>
                            <div class="ds-form-help">Busque la direccion, use la ubicacion del dispositivo o haga clic sobre el mapa para ajustar el punto.</div>
                        </div>
                        <button type="button" class="ds-btn ds-btn-ghost ds-btn-sm" data-croquis-close><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="ds-croquis-modal-head">
                        <div class="ds-croquis-search">
                            <input type="text" class="form-control" id="croquis-search-query" placeholder="Direccion o referencia">
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" id="croquis-load-address"><i class="bi bi-house-door me-1"></i>Usar datos del formulario</button>
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" id="croquis-search"><i class="bi bi-search me-1"></i>Buscar</button>
                        </div>
                    </div>
                    <div class="ds-croquis-map-wrap" id="croquis-map-capture-area">
                        <div id="croquis-map" class="ds-croquis-tile-map" role="application" aria-label="Mapa para croquis">
                            <div id="croquis-map-tiles"></div>
                            <div class="ds-croquis-marker" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></div>
                            <div class="ds-croquis-zoom" aria-label="Zoom del mapa">
                                <button type="button" id="croquis-zoom-in" aria-label="Acercar mapa">+</button>
                                <button type="button" id="croquis-zoom-out" aria-label="Alejar mapa">-</button>
                            </div>
                            <div class="ds-croquis-map-status" id="croquis-map-status">Cargando mapa...</div>
                        </div>
                    </div>
                    <div class="ds-croquis-modal-actions">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" id="croquis-geoloc"><i class="bi bi-crosshair me-1"></i>Usar mi ubicacion</button>
                            <button type="button" class="ds-btn ds-btn-ghost ds-btn-sm" id="croquis-clear"><i class="bi bi-x-lg me-1"></i>Quitar ubicacion</button>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="ds-btn ds-btn-primary ds-btn-sm" id="croquis-capture"><i class="bi bi-camera me-1"></i>Capturar mapa</button>
                            <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" data-croquis-close>Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- FASE: Datos familiares -->
    <div class="ds-step-panel" data-step-panel="familiares">
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-people-fill me-2"></i>Datos familiares</div>
        <div class="card-body">
            <div class="ds-subheader"><i class="bi bi-diagram-3"></i>Datos Familiares</div>

            <div class="ff-row">
                <div>
                    <label class="form-label">Padre</label>
                    <input type="text" name="familiar_padre_nombre" class="form-control" maxlength="200" placeholder="Nombres y apellidos" value="<?= $old('familiar_padre_nombre') ?>">
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Dirección del Padre</label>
                    <input type="text" name="familiar_padre_direccion" class="form-control" maxlength="255" value="<?= $old('familiar_padre_direccion') ?>">
                </div>
                <div>
                    <label class="form-label">Teléfono del Padre</label>
                    <input type="text" name="familiar_padre_telefono" class="form-control" maxlength="30" value="<?= $old('familiar_padre_telefono') ?>">
                </div>
            </div>

            <div class="ff-row mt-2">
                <div>
                    <label class="form-label">Madre</label>
                    <input type="text" name="familiar_madre_nombre" class="form-control" maxlength="200" placeholder="Nombres y apellidos" value="<?= $old('familiar_madre_nombre') ?>">
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Dirección de la Madre</label>
                    <input type="text" name="familiar_madre_direccion" class="form-control" maxlength="255" value="<?= $old('familiar_madre_direccion') ?>">
                </div>
                <div>
                    <label class="form-label">Teléfono de la Madre</label>
                    <input type="text" name="familiar_madre_telefono" class="form-control" maxlength="30" value="<?= $old('familiar_madre_telefono') ?>">
                </div>
            </div>

            <div class="ff-row mt-2">
                <div>
                    <label class="form-label">Hermano/a</label>
                    <input type="text" name="familiar_hermano_nombre" class="form-control" maxlength="200" placeholder="Nombres y apellidos" value="<?= $old('familiar_hermano_nombre') ?>">
                </div>
                <div class="ff-span-2">
                    <label class="form-label">Dirección del Hermano/a</label>
                    <input type="text" name="familiar_hermano_direccion" class="form-control" maxlength="255" value="<?= $old('familiar_hermano_direccion') ?>">
                </div>
                <div>
                    <label class="form-label">Teléfono del Hermano/a</label>
                    <input type="text" name="familiar_hermano_telefono" class="form-control" maxlength="30" value="<?= $old('familiar_hermano_telefono') ?>">
                </div>
            </div>

            <div class="ds-subheader mt-3"><i class="bi bi-heart"></i>Cónyuge</div>
            <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-2" id="conyuge-warning" role="status" style="display:none;">
                <i class="bi bi-info-circle-fill"></i>
                <span>El cónyuge es de ingreso obligatorio cuando el Estado Civil es <strong>Casado/a</strong> o <strong>Unión libre</strong>.</span>
            </div>
            <div class="ff-row">
                <div class="ff-span-2">
                    <label class="form-label">Nombre del Cónyuge <span class="text-danger conyuge-req" style="display:none;">*</span></label>
                    <input type="text" name="conyuge_nombre" id="conyuge_nombre" class="form-control" maxlength="200" value="<?= $old('conyuge_nombre') ?>">
                </div>
                <div>
                    <label class="form-label">Cédula del Cónyuge <span class="text-danger conyuge-req" style="display:none;">*</span></label>
                    <input type="text" name="conyuge_cedula" id="conyuge_cedula" class="form-control" maxlength="10" pattern="\d{10}" inputmode="numeric" autocomplete="off" value="<?= $old('conyuge_cedula') ?>">
                </div>
                <div>
                    <label class="form-label">Edad del Cónyuge <span class="text-danger conyuge-req" style="display:none;">*</span></label>
                    <input type="number" name="conyuge_edad" id="conyuge_edad" class="form-control" min="18" max="120" value="<?= $old('conyuge_edad') ?>">
                </div>
            </div>

            <div class="ds-subheader d-flex justify-content-between align-items-center mt-3">
                <span><i class="bi bi-people"></i>Hijos menores de 18 años</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addHijo()">
                    <i class="bi bi-plus-lg me-1"></i>Agregar
                </button>
            </div>
            <div id="hijos-container">
                <p class="text-muted small mb-0" id="hijos-empty">Sin hijos menores registrados.</p>
            </div>
        </div>
    </div>
    </div>

    <div class="ds-step-panel" data-step-panel="historial">
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
                    <i class="bi bi-info-circle me-1"></i>Opcional: agregue los trabajos anteriores del aspirante si los tiene.
                </p>
            </div>

            <div class="ds-subheader d-flex justify-content-between align-items-center mt-3">
                <span><i class="bi bi-person-lines-fill"></i>Referencias personales <span class="text-danger">*</span></span>
                <button type="button" id="referencias-add" class="btn btn-sm btn-outline-primary" onclick="addReferencia()">
                    <i class="bi bi-plus-lg me-1"></i>Agregar
                </button>
            </div>
            <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-2" id="referencias-warning" role="status">
                <i class="bi bi-info-circle-fill"></i>
                <span>Debe ingresar <strong>dos referencias personales</strong>. No podrá avanzar a la siguiente fase si no ingresa dos referencias personales.</span>
            </div>
            <div id="referencias-container">
                <p class="text-muted small mb-0" id="referencias-empty">Sin referencias registradas.</p>
            </div>
        </div>
    </div>

    </div>

    <div class="ds-step-panel" data-step-panel="cursos">
    <!-- Nivel de instrucción (movido a la fase Cursos, antes de Cursos de Seguridad) -->
    <div class="card shadow-sm mb-3">
        <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Nivel de instrucción</div>
        <div class="card-body">
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
const STEP_ORDER = ['base', 'contacto', 'familiares', 'historial', 'cursos'];
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
const CEDULA_PREFILL_ENABLED = <?= $formEnableCedulaPrefill ? 'true' : 'false' ?>;
const CEDULA_PREFILL_URL = <?= json_encode($view->url($prefillByCedulaUrl), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let currentStepIndex = 0;
let prefillLookupInProgress = false;
let lastCedulaPrefilled = '';

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
    const isLastStep = currentStepIndex === STEP_ORDER.length - 1;
    document.querySelectorAll('[data-step-prev]').forEach(el => el.toggleAttribute('disabled', currentStepIndex === 0));
    // En la última etapa (4/4) NO se muestra "Siguiente": solo queda "Crear Ficha".
    document.querySelectorAll('[data-step-next]').forEach(el => el.classList.toggle('d-none', isLastStep));
    document.querySelectorAll('[data-step-submit]').forEach(el => el.classList.toggle('d-none', !isLastStep));
    // Al cambiar de paso (Siguiente/Anterior/atajo), reubicar al inicio de la pantalla.
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep(step) {
    let firstInvalid = null;
    getStepFields(step).forEach(field => {
        field.dataset.touched = '1';
        updateFieldState(field, true);
        if (!field.checkValidity() && firstInvalid === null) firstInvalid = field;
    });
    if (firstInvalid) {
        firstInvalid.reportValidity();
        firstInvalid.focus({ preventScroll: false });
        return false;
    }
    // El paso 'historial' exige al menos 2 referencias personales.
    if (step === 'historial' && !validateReferencias()) {
        return false;
    }
    return true;
}

// ---- Cónyuge obligatorio según Estado Civil (Casado/a o Unión libre) ----
function syncConyugeRequerido() {
    const estado = document.querySelector('[name="estado_civil"]');
    const obligatorio = !!estado && (estado.value === 'CASADO' || estado.value === 'UNION_LIBRE');
    ['conyuge_nombre', 'conyuge_cedula', 'conyuge_edad'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.required = obligatorio;
        if (!obligatorio) el.setCustomValidity('');
    });
    document.querySelectorAll('.conyuge-req').forEach(s => { s.style.display = obligatorio ? '' : 'none'; });
    const warn = document.getElementById('conyuge-warning');
    if (warn) warn.style.display = obligatorio ? '' : 'none';
}

// ---- Regla: mínimo 2 referencias personales ----
function referenciasCount() {
    return document.querySelectorAll('#referencias-container .ref-row').length;
}

function updateReferenciasState() {
    const warn = document.getElementById('referencias-warning');
    if (!warn) return;
    const ok = referenciasCount() >= 2;
    // Mensaje en ROJO mientras falten referencias; verde cuando ya hay dos.
    warn.classList.remove('alert-info', 'alert-danger', 'alert-success');
    warn.classList.add(ok ? 'alert-success' : 'alert-danger');
}

function validateReferencias() {
    const ok = referenciasCount() >= 2;
    updateReferenciasState(!ok);
    if (!ok) {
        const warn = document.getElementById('referencias-warning');
        if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return ok;
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
            <input type="text" name="trabajos_anteriores[${i}][empresa]" class="form-control form-control-sm" value="${esc(data.empresa)}">
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
    updateReferenciasState(false);
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
    if (section === 'referencias') updateReferenciasState(false);
}

function clearRepeater(section) {
    const container = document.getElementById(section + '-container');
    if (!container) return;
    const selectors = {
        trabajos: '.trabajo-row',
        hijos: '.hijo-row',
        referencias: '.ref-row'
    };
    container.querySelectorAll(selectors[section] || '').forEach(node => node.remove());
    checkEmpty(section);
}

function applyCursosPrefill(cursos = []) {
    document.querySelectorAll('.curso-check').forEach(cb => {
        const idx = cb.dataset.index;
        const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`);
        const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`);
        cb.checked = false;
        if (fecha) {
            fecha.value = '';
            fecha.disabled = true;
        }
        if (obs) {
            obs.value = '';
            obs.disabled = true;
        }
        cb.closest('tr')?.classList.remove('table-primary');
    });

    const mapByCursoId = new Map();
    document.querySelectorAll('.curso-id').forEach(hidden => {
        const idx = hidden.dataset.index;
        if (!idx) return;
        mapByCursoId.set(String(hidden.value || ''), idx);
    });

    (Array.isArray(cursos) ? cursos : []).forEach(curso => {
        const cursoId = String(curso?.curso_id || '');
        if (!cursoId || !mapByCursoId.has(cursoId)) return;
        const idx = mapByCursoId.get(cursoId);
        const cb = document.querySelector(`.curso-check[data-index="${idx}"]`);
        const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`);
        const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`);
        if (cb instanceof HTMLInputElement) {
            cb.checked = true;
            cb.closest('tr')?.classList.add('table-primary');
        }
        if (fecha instanceof HTMLInputElement) {
            fecha.disabled = false;
            fecha.value = String(curso?.fecha_obtencion || '');
        }
        if (obs instanceof HTMLInputElement) {
            obs.disabled = false;
            obs.value = String(curso?.observacion || '');
        }
    });
}

function applyFichaPrefill(prefill = {}) {
    if (!(prefill && typeof prefill === 'object')) return;

    const scalarKeys = [
        'nombres',
        'apellidos',
        'genero',
        'fecha_nacimiento',
        'lugar_nacimiento_provincia',
        'lugar_nacimiento_ciudad',
        'tipo_sangre',
        'estatura',
        'estado_civil',
        'celular',
        'email',
        'domicilio_ciudad',
        'domicilio_tipo_vivienda',
        'domicilio_telefono',
        'domicilio_calle',
        'domicilio_numero',
        'domicilio_barrio',
        'domicilio_referencia',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'banco',
        'tipo_cuenta',
        'cuenta_bancaria',
        'instruccion_primaria',
        'instruccion_secundaria',
        'instruccion_superior',
        'fecha_ultimo_reentrenamiento'
    ];

    scalarKeys.forEach(key => {
        const field = document.querySelector(`[name="${key}"]`);
        if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement) && !(field instanceof HTMLTextAreaElement)) {
            return;
        }
        const value = prefill[key];
        field.value = value == null ? '' : String(value);
        field.dataset.touched = '1';
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });

    clearRepeater('trabajos');
    (Array.isArray(prefill.trabajos_anteriores) ? prefill.trabajos_anteriores : []).forEach(row => addTrabajo(row || {}));

    clearRepeater('hijos');
    (Array.isArray(prefill.hijos_menores) ? prefill.hijos_menores : []).forEach(row => addHijo(row || {}));

    clearRepeater('referencias');
    (Array.isArray(prefill.referencias) ? prefill.referencias : []).forEach(row => addReferencia(row || {}));

    checkEmpty('trabajos');
    checkEmpty('hijos');
    checkEmpty('referencias');
    applyCursosPrefill(prefill.cursos || []);
    refreshSummary();
}

function hasManualDataBeyondCedula() {
    const fields = [
        'nombres', 'apellidos', 'celular', 'email', 'domicilio_calle',
        'domicilio_numero', 'banco', 'cuenta_bancaria'
    ];
    for (const key of fields) {
        const el = document.querySelector(`[name="${key}"]`);
        if (!(el instanceof HTMLInputElement) && !(el instanceof HTMLSelectElement) && !(el instanceof HTMLTextAreaElement)) {
            continue;
        }
        const value = String(el.value || '').trim();
        if (value !== '') return true;
    }

    if (document.querySelector('.trabajo-row, .hijo-row, .ref-row')) return true;
    if (document.querySelector('.curso-check:checked')) return true;
    return false;
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

    const confirmarSobrescrituraPrefill = async () => {
        if (!hasManualDataBeyondCedula()) {
            return true;
        }

        const msg = 'Ya ingresaste datos en el formulario. Se reemplazaran con la ultima ficha encontrada. Deseas continuar?';
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                icon: 'question',
                title: 'Usar datos de la ultima ficha?',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Si, reemplazar',
                cancelButtonText: 'No, mantener',
                reverseButtons: true,
                focusCancel: true
            });
            return Boolean(result.isConfirmed);
        }

        return window.confirm(msg);
    };

    const TIPO_FICHA_META = {
        INGRESO:       { label: 'Ingreso',       cls: 'tipo-ingreso',       icon: 'bi-box-arrow-in-right' },
        REINGRESO:     { label: 'Reingreso',     cls: 'tipo-reingreso',     icon: 'bi-arrow-repeat' },
        ACTUALIZACION: { label: 'Actualización', cls: 'tipo-actualizacion', icon: 'bi-pencil-square' }
    };
    const tipoFichaInput = document.getElementById('tipoFichaInput');
    const tipoFichaDisplay = document.getElementById('tipoFichaDisplay');
    const tipoFichaText = document.getElementById('tipoFichaText');
    const tipoFichaIcon = document.getElementById('tipoFichaIcon');
    const setTipoFicha = (value) => {
        const meta = TIPO_FICHA_META[value] || TIPO_FICHA_META.INGRESO;
        const key = TIPO_FICHA_META[value] ? value : 'INGRESO';
        if (tipoFichaInput) { tipoFichaInput.value = key; }
        if (tipoFichaText) { tipoFichaText.textContent = meta.label; }
        if (tipoFichaIcon) { tipoFichaIcon.className = 'bi ' + meta.icon; }
        if (tipoFichaDisplay) {
            tipoFichaDisplay.className = 'ff-tipo-display ' + meta.cls;
        }
    };

    const prefillDesdeCedula = async (cedulaDigits) => {
        if (!CEDULA_PREFILL_ENABLED || !CEDULA_PREFILL_URL) {
            return;
        }
        if (!/^\d{10}$/.test(cedulaDigits)) {
            return;
        }
        if (prefillLookupInProgress || lastCedulaPrefilled === cedulaDigits) {
            return;
        }

        prefillLookupInProgress = true;
        try {
            const endpoint = new URL(CEDULA_PREFILL_URL, window.location.origin);
            endpoint.searchParams.set('cedula', cedulaDigits);
            const response = await fetch(endpoint.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            // El Tipo de Ficha se determina automáticamente con el resultado del
            // servidor (Ingreso / Reingreso / Actualización), independientemente de
            // si el usuario acepta o no sobrescribir los datos del formulario.
            setTipoFicha(payload && payload.tipo_ficha ? payload.tipo_ficha : 'INGRESO');

            if (!payload || payload.found !== true || typeof payload.input !== 'object') {
                if (payload && payload.found === true) {
                    lastCedulaPrefilled = cedulaDigits;
                }
                return;
            }

            const shouldApply = await confirmarSobrescrituraPrefill();
            if (!shouldApply) {
                lastCedulaPrefilled = cedulaDigits;
                return;
            }

            applyFichaPrefill(payload.input);
            lastCedulaPrefilled = cedulaDigits;
        } catch (error) {
            // Silencioso: no bloquea al usuario si falla prefill.
        } finally {
            prefillLookupInProgress = false;
        }
    };

    if (cedulaInput) {
        cedulaInput.addEventListener('input', () => {
            cedulaInput.value = cedulaInput.value.replace(/\D/g, '').slice(0, 10);
            cedulaInput.setCustomValidity('');
            if (cedulaInput.value.length !== 10) {
                lastCedulaPrefilled = '';
                // Sin cédula completa no hay empleado que consultar: vuelve a Ingreso.
                setTipoFicha('INGRESO');
            }
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
        const verificarCedula = (allowPrefillFetch = true) => {
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
                if (allowPrefillFetch) {
                    void prefillDesdeCedula(val);
                }
            } else {
                setCedulaEstado('invalid', 'Cédula incorrecta');
                cedulaInput.setCustomValidity(r.reason);
            }
        };
        cedulaInput.addEventListener('blur', () => verificarCedula(true));
        cedulaInput.addEventListener('change', () => verificarCedula(true));
        // Si viene precargada del servidor, validar al cargar
        if (cedulaInput.value.length === 10) verificarCedula(false);
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
            if (index < 0) return;
            // No permitir saltar más allá de 'historial' sin 2 referencias personales.
            const histIdx = STEP_ORDER.indexOf('historial');
            if (index > histIdx && referenciasCount() < 2) {
                showStep(histIdx);
                validateReferencias();
                return;
            }
            showStep(index);
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

        // Regla de negocio: no se puede enviar sin dos referencias personales.
        if (referenciasCount() < 2) {
            event.preventDefault();
            const histIdx = STEP_ORDER.indexOf('historial');
            if (histIdx >= 0) showStep(histIdx);
            validateReferencias();
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

    document.querySelector('[name="estado_civil"]')?.addEventListener('change', syncConyugeRequerido);

    showStep(0);
    refreshSummary();
    updateReferenciasState(false);
    syncConyugeRequerido();
}
</script>
<script>
(function () {
    const modal = document.getElementById('croquis-modal');
    const openBtn = document.getElementById('croquis-open-map');
    const mapEl = document.getElementById('croquis-map');
    const tilePane = document.getElementById('croquis-map-tiles');
    const mapStatus = document.getElementById('croquis-map-status');
    const searchInput = document.getElementById('croquis-search-query');
    const searchBtn = document.getElementById('croquis-search');
    const loadAddressBtn = document.getElementById('croquis-load-address');
    const captureBtn = document.getElementById('croquis-capture');
    const geoBtn = document.getElementById('croquis-geoloc');
    const clearBtn = document.getElementById('croquis-clear');
    const zoomInBtn = document.getElementById('croquis-zoom-in');
    const zoomOutBtn = document.getElementById('croquis-zoom-out');
    const deleteBtn = document.getElementById('croquis-delete-image');
    const uploadTrigger = document.getElementById('croquis-upload-trigger');
    const fileInput = document.getElementById('croquis-file-input');
    const previewImg = document.getElementById('croquis-preview-img');
    const previewEmpty = document.getElementById('croquis-preview-empty');
    const imageDataInput = document.getElementById('croquis_imagen_data');
    const deleteInput = document.getElementById('croquis_eliminar_imagen');
    const staticMapInput = document.getElementById('croquis_mapa_estatico');
    const latInput = document.getElementById('croquis_latitud');
    const lngInput = document.getElementById('croquis_longitud');
    const zoomInput = document.getElementById('croquis_zoom');
    const coordsEl = document.getElementById('croquis-coords');

    if (!modal || !openBtn || !mapEl || !tilePane) {
        return;
    }

    const originalParent = modal.parentNode;
    const originalNextSibling = modal.nextSibling;
    const DEFAULT_LAT = -3.99313;
    const DEFAULT_LNG = -79.20422;
    const TILE_SIZE = 256;
    const MIN_ZOOM = 5;
    const MAX_ZOOM = 18;
    const SNAPSHOT_WIDTH = 860;
    const SNAPSHOT_HEIGHT = 380;
    const OSM_ATTRIBUTION = 'OpenStreetMap contributors';
    let zoom = Number.parseInt(zoomInput?.value || '16', 10);
    let center = {
        lat: Number.parseFloat(latInput?.value || '') || DEFAULT_LAT,
        lng: Number.parseFloat(lngInput?.value || '') || DEFAULT_LNG,
    };
    let dragState = null;
    let suppressNextClick = false;

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const clampLat = (lat) => clamp(lat, -85.05112878, 85.05112878);
    const normalizeLng = (lng) => {
        const normalized = ((lng + 180) % 360 + 360) % 360 - 180;
        return normalized === -180 ? 180 : normalized;
    };
    const clampZoom = (value) => clamp(Number.parseInt(value, 10) || 16, MIN_ZOOM, MAX_ZOOM);

    const currentCoordsQuery = () => {
        const lat = Number.parseFloat(latInput?.value || '');
        const lng = Number.parseFloat(lngInput?.value || '');
        return Number.isFinite(lat) && Number.isFinite(lng) ? `${lat.toFixed(7)},${lng.toFixed(7)}` : '';
    };

    const formAddress = () => {
        const names = ['domicilio_calle', 'domicilio_numero', 'domicilio_barrio', 'domicilio_ciudad', 'domicilio_referencia'];
        const parts = names
            .map(name => document.querySelector(`[name="${name}"]`)?.value?.trim() || '')
            .filter(Boolean);
        parts.push('Ecuador');
        return parts.join(', ');
    };

    const updateCoordsLabel = () => {
        if (!coordsEl) return;
        const q = currentCoordsQuery();
        coordsEl.textContent = q ? `Lat/Lng: ${q}` : '';
    };

    const setStatus = (message) => {
        if (mapStatus) {
            mapStatus.textContent = message || '';
        }
    };

    const latLngToPixel = (lat, lng, z) => {
        const sinLat = clamp(Math.sin(clampLat(lat) * Math.PI / 180), -0.9999, 0.9999);
        const scale = TILE_SIZE * Math.pow(2, z);
        return {
            x: (normalizeLng(lng) + 180) / 360 * scale,
            y: (0.5 - Math.log((1 + sinLat) / (1 - sinLat)) / (4 * Math.PI)) * scale,
        };
    };

    const pixelToLatLng = (x, y, z) => {
        const scale = TILE_SIZE * Math.pow(2, z);
        const lng = x / scale * 360 - 180;
        const n = Math.PI - 2 * Math.PI * y / scale;
        const lat = 180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
        return { lat: clampLat(lat), lng: normalizeLng(lng) };
    };

    const tileUrl = (x, y, z) => {
        const tileCount = Math.pow(2, z);
        const wrappedX = ((x % tileCount) + tileCount) % tileCount;
        return `https://tile.openstreetmap.org/${z}/${wrappedX}/${y}.png`;
    };

    const renderMap = () => {
        const rect = mapEl.getBoundingClientRect();
        const width = Math.max(Math.round(rect.width || SNAPSHOT_WIDTH), 320);
        const height = Math.max(Math.round(rect.height || SNAPSHOT_HEIGHT), 240);
        const centerPixel = latLngToPixel(center.lat, center.lng, zoom);
        const startX = centerPixel.x - width / 2;
        const startY = centerPixel.y - height / 2;
        const minTileX = Math.floor(startX / TILE_SIZE);
        const maxTileX = Math.floor((startX + width) / TILE_SIZE);
        const minTileY = Math.floor(startY / TILE_SIZE);
        const maxTileY = Math.floor((startY + height) / TILE_SIZE);
        const tileCount = Math.pow(2, zoom);
        let total = 0;
        let loaded = 0;
        let failed = 0;

        tilePane.innerHTML = '';
        tilePane.style.position = 'absolute';
        tilePane.style.inset = '0';
        tilePane.style.transform = '';
        setStatus('Cargando mapa...');

        for (let y = minTileY; y <= maxTileY; y += 1) {
            if (y < 0 || y >= tileCount) {
                continue;
            }

            for (let x = minTileX; x <= maxTileX; x += 1) {
                total += 1;
                const img = document.createElement('img');
                img.className = 'ds-croquis-tile';
                img.alt = '';
                img.decoding = 'async';
                img.loading = 'eager';
                img.crossOrigin = 'anonymous';
                img.referrerPolicy = 'no-referrer';
                img.draggable = false;
                img.style.left = `${Math.round(x * TILE_SIZE - startX)}px`;
                img.style.top = `${Math.round(y * TILE_SIZE - startY)}px`;
                img.src = tileUrl(x, y, zoom);
                img.addEventListener('load', () => {
                    loaded += 1;
                    if (loaded === 1) {
                        setStatus('');
                    }
                }, { once: true });
                img.addEventListener('error', () => {
                    failed += 1;
                    if (failed >= total && loaded === 0) {
                        setStatus('No se pudieron cargar los mosaicos. Revise la conexion.');
                    }
                }, { once: true });
                tilePane.appendChild(img);
            }
        }

        if (total === 0) {
            setStatus('No se pudo preparar el mapa para esas coordenadas.');
        }
    };

    const setMapPosition = (lat, lng, label = '') => {
        const la = Number(lat);
        const ln = Number(lng);
        if (!Number.isFinite(la) || !Number.isFinite(ln)) {
            return false;
        }
        center = { lat: clampLat(la), lng: normalizeLng(ln) };
        zoom = clampZoom(zoom);
        if (latInput) latInput.value = center.lat.toFixed(7);
        if (lngInput) lngInput.value = center.lng.toFixed(7);
        if (zoomInput) zoomInput.value = String(zoom);
        updateCoordsLabel();
        if (searchInput && label !== '') {
            searchInput.value = label;
        }
        renderMap();
        return true;
    };

    const parseCoords = (query) => {
        const match = String(query || '').trim().match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/);
        return match ? { lat: Number.parseFloat(match[1]), lng: Number.parseFloat(match[2]) } : null;
    };

    const geocodeQuery = async (query) => {
        const q = (query || '').trim();
        if (q === '') {
            return false;
        }
        const coords = parseCoords(q);
        if (coords) {
            return setMapPosition(coords.lat, coords.lng, q);
        }

        const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&accept-language=es&q=${encodeURIComponent(q)}`;
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) {
            throw new Error('geocode_failed');
        }
        const data = await response.json();
        if (!Array.isArray(data) || !data[0]) {
            return false;
        }
        return setMapPosition(Number.parseFloat(data[0].lat), Number.parseFloat(data[0].lon), q);
    };

    const notifyLocationIssue = (message) => {
        if (window.Swal) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Ubicacion no disponible',
                text: message,
                confirmButtonText: 'Entendido',
            });
            return;
        }
        alert(message);
    };

    const setMapQuery = async (query) => {
        const q = (query || '').trim() || currentCoordsQuery() || formAddress() || 'Loja Ecuador';
        if (searchInput) {
            searchInput.value = q;
        }
        renderMap();
        try {
            const found = await geocodeQuery(q);
            if (!found) {
                notifyLocationIssue('No se encontro la direccion. Ajuste el texto o haga clic sobre el mapa.');
            }
        } catch (error) {
            notifyLocationIssue('No se pudo consultar la direccion. El mapa sigue disponible para marcar manualmente.');
        }
    };

    const openModal = () => {
        if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        window.requestAnimationFrame(() => {
            renderMap();
            setMapQuery(searchInput?.value || currentCoordsQuery() || formAddress());
        });
    };

    const closeModal = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (originalParent && modal.parentNode === document.body) {
            originalParent.insertBefore(modal, originalNextSibling);
        }
    };

    const setPreview = (dataUrl) => {
        if (!previewImg || !previewEmpty || !imageDataInput || !deleteInput) return;
        previewImg.src = dataUrl;
        previewImg.style.display = '';
        previewEmpty.style.display = 'none';
        imageDataInput.value = dataUrl;
        deleteInput.value = '0';
        if (staticMapInput) {
            staticMapInput.value = '0';
        }
    };

    const setStaticMapPreview = (dataUrl) => {
        if (!previewImg || !previewEmpty || !staticMapInput || !deleteInput || !imageDataInput) return;
        previewImg.src = dataUrl;
        previewImg.style.display = '';
        previewEmpty.style.display = 'none';
        imageDataInput.value = '';
        staticMapInput.value = '1';
        deleteInput.value = '0';
    };

    const clearPreview = () => {
        if (previewImg) {
            previewImg.removeAttribute('src');
            previewImg.style.display = 'none';
        }
        if (previewEmpty) {
            previewEmpty.style.display = '';
        }
        if (imageDataInput) {
            imageDataInput.value = '';
        }
        if (deleteInput) {
            deleteInput.value = '1';
        }
        if (staticMapInput) {
            staticMapInput.value = '0';
        }
    };

    const loadTileForCanvas = (url) => new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.referrerPolicy = 'no-referrer';
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = url;
    });

    const drawCanvasMarker = (ctx, x, y) => {
        ctx.save();
        ctx.fillStyle = '#dc2626';
        ctx.strokeStyle = 'rgba(255,255,255,.95)';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.arc(x, y - 18, 15, 0, Math.PI * 2);
        ctx.moveTo(x, y + 12);
        ctx.lineTo(x - 11, y - 6);
        ctx.lineTo(x + 11, y - 6);
        ctx.closePath();
        ctx.stroke();
        ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x, y - 18, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    };

    const drawAttribution = (ctx, width, height) => {
        ctx.save();
        ctx.font = '12px Arial, sans-serif';
        const text = OSM_ATTRIBUTION;
        const textWidth = ctx.measureText(text).width;
        const x = width - textWidth - 12;
        const y = height - 18;
        ctx.fillStyle = 'rgba(255,255,255,.88)';
        ctx.fillRect(x - 6, y - 13, textWidth + 10, 18);
        ctx.fillStyle = '#334155';
        ctx.fillText(text, x, y);
        ctx.restore();
    };

    const renderSnapshotDataUrl = async () => {
        const canvas = document.createElement('canvas');
        canvas.width = SNAPSHOT_WIDTH;
        canvas.height = SNAPSHOT_HEIGHT;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('canvas_unavailable');
        }

        ctx.fillStyle = '#e2e8f0';
        ctx.fillRect(0, 0, SNAPSHOT_WIDTH, SNAPSHOT_HEIGHT);

        const centerPixel = latLngToPixel(center.lat, center.lng, zoom);
        const startX = centerPixel.x - SNAPSHOT_WIDTH / 2;
        const startY = centerPixel.y - SNAPSHOT_HEIGHT / 2;
        const minTileX = Math.floor(startX / TILE_SIZE);
        const maxTileX = Math.floor((startX + SNAPSHOT_WIDTH) / TILE_SIZE);
        const minTileY = Math.floor(startY / TILE_SIZE);
        const maxTileY = Math.floor((startY + SNAPSHOT_HEIGHT) / TILE_SIZE);
        const tileCount = Math.pow(2, zoom);
        const tiles = [];

        for (let y = minTileY; y <= maxTileY; y += 1) {
            if (y < 0 || y >= tileCount) {
                continue;
            }
            for (let x = minTileX; x <= maxTileX; x += 1) {
                tiles.push({
                    url: tileUrl(x, y, zoom),
                    left: Math.round(x * TILE_SIZE - startX),
                    top: Math.round(y * TILE_SIZE - startY),
                });
            }
        }

        const loadedTiles = await Promise.allSettled(tiles.map(tile => (
            loadTileForCanvas(tile.url).then(img => ({ ...tile, img }))
        )));
        let drawn = 0;
        loadedTiles.forEach(result => {
            if (result.status !== 'fulfilled') return;
            drawn += 1;
            ctx.drawImage(result.value.img, result.value.left, result.value.top, TILE_SIZE, TILE_SIZE);
        });
        if (drawn === 0) {
            throw new Error('tiles_unavailable');
        }

        drawCanvasMarker(ctx, SNAPSHOT_WIDTH / 2, SNAPSHOT_HEIGHT / 2);
        drawAttribution(ctx, SNAPSHOT_WIDTH, SNAPSHOT_HEIGHT);
        return canvas.toDataURL('image/png');
    };

    const fallbackPreviewDataUrl = () => {
        const canvas = document.createElement('canvas');
        canvas.width = SNAPSHOT_WIDTH;
        canvas.height = SNAPSHOT_HEIGHT;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return '';
        }
        ctx.fillStyle = '#e2e8f0';
        ctx.fillRect(0, 0, SNAPSHOT_WIDTH, SNAPSHOT_HEIGHT);
        ctx.strokeStyle = '#cbd5e1';
        for (let x = 0; x < SNAPSHOT_WIDTH; x += 44) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, SNAPSHOT_HEIGHT);
            ctx.stroke();
        }
        for (let y = 0; y < SNAPSHOT_HEIGHT; y += 44) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(SNAPSHOT_WIDTH, y);
            ctx.stroke();
        }
        drawCanvasMarker(ctx, SNAPSHOT_WIDTH / 2, SNAPSHOT_HEIGHT / 2);
        ctx.fillStyle = '#0f172a';
        ctx.font = '700 22px Arial, sans-serif';
        ctx.fillText('Mapa seleccionado para guardar', 28, 46);
        ctx.font = '16px Arial, sans-serif';
        ctx.fillText(`Lat/Lng: ${currentCoordsQuery()}`, 28, 78);
        return canvas.toDataURL('image/png');
    };

    const captureVisibleMap = async () => {
        if (!currentCoordsQuery()) {
            notifyLocationIssue('Primero busque una direccion, use la ubicacion o haga clic sobre el mapa.');
            return;
        }
        if (!previewImg || !previewEmpty || !staticMapInput || !deleteInput || !imageDataInput) {
            return;
        }

        const originalHtml = captureBtn?.innerHTML || '';
        if (captureBtn) {
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Capturando...';
        }

        try {
            const dataUrl = await renderSnapshotDataUrl();
            setPreview(dataUrl);
        } catch (error) {
            const fallback = fallbackPreviewDataUrl();
            if (fallback !== '') {
                setStaticMapPreview(fallback);
            } else {
                staticMapInput.value = '1';
                imageDataInput.value = '';
                deleteInput.value = '0';
            }
        } finally {
            if (captureBtn) {
                captureBtn.disabled = false;
                captureBtn.innerHTML = originalHtml;
            }
            closeModal();
        }
    };

    const readImageFile = (file) => {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            alert('Formato no permitido. Use JPG, PNG o WEBP.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('La imagen excede 5 MB.');
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result === 'string') {
                setPreview(reader.result);
            }
        };
        reader.readAsDataURL(file);
    };

    const geolocationMessage = (error) => {
        if (!error || typeof error.code === 'undefined') {
            return 'No se pudo obtener la ubicacion. Puede buscar la direccion manualmente.';
        }
        if (error.code === 1) {
            return 'El navegador bloqueo el permiso de ubicacion. Activalo desde el candado de la barra de direcciones y vuelve a intentar.';
        }
        if (error.code === 2) {
            return 'El dispositivo no pudo calcular la ubicacion actual. Se cargo la direccion del formulario si estaba disponible.';
        }
        if (error.code === 3) {
            return 'La consulta de ubicacion tardo demasiado. Se cargo la direccion del formulario si estaba disponible.';
        }
        return 'No se pudo obtener la ubicacion. Puede buscar la direccion manualmente.';
    };

    openBtn.addEventListener('click', openModal);
    modal.querySelectorAll('[data-croquis-close]').forEach(el => el.addEventListener('click', closeModal));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
    searchBtn?.addEventListener('click', () => setMapQuery(searchInput?.value || ''));
    searchInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            setMapQuery(searchInput.value);
        }
    });
    loadAddressBtn?.addEventListener('click', () => setMapQuery(formAddress()));
    captureBtn?.addEventListener('click', captureVisibleMap);
    zoomInBtn?.addEventListener('click', event => {
        event.stopPropagation();
        zoom = clampZoom(zoom + 1);
        if (zoomInput) zoomInput.value = String(zoom);
        renderMap();
    });
    zoomOutBtn?.addEventListener('click', event => {
        event.stopPropagation();
        zoom = clampZoom(zoom - 1);
        if (zoomInput) zoomInput.value = String(zoom);
        renderMap();
    });
    mapEl.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        dragState = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            centerPixel: latLngToPixel(center.lat, center.lng, zoom),
            moved: false,
        };
        mapEl.classList.add('is-dragging');
        mapEl.setPointerCapture?.(event.pointerId);
    });
    mapEl.addEventListener('pointermove', event => {
        if (!dragState || dragState.pointerId !== event.pointerId) return;
        const dx = event.clientX - dragState.startX;
        const dy = event.clientY - dragState.startY;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
            dragState.moved = true;
            tilePane.style.transform = `translate(${dx}px, ${dy}px)`;
        }
    });
    mapEl.addEventListener('pointerup', event => {
        if (!dragState || dragState.pointerId !== event.pointerId) return;
        const dx = event.clientX - dragState.startX;
        const dy = event.clientY - dragState.startY;
        const moved = dragState.moved;
        const nextCenter = pixelToLatLng(
            dragState.centerPixel.x - dx,
            dragState.centerPixel.y - dy,
            zoom
        );
        dragState = null;
        mapEl.classList.remove('is-dragging');
        mapEl.releasePointerCapture?.(event.pointerId);
        tilePane.style.transform = '';
        if (moved) {
            suppressNextClick = true;
            setMapPosition(nextCenter.lat, nextCenter.lng, 'Ubicacion seleccionada en el mapa');
        }
    });
    mapEl.addEventListener('pointercancel', event => {
        if (dragState?.pointerId === event.pointerId) {
            dragState = null;
            mapEl.classList.remove('is-dragging');
            tilePane.style.transform = '';
        }
    });
    mapEl.addEventListener('click', event => {
        if (suppressNextClick) {
            suppressNextClick = false;
            return;
        }
        const rect = mapEl.getBoundingClientRect();
        const centerPixel = latLngToPixel(center.lat, center.lng, zoom);
        const point = pixelToLatLng(
            centerPixel.x + (event.clientX - rect.left - rect.width / 2),
            centerPixel.y + (event.clientY - rect.top - rect.height / 2),
            zoom
        );
        setMapPosition(point.lat, point.lng, 'Ubicacion seleccionada en el mapa');
    });
    geoBtn?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            notifyLocationIssue('Geolocalizacion no disponible en este navegador. Busque la direccion manualmente.');
            return;
        }
        if (!window.isSecureContext) {
            notifyLocationIssue('La ubicacion del navegador requiere HTTPS o localhost. Busque la direccion manualmente.');
            return;
        }
        const originalHtml = geoBtn.innerHTML;
        geoBtn.disabled = true;
        geoBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Obteniendo...';
        navigator.geolocation.getCurrentPosition(position => {
            setMapPosition(position.coords.latitude, position.coords.longitude, 'Ubicacion actual');
            geoBtn.disabled = false;
            geoBtn.innerHTML = originalHtml;
        }, error => {
            const fallback = formAddress();
            if (fallback !== 'Ecuador') {
                setMapQuery(fallback);
            }
            notifyLocationIssue(geolocationMessage(error));
            geoBtn.disabled = false;
            geoBtn.innerHTML = originalHtml;
        }, {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 60000,
        });
    });
    clearBtn?.addEventListener('click', () => {
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        updateCoordsLabel();
        setMapQuery(formAddress());
    });
    deleteBtn?.addEventListener('click', clearPreview);
    uploadTrigger?.addEventListener('click', () => fileInput?.click());
    fileInput?.addEventListener('change', () => readImageFile(fileInput.files?.[0] || null));

    zoom = clampZoom(zoom);
    if (zoomInput) zoomInput.value = String(zoom);
    updateCoordsLabel();
})();
</script>
<?= $view->include('clientes/_geo-script', ['provinciaId' => 'ficha-nac-provincia', 'ciudadId' => 'ficha-nac-ciudad']) ?>
<?php $view->endSection() ?>
