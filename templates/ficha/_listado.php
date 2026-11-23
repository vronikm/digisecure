<?php
/**
 * Fragmento: tabla de guardias + resumen + paginacion.
 *
 * Lo usa ficha/index.php dentro de #guardiasResultados y lo devuelve solo
 * FichaController::index cuando la peticion trae "X-Partial: guardias"
 * (busqueda y paginacion en vivo con ds-tabla-remota.js).
 *
 * Variables: $guardias, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$total = $paginacion->total();
$busqueda = (string) ($filters['busqueda'] ?? '');
$resumen = $total . ' guardia' . ($total !== 1 ? 's' : '') . ($busqueda !== '' ? ' encontrado' . ($total !== 1 ? 's' : '') : ' registrado' . ($total !== 1 ? 's' : ''));
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($guardias === [] && $busqueda !== ''): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/guardias'),
        'sugerencia' => 'Prueba con el apellido, el nombre, el celular, el correo o la cedula completa (10 digitos).',
    ]) ?>
<?php elseif ($guardias === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-badge"></i></div>
            <h3>No hay guardias para mostrar</h3>
            <p>Cuando registres fichas de guardias, se mostraran aqui agrupadas por persona.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table" id="tablaGuardias">
            <thead>
                <tr>
                    <th>Cedula</th>
                    <th>Apellidos</th>
                    <th>Nombres</th>
                    <th>Fecha de nacimiento</th>
                    <th>Celular</th>
                    <th>Correo</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($guardias as $g):
                    $historialUrl = $view->url('/guardias/' . (string) ($g['ficha_id'] ?? '') . '/fichas');
                ?>
                <tr class="js-row-link" data-href="<?= $historialUrl ?>">
                    <td><span class="ds-cell-mono"><?= $e((string) ($g['cedula_display'] ?? '-')) ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e((string) ($g['apellidos'] ?? '')) ?></div>
                        <?php if (!empty($g['fecha_baja'])): ?>
                        <span class="ds-badge tone-muted no-dot" style="font-size:.62rem;">De baja</span>
                        <?php elseif (!empty($g['es_aspirante'])): ?>
                        <span class="ds-badge tone-info no-dot" style="font-size:.62rem;" title="Ficha registrada por el aspirante; aún no contratado">Aspirante</span>
                        <?php else: ?>
                        <span class="ds-badge tone-success no-dot" style="font-size:.62rem;">Empleado</span>
                        <?php endif ?>
                    </td>
                    <td><div class="ds-cell-name"><?= $e((string) ($g['nombres'] ?? '')) ?></div></td>
                    <td>
                        <?php if (!empty($g['fecha_nacimiento'])): ?>
                        <span class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $g['fecha_nacimiento'])) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($g['celular'])): ?>
                        <span class="ds-cell-mono"><?= $e((string) $g['celular']) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($g['email'])): ?>
                        <span class="ds-cell-mono"><?= $e((string) $g['email']) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $historialUrl ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver fichas del guardia"
                               aria-label="Ver fichas de <?= $e(trim((string) ($g['apellidos'] ?? '') . ' ' . (string) ($g['nombres'] ?? ''))) ?>">
                                <i class="bi bi-folder2-open"></i>Ver fichas
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', [
        'paginacion' => $paginacion,
        'ruta' => '/guardias',
        'params' => ['q' => $busqueda],
        'nombre' => 'guardias',
    ]) ?>
<?php endif ?>
</div>
