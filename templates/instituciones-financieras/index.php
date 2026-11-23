<?php
/**
 * Listado del catálogo de instituciones financieras.
 *
 * Variables: $user, $csrfToken, $currentPath, $items, $kpis, $q
 */

use DigiSecure\Catalogo\Domain\TipoInstitucion;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$tipoTone = [
    'BANCO' => 'tone-success',
    'COOPERATIVA' => 'tone-purple',
];
?>

<?php $view->section('title') ?>Instituciones financieras - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active">Instituciones financieras</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-bank2"></i></span>
        <div>
            <h1>Instituciones financieras</h1>
            <small>Catálogo de bancos y cooperativas usado en la ficha del guardia</small>
        </div>
    </div>
    <a href="<?= $view->url('/configuracion/instituciones-financieras/nueva') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva institución
    </a>
</div>

<div class="ds-kpi-grid ds-kpi-grid-4 ds-kpi-grid-solid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-kpi-label">Total</div>
            <div class="ds-kpi-value"><?= (int) $kpis['total'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-bank"></i></span>
        <div>
            <div class="ds-kpi-label">Bancos</div>
            <div class="ds-kpi-value"><?= (int) $kpis['bancos'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-purple">
        <span class="ds-kpi-icon"><i class="bi bi-people"></i></span>
        <div>
            <div class="ds-kpi-label">Cooperativas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['cooperativas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Activas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['activas'] ?></div>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-bank2"></i></span>
        <div>
            <div class="ds-card-title">Catálogo</div>
            <div class="ds-card-subtitle">Primero bancos, luego cooperativas</div>
        </div>
        <?php if ($items !== []): ?>
        <?= $view->include('partials/filtro-local', [
            'id' => 'filtroInstituciones',
            'tabla' => '#tablaInstituciones',
            'placeholder' => 'Buscar institucion...',
            'ayuda' => 'Buscar instituciones financieras por nombre, tipo o estado',
        ]) ?>
        <?php endif ?>
    </div>
    <?php if ($items === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-bank2"></i></div>
            <h3>No hay instituciones registradas</h3>
            <p>Agrega la primera institución financiera del catálogo.</p>
            <a href="<?= $view->url('/configuracion/instituciones-financieras/nueva') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-plus-lg"></i>Nueva institución
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table" id="tablaInstituciones">
            <thead>
                <tr>
                    <th style="width: 70px;">Orden</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it):
                    $tipo = TipoInstitucion::tryFrom((string) $it['tipo']);
                    $tone = $tipoTone[$it['tipo']] ?? 'tone-muted';
                ?>
                <tr>
                    <td class="ds-cell-mono"><?= (int) $it['orden'] ?></td>
                    <td><span class="ds-cell-name"><?= $e((string) $it['nombre']) ?></span></td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e($tipo?->label() ?? (string) $it['tipo']) ?></span></td>
                    <td>
                        <?php if ((int) $it['activo'] === 1): ?>
                        <span class="ds-badge tone-success">Activa</span>
                        <?php else: ?>
                        <span class="ds-badge tone-warning">Inactiva</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/configuracion/instituciones-financieras/' . $it['id'] . '/editar') ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Editar">Editar</a>
                            <form method="post" class="d-inline"
                                  action="<?= $view->url('/configuracion/instituciones-financieras/' . $it['id'] . '/eliminar') ?>"
                                  data-confirm
                                  data-confirm-title="Eliminar institucion"
                                  data-confirm-text="Eliminar <?= $e((string) $it['nombre']) ?> del catalogo?"
                                  data-confirm-type="danger"
                                  data-confirm-button="Si, eliminar">
                                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                <button type="submit" class="ds-btn ds-btn-outline ds-btn-sm" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
