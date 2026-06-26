<?php
/**
 * Listado de consentimientos LOPDP.
 *
 * Variables: $user, $csrfToken, $currentPath, $consentimientos, $activos, $total, $page, $totalPages, $filters?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$filters = is_array($filters ?? null) ? $filters : [];
$hasFilters = !empty($filters['estado'] ?? null)
    || !empty($filters['desde'] ?? null)
    || !empty($filters['hasta'] ?? null);
$qs = http_build_query(array_filter([
    'estado' => $filters['estado'] ?? '',
    'desde' => $filters['desde'] ?? '',
    'hasta' => $filters['hasta'] ?? '',
], static fn(string $v): bool => $v !== ''));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
$returnQuery = http_build_query(array_filter([
    'page' => (int) ($page ?? 1) > 1 ? (int) ($page ?? 1) : null,
    'estado' => $filters['estado'] ?? '',
    'desde' => $filters['desde'] ?? '',
    'hasta' => $filters['hasta'] ?? '',
], static fn($v): bool => $v !== null && $v !== ''));
$returnTo = '/lopdp/consentimientos' . ($returnQuery !== '' ? '?' . $returnQuery : '');
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
            <small><?= (int) ($total ?? 0) ?> registro<?= ((int) ($total ?? 0)) !== 1 ? 's' : '' ?> (<?= (int) $activos ?> activo<?= $activos !== 1 ? 's' : '' ?>)</small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Panel LOPDP
    </a>
</div>

<div class="ds-card ds-op-filter">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de consentimientos</div>
            <div class="ds-card-subtitle">Consulta por estado y rango de fechas</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url('/lopdp/consentimientos') ?>" class="ds-filter-row ds-form-filter">
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="revocado" <?= ($filters['estado'] ?? '') === 'revocado' ? 'selected' : '' ?>>Revocado</option>
                            <option value="rechazado" <?= ($filters['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm"
                               value="<?= $e((string) ($filters['desde'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm"
                               value="<?= $e((string) ($filters['hasta'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-search"></i>Buscar
                    </button>
                    <?php if ($hasFilters): ?>
                    <a href="<?= $view->url('/lopdp/consentimientos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                    <?php endif ?>
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
        <div style="display:flex;gap:1rem;align-items:flex-start;">
            <div style="flex:1;min-width:320px;">
                <embed src="<?= $view->url('/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf') ?>" type="application/pdf" width="100%" height="450px" />
                <div style="margin-top:.5rem;"><a href="<?= $view->url('/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf') ?>" target="_blank" rel="noopener">Abrir PDF en nueva pestaña</a></div>
            </div>
            <div style="width:420px;">
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
    </div>

    <?php if ($consentimientos === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-shield-check"></i></div>
            <h3>Sin consentimientos registrados</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ningun consentimiento coincide con los filtros seleccionados.
                <?php else: ?>
                    Cuando un titular acepte el tratamiento de sus datos, el consentimiento quedara registrado aqui con trazabilidad completa.
                <?php endif ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Titular</th>
                    <th>Proposito</th>
                    <th>Aceptado</th>
                    <th>Estado</th>
                    <th>Revocado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consentimientos as $c):
                    $revocado = !empty($c['revocado_at']);
                    $aceptado = (int) $c['aceptado'] === 1;
                    $titularText = ucfirst((string) $c['titular_tipo']) . ' ' . (string) $c['titular_id'];
                ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e(ucfirst((string) $c['titular_tipo'])) ?></div>
                        <div class="ds-cell-sub"><span class="ds-cell-mono"><?= $e((string) $c['titular_id']) ?></span></div>
                    </td>
                    <td><div style="max-width: 360px;"><?= $e((string) $c['proposito']) ?></div></td>
                    <td><span class="ds-cell-mono"><?= date('d/m/Y H:i', strtotime((string) $c['aceptado_at'])) ?></span></td>
                    <td>
                        <?php if ($revocado): ?>
                            <span class="ds-badge tone-muted">Revocado</span>
                        <?php elseif ($aceptado): ?>
                            <span class="ds-badge tone-success">Activo</span>
                        <?php else: ?>
                            <span class="ds-badge tone-warning">Rechazado</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($revocado): ?>
                            <span class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $c['revocado_at'])) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/lopdp/consentimientos/' . $c['id']) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm"
                               title="Ver detalle">
                                Ver
                            </a>
                            <?php if (!$revocado && $aceptado): ?>
                            <form method="post"
                                  action="<?= $view->url('/lopdp/consentimientos/' . $c['id'] . '/revocar') ?>"
                                  class="d-inline ds-form-inline js-consent-revoke-form"
                                  data-titular="<?= $e($titularText) ?>"
                                  data-proposito="<?= $e((string) $c['proposito']) ?>">
                                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="return_to" value="<?= $e($returnTo) ?>">
                                <input type="hidden" name="motivo" value="">
                                <button type="submit" class="ds-btn ds-btn-danger ds-btn-sm" title="Revocar">
                                    Revocar
                                </button>
                            </form>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if (($totalPages ?? 1) > 1): ?>
    <div class="ds-table-foot">
        <span>Pagina <strong><?= (int) $page ?></strong> de <?= (int) $totalPages ?> &middot; <?= (int) $total ?> consentimiento<?= ((int) $total) !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= ((int) $page) <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/lopdp/consentimientos') ?>?page=<?= max(1, (int) $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, (int) $page - 2); $p <= min((int) $totalPages, (int) $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === (int) $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/lopdp/consentimientos') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= ((int) $page) >= (int) $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/lopdp/consentimientos') ?>?page=<?= min((int) $totalPages, (int) $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
    const forms = document.querySelectorAll('.js-consent-revoke-form');
    if (!forms.length) {
        return;
    }

    const isValidReason = (value) => String(value || '').trim().length >= 5;

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
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
                    `<div><strong>Titular:</strong> ${titular}</div>` +
                    `<div style="margin-top:.25rem;"><strong>Proposito:</strong> ${proposito}</div>` +
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
    });
})();
</script>
<?php $view->endSection() ?>
