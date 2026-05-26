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
        <div class="row g-3">
            <div class="col-md-4">
                <label class="ds-form-label">Titular tipo</label>
                <div class="ds-cell-name"><?= $e(ucfirst((string) $consentimiento['titular_tipo'])) ?></div>
            </div>
            <div class="col-md-8">
                <label class="ds-form-label">Titular ID</label>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['titular_id']) ?></div>
            </div>
            <div class="col-md-8">
                <label class="ds-form-label">Proposito</label>
                <div><?= $e((string) $consentimiento['proposito']) ?></div>
            </div>
            <div class="col-md-4">
                <label class="ds-form-label">Base legal</label>
                <div><?= $e(ucfirst(str_replace('_', ' ', (string) $consentimiento['base_legal']))) ?></div>
            </div>
            <div class="col-md-4">
                <label class="ds-form-label">Version texto legal</label>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['texto_legal_version']) ?></div>
            </div>
            <div class="col-md-8">
                <label class="ds-form-label">Hash texto legal</label>
                <div class="ds-cell-mono" style="word-break: break-all;"><?= $e((string) $consentimiento['texto_legal_hash']) ?></div>
            </div>
            <div class="col-md-4">
                <label class="ds-form-label">Fecha aceptacion</label>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['aceptado_at']) ?></div>
            </div>
            <div class="col-md-4">
                <label class="ds-form-label">IP aceptacion</label>
                <div class="ds-cell-mono"><?= $e((string) ($ipAceptacion ?? '-')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="ds-form-label">Estado</label>
                <div><span class="ds-badge <?= $estadoTone ?>"><?= $e($estadoLabel) ?></span></div>
            </div>
            <div class="col-12">
                <label class="ds-form-label">User-Agent aceptacion</label>
                <div style="color: var(--ds-text-soft); font-size: .85rem; word-break: break-word;">
                    <?= $e((string) ($consentimiento['user_agent_aceptacion'] ?? '-')) ?>
                </div>
            </div>
            <?php if ($revocado): ?>
            <div class="col-md-4">
                <label class="ds-form-label">Fecha revocacion</label>
                <div class="ds-cell-mono"><?= $e((string) $consentimiento['revocado_at']) ?></div>
            </div>
            <div class="col-md-8">
                <label class="ds-form-label">Motivo revocacion</label>
                <div style="white-space: pre-wrap;"><?= $e((string) ($consentimiento['revocado_motivo'] ?? '')) ?></div>
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
