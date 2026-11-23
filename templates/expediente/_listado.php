<?php
/**
 * Fragmento: tabla de expedientes + paginacion. Lo incluye expediente/index.php
 * y lo devuelve solo ExpedienteController::listado con "X-Partial: expedientes".
 *
 * Variables: $expedientes, $paginacion (Paginacion), $filters, $ruta, $mostrarBloqueB
 */

use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$mostrarBloqueB = (bool) ($mostrarBloqueB ?? false);
$ruta = (string) ($ruta ?? '/expedientes');
$estadoTone = [
    'INCOMPLETO'         => 'tone-warning',
    'BLOQUE_A_COMPLETO'  => 'tone-info',
    'COMPLETO'           => 'tone-success',
];
$busqueda = (string) ($filters['busqueda'] ?? '');
// En /expedientes/pendientes el estado es fijo: no viaja en la URL.
$params = ['q' => $busqueda];
if ($ruta === '/expedientes') {
    $params['estado'] = (string) ($filters['estado'] ?? '');
}
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' expediente' . ($total !== 1 ? 's' : '') . ($hayFiltros ? ' encontrado' . ($total !== 1 ? 's' : '') : '');
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($expedientes === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url($ruta),
        'sugerencia' => 'Busca por apellidos, nombres, correlativo, correo o celular del guardia.',
    ]) ?>
<?php elseif ($expedientes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-folder2"></i></div>
            <h3>No hay expedientes para mostrar</h3>
            <p>Los expedientes se generan automaticamente al crear una ficha de guardia.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Correlativo</th>
                    <th>Guardia</th>
                    <th>Estado</th>
                    <th>Bloque A</th>
                    <?php if ($mostrarBloqueB): ?>
                    <th>Bloque B</th>
                    <?php endif ?>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expedientes as $exp):
                    $estado = EstadoExpediente::from($exp['estado']);
                    $tone = $estadoTone[$exp['estado']] ?? 'tone-muted';
                    $pctA = $exp['bloque_a_total'] > 0 ? round(($exp['bloque_a_aceptados'] / $exp['bloque_a_total']) * 100) : 0;
                    $pctB = $exp['bloque_b_total'] > 0 ? round(($exp['bloque_b_aceptados'] / $exp['bloque_b_total']) * 100) : 0;
                    $toneA = $pctA === 100 ? 'tone-success' : ($pctA >= 50 ? '' : 'tone-warning');
                    $toneB = $pctB === 100 ? 'tone-success' : 'tone-info';
                ?>
                <tr>
                    <td><span class="ds-cell-mono" style="font-weight:700;color:var(--ds-text);"><?= $e($exp['correlativo']) ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e($exp['apellidos']) ?> <?= $e($exp['nombres']) ?></div>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e($estado->label()) ?></span></td>
                    <td>
                        <div class="ds-progress-row">
                            <div class="ds-progress">
                                <div class="ds-progress-bar <?= $toneA ?>" style="width: <?= $pctA ?>%"></div>
                            </div>
                            <small><?= $exp['bloque_a_aceptados'] ?>/<?= $exp['bloque_a_total'] ?></small>
                        </div>
                    </td>
                    <?php if ($mostrarBloqueB): ?>
                    <td>
                        <?php if ($exp['bloque_b_desbloqueado']): ?>
                        <div class="ds-progress-row">
                            <div class="ds-progress">
                                <div class="ds-progress-bar <?= $toneB ?>" style="width: <?= $pctB ?>%"></div>
                            </div>
                            <small><?= $exp['bloque_b_aceptados'] ?>/<?= $exp['bloque_b_total'] ?></small>
                        </div>
                        <?php else: ?>
                        <span class="ds-cell-sub"><i class="bi bi-lock"></i> Bloqueado</span>
                        <?php endif ?>
                    </td>
                    <?php endif ?>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/guardias/' . $exp['ficha_id'] . '/expediente') ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm">
                                Abrir expediente
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => $ruta, 'params' => $params, 'nombre' => 'expedientes']) ?>
<?php endif ?>
</div>
