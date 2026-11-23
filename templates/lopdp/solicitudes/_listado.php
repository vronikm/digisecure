<?php
/**
 * Fragmento: tabla de solicitudes ARCO-P + paginacion. Lo incluye
 * lopdp/solicitudes/index.php y lo devuelve solo LopdpController::listarSolicitudes
 * con "X-Partial: solicitudes".
 *
 * Variables: $solicitudes, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$estadoTone = static fn(string $estado) => match ($estado) {
    'RECIBIDA'   => 'tone-info',
    'EN_PROCESO' => 'tone-warning',
    'RESPONDIDA' => 'tone-success',
    'DENEGADA'   => 'tone-muted',
    default      => 'tone-muted',
};
$today = date('Y-m-d');
$busqueda = (string) ($filters['busqueda'] ?? '');
$params = ['q' => $busqueda, 'estado' => (string) ($filters['estado'] ?? ''), 'tipo' => (string) ($filters['tipo'] ?? '')];
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' solicitud' . ($total !== 1 ? 'es' : '') . ($hayFiltros ? ' encontrada' . ($total !== 1 ? 's' : '') : ' registrada' . ($total !== 1 ? 's' : ''));
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($solicitudes === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/lopdp/solicitudes'),
        'sugerencia' => 'Busca por nombre, cedula o correo del titular, tipo de derecho o estado.',
    ]) ?>
<?php elseif ($solicitudes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-file-earmark-lock"></i></div>
            <h3>No hay solicitudes registradas</h3>
            <p>Cuando un titular ejerza sus derechos ARCO-P, podras registrar la solicitud aqui.</p>
            <a href="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-plus-lg"></i>Registrar primera solicitud
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Titular</th>
                    <th>Recepcion</th>
                    <th>Vence</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $s):
                    $vencida = $s['estado'] !== 'RESPONDIDA' && $s['estado'] !== 'DENEGADA' && $s['fecha_limite'] < $today;
                    $tone = $estadoTone((string) $s['estado']);
                ?>
                <tr <?= $vencida ? 'style="background:rgba(220,38,38,.04);"' : '' ?>>
                    <td><span class="ds-chip"><i class="bi bi-tag"></i><?= $e(ucfirst((string) $s['tipo'])) ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e((string) $s['titular_nombre']) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) ($s['titular_cedula'] ?? '-')) ?> &middot; <?= $e((string) $s['titular_tipo']) ?></div>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e((string) $s['fecha_recepcion']) ?></span></td>
                    <td>
                        <?php if ($vencida): ?>
                            <span class="ds-badge tone-danger no-dot"><i class="bi bi-exclamation-triangle"></i><?= $e((string) $s['fecha_limite']) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-mono"><?= $e((string) $s['fecha_limite']) ?></span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e((string) $s['estado']) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/lopdp/solicitudes/' . $s['id']) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle">
                                Ver
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/lopdp/solicitudes', 'params' => $params, 'nombre' => 'solicitudes ARCO-P']) ?>
<?php endif ?>
</div>
