<?php
/**
 * Catálogo de tipos de documento del tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $tipos, $kpis, $bloqueFilter
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Tipos de documento — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/configuracion/documentos') ?>">Configuración</a></li>
<li class="breadcrumb-item active">Tipos de documento</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-ruled me-2"></i>Tipos de documento</h4>
    <span class="text-muted small">Catálogo configurado para su empresa</span>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-primary border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Activos</div>
                <div class="fs-4 fw-bold text-primary"><?= $kpis['activos'] ?> <span class="fs-6 fw-normal text-muted">/ <?= $kpis['total'] ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-info border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Bloque A (Pre-empleo)</div>
                <div class="fs-4 fw-bold text-info"><?= $kpis['bloque_a'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-warning border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Bloque B (Post-vincul.)</div>
                <div class="fs-4 fw-bold text-warning"><?= $kpis['bloque_b'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-danger border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Con vencimiento</div>
                <div class="fs-4 fw-bold text-danger"><?= $kpis['con_vencimiento'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtro de bloque -->
<div class="d-flex gap-2 mb-3">
    <a href="<?= $view->url('/configuracion/documentos') ?>"
       class="btn btn-sm <?= $bloqueFilter === null ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
    <a href="<?= $view->url('/configuracion/documentos?bloque=A') ?>"
       class="btn btn-sm <?= $bloqueFilter === 'A' ? 'btn-info' : 'btn-outline-info' ?>">Bloque A — Pre-empleo</a>
    <a href="<?= $view->url('/configuracion/documentos?bloque=B') ?>"
       class="btn btn-sm <?= $bloqueFilter === 'B' ? 'btn-warning' : 'btn-outline-warning' ?>">Bloque B — Post-vinculación</a>
</div>

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>
    El catálogo base viene del sistema. Su empresa puede <strong>activar/desactivar</strong> tipos,
    ajustar <strong>vigencias</strong> y cambiar la <strong>obligatoriedad</strong>. Los cambios solo afectan a su empresa.
</div>

<!-- Tabla -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">#</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th class="text-center">Bloque</th>
                    <th class="text-center">Oblig.</th>
                    <th class="text-center">Vigencia</th>
                    <th class="text-center">Días</th>
                    <th class="text-center">Activo</th>
                    <th class="text-center">Override</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tipos)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay tipos de documento configurados.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tipos as $t): ?>
                <?php $esActivo = (int)$t['activo'] === 1; ?>
                <?php $esOverride = $t['tenant_id'] !== null; ?>
                <tr class="<?= $esActivo ? '' : 'table-secondary text-muted' ?>">
                    <td class="small"><?= (int)$t['orden'] ?></td>
                    <td><code class="small"><?= $e((string)$t['codigo']) ?></code></td>
                    <td class="<?= $esActivo ? '' : 'text-decoration-line-through' ?>"><?= $e((string)$t['nombre']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $t['bloque'] === 'A' ? 'bg-info' : 'bg-warning text-dark' ?>"><?= $e((string)$t['bloque']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ((int)$t['obligatorio'] === 1): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-dash-circle text-muted"></i>
                        <?php endif ?>
                    </td>
                    <td class="text-center">
                        <?php if ((int)$t['tiene_vencimiento'] === 1): ?>
                            <i class="bi bi-clock-fill text-warning"></i>
                        <?php else: ?>
                            <i class="bi bi-infinity text-muted"></i>
                        <?php endif ?>
                    </td>
                    <td class="text-center small">
                        <?php if ((int)$t['tiene_vencimiento'] === 1 && $t['dias_vigencia'] !== null): ?>
                            <?= (int)$t['dias_vigencia'] ?>d
                        <?php else: ?>
                            —
                        <?php endif ?>
                    </td>
                    <td class="text-center">
                        <form method="post" action="<?= $view->url('/configuracion/documentos/' . $e((string)$t['codigo']) . '/toggle') ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" title="<?= $esActivo ? 'Desactivar' : 'Activar' ?>">
                                <?php if ($esActivo): ?>
                                    <i class="bi bi-toggle-on fs-5 text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-toggle-off fs-5 text-secondary"></i>
                                <?php endif ?>
                            </button>
                        </form>
                    </td>
                    <td class="text-center">
                        <?php if ($esOverride): ?>
                            <span class="badge bg-primary" title="Configuración propia de su empresa">Propio</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border">Sistema</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <a href="<?= $view->url('/configuracion/documentos/' . $e((string)$t['codigo'])) ?>"
                           class="btn btn-outline-primary btn-sm py-0 px-2" title="Configurar">
                            <i class="bi bi-gear"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach ?>
            <?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?php $view->endSection() ?>
