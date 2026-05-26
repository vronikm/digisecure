<?php
/**
 * Detalle de consentimiento LOPDP.
 *
 * Variables: $user, $csrfToken, $currentPath, $consentimiento, $ipAceptacion, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$revocado = !empty($consentimiento['revocado_at']);
$aceptado = (int) ($consentimiento['aceptado'] ?? 0) === 1;
$estadoTone = $revocado ? 'tone-muted' : ($aceptado ? 'tone-success' : 'tone-warning');
$estadoLabel = $revocado ? 'Revocado' : ($aceptado ? 'Activo' : 'Rechazado');
$detailPath = '/lopdp/consentimientos/' . (string) $consentimiento['id'];
?>

<?php $view->section('title') ?>Detalle consentimiento LOPDP<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-detail-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .7rem;
    }

    .ds-detail-item {
        grid-column: span 6;
        padding: .72rem .82rem;
        border: 1px solid rgba(21, 48, 79, 0.09);
        border-radius: 12px;
        background: linear-gradient(180deg, #fcfdff, #f7f9fc);
        min-width: 0;
    }

    .ds-detail-item.span-4 { grid-column: span 4; }
    .ds-detail-item.span-8 { grid-column: span 8; }
    .ds-detail-item.span-12 { grid-column: span 12; }

    .ds-detail-label {
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ds-text-soft);
        margin-bottom: .2rem;
        line-height: 1.2;
    }

    .ds-detail-value {
        font-size: .93rem;
        font-weight: 600;
        color: var(--ds-text);
        line-height: 1.3;
    }

    .ds-detail-value.break {
        white-space: normal;
        word-break: break-word;
    }

    @media (max-width: 1199.98px) {
        .ds-detail-item.span-8 { grid-column: span 12; }
    }

    @media (max-width: 767.98px) {
        .ds-detail-item,
        .ds-detail-item.span-4,
        .ds-detail-item.span-8,
        .ds-detail-item.span-12 {
            grid-column: span 12;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp/consentimientos') ?>">Consentimientos</a></li>
<li class="breadcrumb-item active">Detalle</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-shield-check"></i></span>
        <div>
            <h1>Detalle de consentimiento</h1>
            <small>
                <span class="ds-badge <?= $estadoTone ?>"><?= $e($estadoLabel) ?></span>
                <span class="ds-cell-mono" style="margin-left:.5rem;">ID: <?= $e((string) $consentimiento['id']) ?></span>
            </small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp/consentimientos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo completar la accion</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e((string) $error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="ds-card" style="margin-bottom: 1rem;">
    <div class="ds-card-header">
        <span class="ds-card-mark"><i class="bi bi-file-earmark-text"></i></span>
        <div>
            <div class="ds-card-title">Trazabilidad legal</div>
            <div class="ds-card-subtitle">Informacion del consentimiento registrado</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-detail-grid">
            <div class="ds-detail-item span-4">
                <div class="ds-detail-label">Titular tipo</div>
                <div class="ds-detail-value"><?= $e(ucfirst((string) $consentimiento['titular_tipo'])) ?></div>
            </div>
            <div class="ds-detail-item span-8">
                <div class="ds-detail-label">Titular ID</div>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['titular_id']) ?></div>
            </div>
            <div class="ds-detail-item span-8">
                <div class="ds-detail-label">Proposito</div>
                <div class="ds-detail-value break"><?= $e((string) $consentimiento['proposito']) ?></div>
            </div>
            <div class="ds-detail-item span-4">
                <div class="ds-detail-label">Base legal</div>
                <div class="ds-detail-value"><?= $e(ucfirst(str_replace('_', ' ', (string) $consentimiento['base_legal']))) ?></div>
            </div>
            <div class="ds-detail-item span-4">
                <div class="ds-detail-label">Version texto legal</div>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['texto_legal_version']) ?></div>
            </div>
            <div class="ds-detail-item span-8">
                <div class="ds-detail-label">Hash texto legal</div>
                <div class="ds-cell-mono" style="word-break: break-all;"><?= $e((string) $consentimiento['texto_legal_hash']) ?></div>
            </div>
            <div class="ds-detail-item span-4">
                <div class="ds-detail-label">Fecha aceptacion</div>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['aceptado_at']) ?></div>
            </div>
            <div class="ds-detail-item span-4">
                <div class="ds-detail-label">IP aceptacion</div>
                <div class="ds-cell-mono"><?= $e((string) ($ipAceptacion ?? '-')) ?></div>
            </div>
            <div class="ds-detail-item span-4">
                <div class="ds-detail-label">Estado</div>
                <div><span class="ds-badge <?= $estadoTone ?>"><?= $e($estadoLabel) ?></span></div>
            </div>
            <div class="ds-detail-item span-12">
                <div class="ds-detail-label">User-Agent aceptacion</div>
                <div class="ds-detail-value break" style="color: var(--ds-text-soft); font-weight: 500;"><?= $e((string) ($consentimiento['user_agent_aceptacion'] ?? '-')) ?></div>
            </div>
            <?php if ($revocado): ?>
            <div class="ds-detail-item span-4">
                <div class="ds-detail-label">Fecha revocacion</div>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['revocado_at']) ?></div>
            </div>
            <div class="ds-detail-item span-8">
                <div class="ds-detail-label">Motivo revocacion</div>
                <div class="ds-detail-value break"><?= $e((string) ($consentimiento['revocado_motivo'] ?? '')) ?></div>
            </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?php if (!$revocado && $aceptado): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626;">
    <div class="ds-card-header tone-danger">
        <span class="ds-card-mark"><i class="bi bi-x-octagon"></i></span>
        <div>
            <div class="ds-card-title">Revocar consentimiento</div>
            <div class="ds-card-subtitle">Esta accion deja trazabilidad en auditoria</div>
        </div>
    </div>
    <div class="ds-card-body">
        <form method="post"
              action="<?= $view->url('/lopdp/consentimientos/' . $consentimiento['id'] . '/revocar') ?>"
              class="ds-form-main js-consent-revoke-form"
              data-titular="<?= $e(ucfirst((string) $consentimiento['titular_tipo']) . ' ' . (string) $consentimiento['titular_id']) ?>"
              data-proposito="<?= $e((string) $consentimiento['proposito']) ?>">
            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="return_to" value="<?= $e($detailPath) ?>">
            <div class="ds-form-field">
                <label class="ds-form-label">Motivo de revocacion <span class="ds-required">*</span></label>
                <textarea name="motivo" class="form-control" rows="3" minlength="5" maxlength="255" required
                          placeholder="Explica la causa de la revocacion (minimo 5 caracteres)."></textarea>
            </div>
            <div class="ds-form-actions">
                <button type="submit" class="ds-btn ds-btn-danger">
                    Revocar consentimiento
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif ?>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
    const forms = document.querySelectorAll('.js-consent-revoke-form');
    if (!forms.length) {
        return;
    }

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const titular = form.dataset.titular || 'titular';
            const proposito = form.dataset.proposito || 'proposito no especificado';
            const motivo = String((form.querySelector('[name="motivo"]') || {}).value || '').trim();
            if (motivo.length < 5) {
                form.reportValidity();
                return;
            }

            const submitForm = () => form.submit();

            if (typeof window.Swal === 'undefined') {
                if (window.confirm('Se revocara este consentimiento. Deseas continuar?')) {
                    submitForm();
                }
                return;
            }

            window.Swal.fire({
                icon: 'warning',
                title: 'Confirmar revocacion',
                html: `<div style="text-align:left;font-size:.9rem;color:var(--ds-text-soft);">` +
                    `<div><strong>Titular:</strong> ${titular}</div>` +
                    `<div style="margin-top:.25rem;"><strong>Proposito:</strong> ${proposito}</div>` +
                    `<div style="margin-top:.5rem;"><strong>Motivo:</strong> ${motivo}</div>` +
                    `</div>`,
                showCancelButton: true,
                confirmButtonText: 'Si, revocar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                focusCancel: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    submitForm();
                }
            });
        });
    });
})();
</script>
<?php $view->endSection() ?>
