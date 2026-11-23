<?php
/**
 * Fragmento: tabla de puestos + paginacion. Lo incluye puestos/index.php y lo
 * devuelve solo PuestoController::index con "X-Partial: puestos".
 *
 * Variables: $user, $puestos, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Puesto\Domain\Enum\TipoPuesto;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$estadoTone = ['activo' => 'tone-success', 'inactivo' => 'tone-warning', 'archivado' => 'tone-muted'];
$busqueda = (string) ($filters['q'] ?? '');
$params = [
    'q' => $busqueda,
    'estado' => (string) ($filters['estado'] ?? ''),
    'tipo' => (string) ($filters['tipo'] ?? ''),
    'cliente_id' => (string) ($filters['cliente_id'] ?? ''),
];
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' puesto' . ($total !== 1 ? 's' : '') . ($hayFiltros ? ' encontrado' . ($total !== 1 ? 's' : '') : ' en el listado');
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($puestos === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/puestos'),
        'sugerencia' => 'Busca por nombre, codigo, cliente, direccion, ciudad, tipo o modalidad.',
    ]) ?>
<?php elseif ($puestos === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-geo-alt"></i></div>
            <h3>No hay puestos para mostrar</h3>
            <p>Crea el primer puesto para asociarlo a un cliente y poder asignar guardias.</p>
            <?php if ($user->tienePermiso('puestos.crear')): ?>
            <a href="<?= $view->url('/puestos/crear') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-plus-lg"></i>Crear el primer puesto
            </a>
            <?php endif ?>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table" id="tablaPuestos">
            <thead>
                <tr>
                    <th>Puesto</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Modalidad</th>
                    <th class="ds-cell-end">Plazas</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($puestos as $p):
                    $tipo = TipoPuesto::tryFrom((string) $p['tipo']);
                    $tone = $estadoTone[$p['estado']] ?? 'tone-muted';
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/puestos/' . $p['id']) ?>"
                           class="ds-cell-name" style="text-decoration: none;">
                            <?= $e((string) $p['nombre']) ?>
                        </a>
                        <div class="ds-cell-sub">
                            <?php if (!empty($p['codigo'])): ?>
                                <span class="ds-cell-mono"><?= $e((string) $p['codigo']) ?></span> &middot;
                            <?php endif ?>
                            <?= $e((string) ($p['ciudad'] ?? '-')) ?>
                        </div>
                    </td>
                    <td><div class="ds-cell-name" style="font-weight: 500;"><?= $e((string) $p['cliente_razon_social']) ?></div></td>
                    <td>
                        <?php if ($tipo): ?>
                            <span class="ds-chip"><i class="bi <?= $tipo->icon() ?>"></i><?= $e($tipo->label()) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub"><?= $e((string) $p['tipo']) ?></span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-cell-sub"><?= $e(ucfirst((string) $p['modalidad'])) ?></span></td>
                    <td class="ds-cell-end"><span class="ds-chip"><i class="bi bi-people"></i><?= (int) $p['num_guardias_requeridos'] ?></span></td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e(ucfirst((string) $p['estado'])) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/puestos/' . $p['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle"
                               aria-label="Ver detalle del puesto <?= $e((string) $p['nombre']) ?>">
                                <i class="bi bi-eye"></i>Ver
                            </a>
                            <?php if ($user->tienePermiso('puestos.editar') && $p['estado'] !== 'archivado'): ?>
                            <a href="<?= $view->url('/puestos/' . $p['id'] . '/editar') ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Editar"
                               aria-label="Editar el puesto <?= $e((string) $p['nombre']) ?>">
                                <i class="bi bi-pencil-square"></i>Editar
                            </a>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/puestos', 'params' => $params, 'nombre' => 'puestos']) ?>
<?php endif ?>
</div>
