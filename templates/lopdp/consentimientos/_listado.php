<?php
/**
 * Fragmento: tabla de consentimientos + paginacion. Lo incluye
 * lopdp/consentimientos/index.php y lo devuelve solo
 * LopdpController::listarConsentimientos con "X-Partial: consentimientos".
 *
 * Variables: $csrfToken, $consentimientos, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$busqueda = (string) ($filters['q'] ?? '');
$params = [];
foreach (['q', 'estado', 'desde', 'hasta'] as $clave) {
    $params[$clave] = (string) ($filters[$clave] ?? '');
}
$hayFiltros = implode('', $params) !== '';
// Tras revocar se vuelve a la misma pagina, filtros y tamano.
$returnQuery = http_build_query(array_filter(
    $params + ['por_pagina' => $paginacion->parametro(), 'page' => $paginacion->pagina() > 1 ? $paginacion->pagina() : null],
    static fn($v): bool => $v !== null && $v !== '',
));
$returnTo = '/lopdp/consentimientos' . ($returnQuery !== '' ? '?' . $returnQuery : '');
$total = $paginacion->total();
$resumen = $total . ' consentimiento' . ($total !== 1 ? 's' : '') . ($hayFiltros ? ' encontrado' . ($total !== 1 ? 's' : '') : ' registrado' . ($total !== 1 ? 's' : ''));
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($consentimientos === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/lopdp/consentimientos'),
        'sugerencia' => 'Busca por proposito, tipo de titular o identificador del titular.',
    ]) ?>
<?php elseif ($consentimientos === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-shield-check"></i></div>
            <h3>Sin consentimientos registrados</h3>
            <p>Cuando un titular acepte el tratamiento de sus datos, el consentimiento quedara registrado aqui con trazabilidad completa.</p>
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

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/lopdp/consentimientos', 'params' => $params, 'nombre' => 'consentimientos']) ?>
<?php endif ?>
</div>
