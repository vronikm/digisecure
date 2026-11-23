<?php
/**
 * Fragmento: tabla de asignaciones + paginacion. Lo incluye
 * asignaciones/index.php y lo devuelve solo AsignacionController::index con
 * "X-Partial: asignaciones".
 *
 * Variables: $user, $asignaciones, $paginacion (Paginacion), $filters
 */

use DigiSecure\Asignacion\Domain\Enum\EstadoAsignacion;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$today = date('Y-m-d');
$busqueda = (string) ($filters['q'] ?? '');
$params = [
    'q' => $busqueda,
    'estado' => (string) ($filters['estado'] ?? ''),
    'cliente_id' => (string) ($filters['cliente_id'] ?? ''),
    'puesto_id' => (string) ($filters['puesto_id'] ?? ''),
    'ficha_id' => (string) ($filters['ficha_id'] ?? ''),
];
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' asignacion' . ($total !== 1 ? 'es' : '') . ($hayFiltros ? ' encontrada' . ($total !== 1 ? 's' : '') : ' en el listado');
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($asignaciones === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/asignaciones'),
        'sugerencia' => 'Busca por nombre del guardia, correlativo, puesto, codigo del puesto o cliente.',
    ]) ?>
<?php elseif ($asignaciones === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-link-45deg"></i></div>
            <h3>No hay asignaciones para mostrar</h3>
            <p>Crea la primera asignacion vinculando un guardia (ficha COMPLETA) a un puesto activo.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Guardia</th>
                    <th>Puesto / Cliente</th>
                    <th>Periodo</th>
                    <th>Rol en sitio</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($asignaciones as $a):
                    $estado = EstadoAsignacion::tryFrom((string) $a['estado']);
                    $tone = $estado ? $estado->tone() : 'tone-muted';
                    $vencidaProxima = $estado === EstadoAsignacion::ACTIVA
                        && !empty($a['fecha_fin'])
                        && $a['fecha_fin'] >= $today
                        && (strtotime((string) $a['fecha_fin']) - time()) <= 14 * 86400;
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/asignaciones/' . $a['id']) ?>"
                           class="ds-cell-name" style="text-decoration: none;">
                            <?= $e((string) $a['ficha_apellidos']) ?> <?= $e((string) $a['ficha_nombres']) ?>
                        </a>
                        <div class="ds-cell-sub"><span class="ds-cell-mono"><?= $e((string) $a['ficha_correlativo']) ?></span></div>
                    </td>
                    <td>
                        <div class="ds-cell-name" style="font-weight: 500;"><?= $e((string) $a['puesto_nombre']) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) $a['cliente_razon_social']) ?></div>
                    </td>
                    <td>
                        <div class="ds-cell-mono" style="font-weight: 600; color: var(--ds-text);">
                            <?= date('d/m/Y', strtotime((string) $a['fecha_inicio'])) ?>
                        </div>
                        <div class="ds-cell-sub">
                            <?php if (!empty($a['fecha_fin'])): ?>
                                hasta <?= date('d/m/Y', strtotime((string) $a['fecha_fin'])) ?>
                                <?php if ($vencidaProxima): ?>
                                    <span class="ds-badge tone-warning no-dot" style="font-size: .65rem; margin-left: .25rem;">por vencer</span>
                                <?php endif ?>
                            <?php else: ?>
                                <span style="color:#059669;">vigente</span>
                            <?php endif ?>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($a['rol_en_puesto'])): ?>
                            <span class="ds-chip"><?= $e((string) $a['rol_en_puesto']) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $estado ? $e($estado->label()) : $e((string) $a['estado']) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/asignaciones/' . $a['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle">
                                Ver
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/asignaciones', 'params' => $params, 'nombre' => 'asignaciones']) ?>
<?php endif ?>
</div>
