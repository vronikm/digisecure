<?php
/**
 * Fragmento: tabla de aspirantes + paginacion. Lo incluye reclutamiento/index.php
 * y lo devuelve solo AspiranteAdminController::index con "X-Partial: reclutamiento".
 *
 * Variables: $user, $aspirantes, $paginacion (Paginacion), $filters, $estadosEditables
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
['tone' => $estadoTone, 'label' => $estadoLabel, 'tipo' => $tipoLabel] = require __DIR__ . '/_estados.php';
$busqueda = (string) ($filters['q'] ?? '');
$params = [
    'q' => $busqueda,
    'avance' => (string) ($filters['avance'] ?? ''),
    'estado' => (string) ($filters['estado'] ?? ''),
    'verificado' => (string) ($filters['verificado'] ?? ''),
];
$puedeVerFicha = $user->tienePermiso('guardias.ver');
$fichaLabel = ['BORRADOR' => 'Borrador', 'EN_REVISION' => 'Por revisar', 'COMPLETA' => 'Completa'];
$fichaTone = ['BORRADOR' => 'tone-muted', 'EN_REVISION' => 'tone-warning', 'COMPLETA' => 'tone-success'];
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' postulante' . ($total !== 1 ? 's' : '') . ($hayFiltros ? ' encontrado' . ($total !== 1 ? 's' : '') : ' registrado' . ($total !== 1 ? 's' : ''));
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($aspirantes === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/reclutamiento'),
        'sugerencia' => 'Busca por nombres, apellidos, documento, correo o celular.',
    ]) ?>
<?php elseif ($aspirantes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-plus"></i></div>
            <h3>No hay aspirantes para mostrar</h3>
            <p>Cuando alguien se registre desde la pantalla de inicio, aparecerá aquí.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Aspirante</th>
                    <th>Avance</th>
                    <th>Estado</th>
                    <th>Documento</th>
                    <th>Contacto</th>
                    <th>Email</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aspirantes as $a):
                    $tone = $estadoTone[$a['estado']] ?? 'tone-muted';
                    $verificado = (int) $a['email_verificado'] === 1;
                    $fichaId = trim((string) ($a['ficha_id'] ?? ''));
                    $puedeAbrirExpediente = $fichaId !== '' && $user->tieneAlgunPermiso('expedientes.ver', 'expedientes.validar', 'expedientes.revisar');
                    $fichaEstado = (string) ($a['ficha_estado'] ?? '');
                    $totalA = (int) ($a['bloque_a_total'] ?? 0);
                    $aceptadosA = min($totalA, (int) ($a['bloque_a_aceptados'] ?? 0));
                    $pctA = $totalA > 0 ? (int) round($aceptadosA * 100 / $totalA) : 0;
                    $enProceso = !in_array((string) $a['estado'], ['contratado', 'descartado'], true);
                    $listo = $enProceso && $verificado && $fichaEstado === 'COMPLETA' && $totalA > 0 && $aceptadosA >= $totalA;
                    $nombreAsp = trim($a['nombres'] . ' ' . $a['apellidos']);
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/reclutamiento/' . $a['id']) ?>" class="ds-cell-name" style="text-decoration: none;">
                            <?= $e(trim($a['nombres'] . ' ' . $a['apellidos'])) ?>
                        </a>
                    </td>
                    <td>
                        <div class="rc-avance">
                            <?php if ($fichaId === ''): ?>
                                <span class="ds-badge tone-muted no-dot"><i class="bi bi-file-earmark-x"></i> Sin ficha</span>
                            <?php else: ?>
                                <span class="ds-badge <?= $fichaTone[$fichaEstado] ?? 'tone-muted' ?> no-dot" title="Estado de la ficha de ingreso">
                                    <i class="bi bi-file-earmark-person"></i> Ficha: <?= $e($fichaLabel[$fichaEstado] ?? $fichaEstado) ?>
                                </span>
                                <?php if ($totalA > 0): ?>
                                <div class="rc-avance-docs" title="Documentos del Bloque A aceptados">
                                    <span>Bloque A <?= $aceptadosA ?>/<?= $totalA ?></span>
                                    <div class="ds-progress" role="progressbar" aria-valuenow="<?= $pctA ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Bloque A de <?= $e($nombreAsp) ?>">
                                        <div class="ds-progress-bar <?= $pctA >= 100 ? 'tone-success' : 'tone-info' ?>" style="width: <?= $pctA ?>%"></div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span class="ds-cell-sub">Sin expediente</span>
                                <?php endif ?>
                            <?php endif ?>
                            <?php if ($listo): ?>
                                <span class="ds-badge tone-purple no-dot"><i class="bi bi-person-check"></i> Listo para contratar</span>
                            <?php endif ?>
                        </div>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e($estadoLabel[$a['estado']] ?? (string) $a['estado']) ?></span></td>
                    <td>
                        <span class="ds-chip"><i class="bi bi-tag"></i><?= $e($tipoLabel[$a['tipo_documento']] ?? (string) $a['tipo_documento']) ?></span>
                        <div class="ds-cell-mono" style="margin-top: .15rem;"><?= $e(preg_match('/^\d{10}$/', (string) $a['documento']) === 1 ? '******' . substr((string) $a['documento'], -4) : (string) $a['documento']) ?></div>
                    </td>
                    <td>
                        <div class="ds-cell-mono"><?= $e((string) $a['correo']) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) $a['celular']) ?></div>
                    </td>
                    <td>
                        <?php if ($verificado): ?>
                            <span class="ds-badge tone-success"><i class="bi bi-check-circle"></i> Verificado</span>
                        <?php else: ?>
                            <span class="ds-badge tone-warning"><i class="bi bi-clock"></i> Sin verificar</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/reclutamiento/' . $a['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm">Revisar</a>
                            <?php if ($fichaId !== '' && $puedeVerFicha): ?>
                                <a href="<?= $view->url('/guardias/' . $fichaId) ?>" class="ds-btn ds-btn-outline ds-btn-sm"
                                   title="Revisar, enviar a revisión o completar la ficha" aria-label="Ficha de <?= $e($nombreAsp) ?>">
                                    <i class="bi bi-file-earmark-person"></i>Ficha
                                </a>
                            <?php endif ?>
                            <?php if (in_array((string) $a['estado'], $estadosEditables ?? [], true)): ?>
                                <a href="<?= $view->url('/reclutamiento/' . $a['id'] . '/editar') ?>" class="ds-btn ds-btn-ghost ds-btn-sm"
                                   title="Corregir datos del registro (correo, documento, nombres...)" aria-label="Editar datos de <?= $e($nombreAsp) ?>">
                                    <i class="bi bi-pencil-square"></i>Editar
                                </a>
                            <?php endif ?>
                            <?php if ($puedeAbrirExpediente): ?>
                                <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                                    <i class="bi bi-folder-check"></i>Documentos
                                </a>
                            <?php elseif ($fichaId === ''): ?>
                                <span class="ds-btn ds-btn-ghost ds-btn-sm is-disabled" title="El aspirante aun no ha creado su ficha.">
                                    <i class="bi bi-folder-x"></i>Sin expediente
                                </span>
                            <?php else: ?>
                                <span class="ds-btn ds-btn-ghost ds-btn-sm is-disabled" title="Tu usuario necesita permiso para ver o validar expedientes.">
                                    <i class="bi bi-lock"></i>Documentos
                                </span>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/reclutamiento', 'params' => $params, 'nombre' => 'aspirantes']) ?>
<?php endif ?>
</div>
