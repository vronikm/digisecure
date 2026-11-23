<?php
/**
 * Fragmento: tabla de clientes + paginacion. Lo incluye clientes/index.php y
 * lo devuelve solo ClienteController::index con "X-Partial: clientes".
 *
 * Variables: $user, $clientes, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$estadoTone = ['activo' => 'tone-success', 'inactivo' => 'tone-warning', 'archivado' => 'tone-muted'];
$busqueda = (string) ($filters['q'] ?? '');
$hayFiltros = $busqueda !== '' || !empty($filters['estado']);
$total = $paginacion->total();
$resumen = $total . ' cliente' . ($total !== 1 ? 's' : '') . ($hayFiltros ? ' encontrado' . ($total !== 1 ? 's' : '') : ' en el listado');
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($clientes === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/clientes'),
        'sugerencia' => 'Busca por razon social, nombre comercial, RUC/cedula, contacto, correo, ciudad o sector.',
    ]) ?>
<?php elseif ($clientes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-briefcase"></i></div>
            <h3>No hay clientes para mostrar</h3>
            <p>Registra el primer cliente para empezar a asociar puestos, turnos y guardias.</p>
            <?php if ($user->tienePermiso('clientes.crear')): ?>
            <a href="<?= $view->url('/clientes/crear') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-plus-lg"></i>Crear el primer cliente
            </a>
            <?php endif ?>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table" id="tablaClientes">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Contacto</th>
                    <th>Sector</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c):
                    $tone = $estadoTone[$c['estado']] ?? 'tone-muted';
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/clientes/' . $c['id']) ?>"
                           class="ds-cell-name" style="text-decoration: none;">
                            <?= $e((string) $c['razon_social']) ?>
                        </a>
                        <?php if (!empty($c['nombre_comercial'])): ?>
                        <div class="ds-cell-sub"><?= $e((string) $c['nombre_comercial']) ?></div>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="ds-chip"><i class="bi bi-tag"></i><?= $e((string) $c['tipo_documento']) ?></span>
                        <div class="ds-cell-mono" style="margin-top: .15rem;"><?= $e((string) $c['documento']) ?></div>
                    </td>
                    <td>
                        <?php if (!empty($c['contacto_nombre'])): ?>
                        <div class="ds-cell-name" style="font-weight: 500;"><?= $e((string) $c['contacto_nombre']) ?></div>
                        <?php endif ?>
                        <?php if (!empty($c['contacto_email'])): ?>
                        <div class="ds-cell-mono"><?= $e((string) $c['contacto_email']) ?></div>
                        <?php elseif (!empty($c['contacto_telefono'])): ?>
                        <div class="ds-cell-mono"><?= $e((string) $c['contacto_telefono']) ?></div>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($c['sector'])): ?>
                            <span class="ds-chip"><?= $e((string) $c['sector']) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e(ucfirst((string) $c['estado'])) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/clientes/' . $c['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle"
                               aria-label="Ver detalle de <?= $e((string) $c['razon_social']) ?>">
                                <i class="bi bi-eye"></i>Ver
                            </a>
                            <?php if ($user->tienePermiso('clientes.editar') && $c['estado'] !== 'archivado'): ?>
                            <a href="<?= $view->url('/clientes/' . $c['id'] . '/editar') ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Editar"
                               aria-label="Editar <?= $e((string) $c['razon_social']) ?>">
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
    <?= $view->include('partials/paginacion', [
        'paginacion' => $paginacion,
        'ruta' => '/clientes',
        'params' => ['q' => $busqueda, 'estado' => (string) ($filters['estado'] ?? '')],
        'nombre' => 'clientes',
    ]) ?>
<?php endif ?>
</div>
