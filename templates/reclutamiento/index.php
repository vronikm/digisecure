<?php
/**
 * Panel de reclutamiento: listado de aspirantes (Fase 4).
 *
 * Variables: $user, $csrfToken, $currentPath, $aspirantes,
 *            $total, $page, $totalPages, $filters, $kpis
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = [
    'nuevo'       => 'tone-warning',
    'aspirante'   => 'tone-info',
    'en_revision' => 'tone-info',
    'contactado'  => 'tone-info',
    'aprobado'    => 'tone-info',
    'descartado'  => 'tone-muted',
    'contratado'  => 'tone-success',
];
$estadoLabel = [
    'nuevo'       => 'Nuevo',
    'aspirante'   => 'Aspirante',
    'en_revision' => 'En revisión',
    'contactado'  => 'Contactado',
    'aprobado'    => 'Aprobado',
    'descartado'  => 'Descartado',
    'contratado'  => 'Contratado',
];
$tipoLabel = ['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'];

$hasFilters = !empty($filters['q']) || (string) ($filters['estado'] ?? '') !== '' || (string) ($filters['verificado'] ?? '') !== '';
$qs = http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'estado' => $filters['estado'] ?? '',
    'verificado' => $filters['verificado'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?>Reclutamiento - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-person-plus-fill me-1"></i>Reclutamiento</li>
<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-reclutamiento-filter .ds-filter-row {
        display: grid;
        grid-template-columns: minmax(280px, 1fr) minmax(150px, 180px) minmax(150px, 180px) auto;
        gap: .75rem;
        align-items: end;
    }

    .ds-reclutamiento-filter .ds-filter-field {
        min-width: 0;
    }

    .ds-reclutamiento-filter .ds-filter-actions {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        white-space: nowrap;
        padding-bottom: .05rem;
    }

    @media (max-width: 991.98px) {
        .ds-reclutamiento-filter .ds-filter-row {
            grid-template-columns: 1fr 1fr;
        }

        .ds-reclutamiento-filter .ds-filter-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .ds-reclutamiento-filter .ds-filter-row {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-plus-fill"></i></span>
        <div>
            <h1>Reclutamiento</h1>
            <small><?= (int) $kpis['total'] ?> postulante<?= (int) $kpis['total'] !== 1 ? 's' : '' ?> registrado<?= (int) $kpis['total'] !== 1 ? 's' : '' ?></small>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-4 ds-kpi-grid-solid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-kpi-label">Total</div>
            <div class="ds-kpi-value"><?= (int) $kpis['total'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-envelope-exclamation"></i></span>
        <div>
            <div class="ds-kpi-label">Sin verificar</div>
            <div class="ds-kpi-value"><?= (int) $kpis['pendientes'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-info">
        <span class="ds-kpi-icon"><i class="bi bi-clipboard-check"></i></span>
        <div>
            <div class="ds-kpi-label">Por revisar</div>
            <div class="ds-kpi-value"><?= (int) $kpis['verificados'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-person-check"></i></span>
        <div>
            <div class="ds-kpi-label">Contratados</div>
            <div class="ds-kpi-value"><?= (int) ($kpis['contratados'] ?? $kpis['aprobados']) ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="ds-card ds-reclutamiento-filter">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros</div>
            <div class="ds-card-subtitle">Búsqueda por nombre, documento, correo y estado</div>
        </div>
    </div>
    <div class="ds-card-body">
        <form method="get" action="<?= $view->url('/reclutamiento') ?>" class="ds-filter-row ds-form-filter">
            <div class="ds-filter-field">
                <div class="ds-form-field">
                    <label class="ds-form-label">Buscar</label>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Nombre, documento, correo..." value="<?= $e((string) ($filters['q'] ?? '')) ?>">
                </div>
            </div>
            <div class="ds-filter-field">
                <div class="ds-form-field">
                    <label class="ds-form-label">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($estadoLabel as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($filters['estado'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="ds-filter-field">
                <div class="ds-form-field">
                    <label class="ds-form-label">Verificación</label>
                    <select name="verificado" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="1" <?= (string) ($filters['verificado'] ?? '') === '1' ? 'selected' : '' ?>>Verificado</option>
                        <option value="0" <?= (string) ($filters['verificado'] ?? '') === '0' ? 'selected' : '' ?>>Sin verificar</option>
                    </select>
                </div>
            </div>
            <div class="ds-filter-actions">
                <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-search"></i>Buscar</button>
                <?php if ($hasFilters): ?>
                <a href="<?= $view->url('/reclutamiento') ?>" class="ds-btn ds-btn-ghost ds-btn-sm"><i class="bi bi-x-lg"></i>Limpiar</a>
                <?php endif ?>
            </div>
        </form>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
        <div>
            <div class="ds-card-title">Aspirantes</div>
            <div class="ds-card-subtitle">Postulantes y su estado de revisión</div>
        </div>
    </div>
    <?php if ($aspirantes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-plus"></i></div>
            <h3>No hay aspirantes para mostrar</h3>
            <p><?= $hasFilters ? 'Ningún aspirante coincide con los criterios. Ajusta o limpia los filtros.' : 'Cuando alguien se registre desde la pantalla de inicio, aparecerá aquí.' ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Aspirante</th>
                    <th>Documento</th>
                    <th>Contacto</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aspirantes as $a):
                    $tone = $estadoTone[$a['estado']] ?? 'tone-muted';
                    $verificado = (int) $a['email_verificado'] === 1;
                    $fichaId = trim((string) ($a['ficha_id'] ?? ''));
                    $puedeAbrirExpediente = $fichaId !== '' && $user->tieneAlgunPermiso('expedientes.ver', 'expedientes.validar', 'expedientes.revisar');
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/reclutamiento/' . $a['id']) ?>" class="ds-cell-name" style="text-decoration: none;">
                            <?= $e(trim($a['nombres'] . ' ' . $a['apellidos'])) ?>
                        </a>
                    </td>
                    <td>
                        <span class="ds-chip"><i class="bi bi-tag"></i><?= $e($tipoLabel[$a['tipo_documento']] ?? (string) $a['tipo_documento']) ?></span>
                        <div class="ds-cell-mono" style="margin-top: .15rem;"><?= $e((string) $a['documento']) ?></div>
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
                    <td><span class="ds-badge <?= $tone ?>"><?= $e($estadoLabel[$a['estado']] ?? (string) $a['estado']) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/reclutamiento/' . $a['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm">Revisar</a>
                            <?php if (in_array((string) $a['estado'], $estadosEditables ?? [], true)): ?>
                                <a href="<?= $view->url('/reclutamiento/' . $a['id'] . '/editar') ?>" class="ds-btn ds-btn-ghost ds-btn-sm"
                                   title="Corregir datos del registro (correo, documento, nombres...)" aria-label="Editar datos de <?= $e(trim($a['nombres'] . ' ' . $a['apellidos'])) ?>">
                                    <i class="bi bi-pencil-square"></i>Editar
                                </a>
                            <?php endif ?>
                            <?php if ($puedeAbrirExpediente): ?>
                                <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                                    <i class="bi bi-folder-check"></i>Validar documentos
                                </a>
                            <?php elseif ($fichaId === ''): ?>
                                <span class="ds-btn ds-btn-ghost ds-btn-sm is-disabled" title="El aspirante aun no ha creado su ficha.">
                                    <i class="bi bi-folder-x"></i>Sin expediente
                                </span>
                            <?php else: ?>
                                <span class="ds-btn ds-btn-ghost ds-btn-sm is-disabled" title="Tu usuario necesita permiso para ver o validar expedientes.">
                                    <i class="bi bi-lock"></i>Validar documentos
                                </span>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="ds-table-foot">
        <span>Página <strong><?= $page ?></strong> de <?= $totalPages ?> &middot; <?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li><a class="ds-page <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $view->url('/reclutamiento') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>"><i class="bi bi-chevron-left"></i></a></li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li><a class="ds-page <?= $p === $page ? 'is-active' : '' ?>" href="<?= $view->url('/reclutamiento') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a></li>
            <?php endfor ?>
            <li><a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= $view->url('/reclutamiento') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>"><i class="bi bi-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
