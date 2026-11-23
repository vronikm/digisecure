<?php
/**
 * Listado de consentimientos LOPDP.
 *
 * Variables: $user, $csrfToken, $currentPath, $consentimientos, $activos, $paginacion (Paginacion), $filters, $policyResources
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$filters = is_array($filters ?? null) ? $filters : [];
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
$total = $paginacion->total();
?>

<?php $view->section('title') ?>Consentimientos LOPDP<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-op-filter .ds-filter-bar {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .ds-op-filter .ds-filter-row {
        padding: 0;
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item active">Consentimientos</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-shield-check"></i></span>
        <div>
            <h1>Consentimientos LOPDP</h1>
            <small data-resumen-de="#consentimientosResultados" aria-live="polite"><?= (int) ($total ?? 0) ?> registro<?= ((int) ($total ?? 0)) !== 1 ? 's' : '' ?> (<?= (int) $activos ?> activo<?= $activos !== 1 ? 's' : '' ?>)</small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Panel LOPDP
    </a>
</div>

<div class="ds-card ds-op-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de consentimientos</div>
            <div class="ds-card-subtitle">Consulta por estado y rango de fechas</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosConsentimientos" method="get" action="<?= $view->url('/lopdp/consentimientos') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroConsEstado">Estado</label>
                        <select name="estado" id="filtroConsEstado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="revocado" <?= ($filters['estado'] ?? '') === 'revocado' ? 'selected' : '' ?>>Revocado</option>
                            <option value="rechazado" <?= ($filters['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroConsDesde">Desde</label>
                        <input type="date" name="desde" id="filtroConsDesde" class="form-control form-control-sm" value="<?= $e((string) ($filters['desde'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroConsHasta">Hasta</label>
                        <input type="date" name="hasta" id="filtroConsHasta" class="form-control form-control-sm" value="<?= $e((string) ($filters['hasta'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/lopdp/consentimientos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($policyResources ?? [])): ?>
<div class="ds-card" style="margin-top:1rem;">
    <div class="ds-card-header tone-info">
        <span class="ds-card-mark"><i class="bi bi-file-earmark-pdf"></i></span>
        <div>
            <div class="ds-card-title">Política de Privacidad (TDP)</div>
            <div class="ds-card-subtitle">Recursos basados en el documento PoliticaPrivacidad_TDP.pdf</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
            <div style="flex:1 1 320px;min-width:0;">
                <embed src="<?= $view->url('/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf') ?>" type="application/pdf" width="100%" height="450px" />
                <div style="margin-top:.5rem;"><a href="<?= $view->url('/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf') ?>" target="_blank" rel="noopener">Abrir PDF en nueva pestaña</a></div>
            </div>
            <div style="flex:1 1 280px;max-width:420px;min-width:0;">
                <h4 style="margin-top:0;">Consentimientos asociados</h4>
                <ul style="padding-left:1rem;">
                    <?php foreach ($policyResources as $pr): ?>
                        <li>
                            <div><strong><?= $e(ucfirst((string)$pr['titular_tipo'])) ?>:</strong> <span class="ds-cell-mono"><?= $e((string)$pr['titular_id']) ?></span></div>
                            <div style="font-size:.9rem;color:var(--ds-text-soft);">Propósito: <?= $e((string)$pr['proposito']) ?></div>
                            <div style="font-size:.8rem;color:var(--ds-text-soft);">Aceptado: <?= date('d/m/Y H:i', strtotime((string)$pr['aceptado_at'])) ?></div>
                            <div style="margin-bottom:.5rem;"><a href="<?= $view->url('/lopdp/consentimientos/' . $pr['id']) ?>">Ver detalle</a></div>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-person-check"></i></span>
        <div>
            <div class="ds-card-title">Listado de consentimientos</div>
            <div class="ds-card-subtitle">Trazabilidad de aceptacion y revocacion por titular</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarConsentimientos',
            'destino' => '#consentimientosResultados',
            'parcial' => 'consentimientos',
            'ruta' => '/lopdp/consentimientos',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar titular...',
            'ayuda' => 'Buscar consentimientos por proposito, tipo o identificador del titular',
            'filtros' => '#filtrosConsentimientos',
            'ocultos' => [
                'estado' => (string) ($filters['estado'] ?? ''),
                'desde' => (string) ($filters['desde'] ?? ''),
                'hasta' => (string) ($filters['hasta'] ?? ''),
            ],
        ]) ?>
    </div>
    <div id="consentimientosResultados" class="ds-listado-wrap">
        <?= $view->include('lopdp/consentimientos/_listado', [
            'csrfToken' => $csrfToken,
            'consentimientos' => $consentimientos,
            'paginacion' => $paginacion,
            'filters' => $filters,
        ]) ?>
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
(() => {
    const isValidReason = (value) => String(value || '').trim().length >= 5;
    // El titular y el proposito vienen de la base: se escapan antes de ir al HTML del dialogo.
    const escapar = (texto) => String(texto).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Delegado en el documento: las filas se reemplazan al buscar o paginar (ds-tabla-remota.js).
    document.addEventListener('submit', (event) => {
        const form = event.target instanceof Element ? event.target.closest('.js-consent-revoke-form') : null;
        if (!form) {
            return;
        }
        event.preventDefault();

        const titular = form.dataset.titular || 'titular';
        const proposito = form.dataset.proposito || 'proposito no especificado';
        const motivoInput = form.querySelector('input[name="motivo"]');
        if (!motivoInput) {
            return;
        }

        const submitWithReason = (motivo) => {
            motivoInput.value = String(motivo || '').trim();
            form.submit();
        };

        if (typeof window.Swal === 'undefined') {
            const fallback = window.prompt(
                `Motivo de revocacion para ${titular} (${proposito}). Minimo 5 caracteres:`,
                ''
            );
            if (fallback !== null && isValidReason(fallback)) {
                submitWithReason(fallback);
                return;
            }
            if (fallback !== null) {
                window.alert('Ingresa un motivo valido (minimo 5 caracteres).');
            }
            return;
        }

        window.Swal.fire({
            icon: 'warning',
            title: 'Revocar consentimiento',
            html: `<div style="text-align:left;font-size:.9rem;color:var(--ds-text-soft);">` +
                `<div><strong>Titular:</strong> ${escapar(titular)}</div>` +
                `<div style="margin-top:.25rem;"><strong>Proposito:</strong> ${escapar(proposito)}</div>` +
                `</div>`,
            input: 'textarea',
            inputLabel: 'Motivo de revocacion',
            inputPlaceholder: 'Ejemplo: solicitud expresa del titular para retirar su autorizacion.',
            inputAttributes: {
                maxlength: '255',
            },
            showCancelButton: true,
            confirmButtonText: 'Si, revocar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            preConfirm: (value) => {
                const motivo = String(value || '').trim();
                if (!isValidReason(motivo)) {
                    window.Swal.showValidationMessage('Ingresa un motivo de al menos 5 caracteres.');
                    return false;
                }
                return motivo;
            },
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }
            submitWithReason(result.value);
        });
    });
})();
</script>
<?php $view->endSection() ?>
