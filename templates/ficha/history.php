<?php
/**
 * Historial de fichas por guardia.
 *
 * Variables: $user, $csrfToken, $currentPath, $guardia, $fichas, $filters?, $exportPath?
 */

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$filters = is_array($filters ?? null) ? $filters : [];
$estadoActual = (string) ($filters['estado'] ?? '');
$tipoActual = (string) ($filters['tipo_ficha'] ?? '');
$desdeActual = (string) ($filters['desde'] ?? '');
$hastaActual = (string) ($filters['hasta'] ?? '');
$hasFilters = $estadoActual !== '' || $tipoActual !== '' || $desdeActual !== '' || $hastaActual !== '';

$estadoTone = [
    'BORRADOR' => 'tone-muted',
    'EN_REVISION' => 'tone-warning',
    'COMPLETA' => 'tone-success',
    'ANULADA' => 'tone-danger',
];
?>

<?php $view->section('title') ?>Fichas del guardia - DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-company-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .7rem;
    }
    .ds-company-item {
        grid-column: span 6;
        padding: .7rem .8rem;
        border: 1px solid rgba(21, 48, 79, 0.09);
        border-radius: 12px;
        background: linear-gradient(180deg, #fcfdff, #f7f9fc);
        min-width: 0;
    }
    .ds-company-item.span-3 { grid-column: span 3; }
    .ds-company-item.span-4 { grid-column: span 4; }
    .ds-company-item.span-6 { grid-column: span 6; }
    .ds-company-item.span-8 { grid-column: span 8; }
    .ds-company-item.span-12 { grid-column: span 12; }
    .ds-company-label {
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ds-text-soft);
        margin-bottom: .2rem;
        line-height: 1.2;
    }
    .ds-company-value {
        font-size: .93rem;
        font-weight: 600;
        color: var(--ds-text);
        line-height: 1.3;
    }
    .ds-company-value.break {
        white-space: normal;
        word-break: break-word;
    }
    .ds-company-item .ds-cell-mono {
        font-size: .93rem;
        line-height: 1.3;
        color: var(--ds-text);
    }
    .ds-history-filter .ds-filter-bar {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }
    .ds-history-filter .ds-filter-row {
        padding: 0;
    }
    @media (max-width: 1199.98px) {
        .ds-company-item.span-3 { grid-column: span 4; }
        .ds-company-item.span-8 { grid-column: span 12; }
    }
    @media (max-width: 767.98px) {
        .ds-company-item,
        .ds-company-item.span-3,
        .ds-company-item.span-4,
        .ds-company-item.span-6,
        .ds-company-item.span-8,
        .ds-company-item.span-12 {
            grid-column: span 12;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item active">Fichas</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-collection"></i></span>
        <div>
            <h1>Fichas del guardia</h1>
            <small><?= (int) count($fichas) ?> ficha<?= count($fichas) !== 1 ? 's' : '' ?> registrada<?= count($fichas) !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <div class="ds-row-actions">
        <?php if ($user->tienePermiso('guardias.crear')): ?>
        <?php
        $createFichaUrl = $view->url('/guardias/crear');
        $queryGlue = str_contains($createFichaUrl, '?') ? '&' : '?';
        ?>
        <a href="<?= $createFichaUrl . $queryGlue . 'base_ficha=' . urlencode((string) ($guardia['id'] ?? '')) ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-plus-lg"></i>Nueva ficha
        </a>
        <?php endif ?>
        <a href="<?= $view->url((string) ($exportPath ?? '/guardias')) ?>" class="ds-btn ds-btn-outline ds-btn-sm">
            <i class="bi bi-filetype-csv"></i>Exportar CSV
        </a>
        <a href="<?= $view->url('/guardias') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver a guardias
        </a>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header">
        <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
        <div>
            <div class="ds-card-title">Informacion general</div>
            <div class="ds-card-subtitle">Datos base del guardia</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-company-grid">
            <div class="ds-company-item span-3">
                <div class="ds-company-label">Cedula</div>
                <div class="ds-cell-mono"><?= $e((string) ($guardia['cedula_display'] ?? '-')) ?></div>
            </div>
            <div class="ds-company-item span-3">
                <div class="ds-company-label">Apellidos</div>
                <div class="ds-company-value break"><?= $e((string) ($guardia['apellidos'] ?? '-')) ?></div>
            </div>
            <div class="ds-company-item span-3">
                <div class="ds-company-label">Nombres</div>
                <div class="ds-company-value break"><?= $e((string) ($guardia['nombres'] ?? '-')) ?></div>
            </div>
            <div class="ds-company-item span-3">
                <div class="ds-company-label">Fecha de nacimiento</div>
                <div class="ds-cell-mono">
                    <?php if (!empty($guardia['fecha_nacimiento'])): ?>
                        <?= date('d/m/Y', strtotime((string) $guardia['fecha_nacimiento'])) ?>
                    <?php else: ?>
                        -
                    <?php endif ?>
                </div>
            </div>
            <div class="ds-company-item span-4">
                <div class="ds-company-label">Celular</div>
                <div class="ds-cell-mono"><?= $e((string) ($guardia['celular'] ?? '-')) ?></div>
            </div>
            <div class="ds-company-item span-8">
                <div class="ds-company-label">Correo</div>
                <div class="ds-cell-mono"><?= $e((string) ($guardia['email'] ?? '-')) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="ds-card ds-history-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros del historial</div>
            <div class="ds-card-subtitle">Refina por estado, tipo y rango de fechas</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url('/guardias/' . $guardia['id'] . '/fichas') ?>" class="ds-filter-row ds-form-filter">
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (EstadoFicha::cases() as $estado): ?>
                            <option value="<?= $estado->value ?>" <?= $estadoActual === $estado->value ? 'selected' : '' ?>><?= $e($estado->label()) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo</label>
                        <select name="tipo_ficha" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (TipoFicha::cases() as $tipo): ?>
                            <option value="<?= $tipo->value ?>" <?= $tipoActual === $tipo->value ? 'selected' : '' ?>><?= $e($tipo->label()) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm" value="<?= $e($desdeActual) ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $e($hastaActual) ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-search"></i>Buscar
                    </button>
                    <?php if ($hasFilters): ?>
                    <a href="<?= $view->url('/guardias/' . $guardia['id'] . '/fichas') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                    <?php endif ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-card-title">Historial de fichas</div>
            <div class="ds-card-subtitle">Linea temporal del guardia (ingreso, reingreso y actualizaciones)</div>
        </div>
    </div>
    <?php if ($fichas === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-collection"></i></div>
            <h3>No hay fichas para mostrar</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ninguna ficha coincide con los filtros seleccionados.
                <?php else: ?>
                    Este guardia aun no tiene fichas registradas.
                <?php endif ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Correlativo</th>
                    <th>Tipo de ficha</th>
                    <th>Estado</th>
                    <th>Fecha de emision</th>
                    <th>Creada</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fichas as $f):
                    $tone = $estadoTone[$f['estado']] ?? 'tone-muted';
                    $tipo = TipoFicha::from((string) $f['tipo_ficha']);
                    $estado = EstadoFicha::from((string) $f['estado']);
                ?>
                <tr>
                    <td><span class="ds-cell-mono" style="font-weight:700;"><?= $e((string) $f['correlativo']) ?></span></td>
                    <td><span class="ds-chip"><?= $e($tipo->label()) ?></span></td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e($estado->label()) ?></span></td>
                    <td>
                        <?php if (!empty($f['fecha_emision'])): ?>
                            <span class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $f['fecha_emision'])) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($f['created_at'])): ?>
                            <span class="ds-cell-mono"><?= date('d/m/Y H:i', strtotime((string) $f['created_at'])) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/guardias/' . $f['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                                Ver
                            </a>
                            <?php if ($user->tienePermiso('guardias.editar') && in_array((string) $f['estado'], ['BORRADOR', 'EN_REVISION'], true)): ?>
                            <a href="<?= $view->url('/guardias/' . $f['id'] . '/editar') ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                                Editar
                            </a>
                            <?php endif ?>
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
