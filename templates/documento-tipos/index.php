<?php
/**
 * Catalogo de tipos de documento del tenant.
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
<li class="breadcrumb-item"><a href="<?= $view->url('/configuracion/documentos') ?>">Configuracion</a></li>
<li class="breadcrumb-item active">Tipos de documento</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-file-earmark-ruled"></i></span>
        <div>
            <h1>Tipos de documento</h1>
            <small>Catalogo de documentos exigidos por su empresa</small>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-toggle-on"></i></span>
        <div>
            <div class="ds-kpi-label">Activos</div>
            <div class="ds-kpi-value"><?= $kpis['activos'] ?> <span style="font-size:.95rem;font-weight:500;color:var(--ds-text-soft);">/ <?= $kpis['total'] ?></span></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-info">
        <span class="ds-kpi-icon"><i class="bi bi-1-square"></i></span>
        <div>
            <div class="ds-kpi-label">Bloque A &middot; Pre-empleo</div>
            <div class="ds-kpi-value"><?= $kpis['bloque_a'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-2-square"></i></span>
        <div>
            <div class="ds-kpi-label">Bloque B &middot; Post-vinculacion</div>
            <div class="ds-kpi-value"><?= $kpis['bloque_b'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-danger">
        <span class="ds-kpi-icon"><i class="bi bi-clock-history"></i></span>
        <div>
            <div class="ds-kpi-label">Con vencimiento</div>
            <div class="ds-kpi-value"><?= $kpis['con_vencimiento'] ?></div>
        </div>
    </div>
</div>

<!-- Filtro de bloque -->
<div class="ds-filter-bar" style="display: flex; flex-direction: row; align-items: center; gap: .75rem; flex-wrap: wrap; padding: .85rem 1.1rem;">
    <div class="ds-filter-summary">
        <i class="bi bi-funnel"></i>
        <strong>Filtrar por bloque:</strong>
    </div>
    <a href="<?= $view->url('/configuracion/documentos') ?>"
       class="ds-btn ds-btn-sm <?= $bloqueFilter === null ? 'ds-btn-primary' : 'ds-btn-outline' ?>">
        Todos
    </a>
    <a href="<?= $view->url('/configuracion/documentos?bloque=A') ?>"
       class="ds-btn ds-btn-sm <?= $bloqueFilter === 'A' ? 'ds-btn-primary' : 'ds-btn-outline' ?>">
        <i class="bi bi-1-square"></i>Bloque A &middot; Pre-empleo
    </a>
    <a href="<?= $view->url('/configuracion/documentos?bloque=B') ?>"
       class="ds-btn ds-btn-sm <?= $bloqueFilter === 'B' ? 'ds-btn-primary' : 'ds-btn-outline' ?>">
        <i class="bi bi-2-square"></i>Bloque B &middot; Post-vinculacion
    </a>
</div>

<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Personalizable por empresa</strong>
        El catalogo base viene del sistema. Tu empresa puede activar/desactivar tipos,
        ajustar vigencias y cambiar la obligatoriedad. Los cambios solo afectan a tu empresa.
    </div>
</div>

<?php if (empty($tipos)): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-files"></i></div>
        <h3>No hay tipos configurados</h3>
        <p>El catalogo se construye desde la base del sistema. Si no ves tipos, contacta a soporte.</p>
    </div>
</div>
<?php else: ?>
<div class="ds-table-card">
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Codigo</th>
                    <th>Nombre</th>
                    <th class="ds-cell-end">Bloque</th>
                    <th class="ds-cell-end">Oblig.</th>
                    <th class="ds-cell-end">Vigencia</th>
                    <th class="ds-cell-end">Dias</th>
                    <th class="ds-cell-end">Activo</th>
                    <th class="ds-cell-end">Origen</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tipos as $t):
                    $esActivo = (int) $t['activo'] === 1;
                    $esOverride = $t['tenant_id'] !== null;
                    $bloqueTone = $t['bloque'] === 'A' ? 'tone-info' : 'tone-warning';
                ?>
                <tr <?= $esActivo ? '' : 'style="opacity:.55;"' ?>>
                    <td><span class="ds-cell-sub"><?= (int) $t['orden'] ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $t['codigo']) ?></span></td>
                    <td>
                        <div class="ds-cell-name" <?= $esActivo ? '' : 'style="text-decoration:line-through;"' ?>>
                            <?= $e((string) $t['nombre']) ?>
                        </div>
                    </td>
                    <td class="ds-cell-end">
                        <span class="ds-badge <?= $bloqueTone ?> no-dot">Bloque <?= $e((string) $t['bloque']) ?></span>
                    </td>
                    <td class="ds-cell-end">
                        <?php if ((int) $t['obligatorio'] === 1): ?>
                            <i class="bi bi-check-circle-fill" style="color:#059669;"></i>
                        <?php else: ?>
                            <i class="bi bi-dash-circle" style="color:#94a3b8;"></i>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <?php if ((int) $t['tiene_vencimiento'] === 1): ?>
                            <i class="bi bi-clock-fill" style="color:#d97706;"></i>
                        <?php else: ?>
                            <i class="bi bi-infinity" style="color:#94a3b8;"></i>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <?php if ((int) $t['tiene_vencimiento'] === 1 && $t['dias_vigencia'] !== null): ?>
                            <span class="ds-cell-mono"><?= (int) $t['dias_vigencia'] ?>d</span>
                        <?php else: ?>
                            <span class="ds-cell-sub">—</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <form method="post" action="<?= $view->url('/configuracion/documentos/' . $e((string) $t['codigo']) . '/toggle') ?>" class="ds-form-inline" style="display:inline-block;">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <button type="submit" class="ds-icon-btn <?= $esActivo ? 'tone-success' : '' ?>"
                                    title="<?= $esActivo ? 'Desactivar' : 'Activar' ?>">
                                <i class="bi <?= $esActivo ? 'bi-toggle-on' : 'bi-toggle-off' ?>" style="font-size:1.1rem;"></i>
                            </button>
                        </form>
                    </td>
                    <td class="ds-cell-end">
                        <?php if ($esOverride): ?>
                            <span class="ds-badge tone-info no-dot" title="Configuracion propia de tu empresa">Propio</span>
                        <?php else: ?>
                            <span class="ds-badge tone-muted no-dot">Sistema</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/configuracion/documentos/' . $e((string) $t['codigo'])) ?>"
                               class="ds-icon-btn" title="Configurar">
                                <i class="bi bi-gear"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>
<?php $view->endSection() ?>
