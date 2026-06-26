<?php
/**
 * Importacion de Excel GEMESEG.
 *
 * Variables: $user, $csrfToken, $currentPath, $recentSources
 * Opcionales: $analysis, $validation, $tempToken, $source, $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$analysis = $analysis ?? null;
$validation = $validation ?? null;
$source = (string) ($source ?? 'PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx');
$totals = is_array($analysis) ? ($analysis['totals'] ?? []) : [];
?>

<?php $view->section('title') ?>Importar Excel GEMESEG - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Importar Excel GEMESEG</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-file-earmark-spreadsheet"></i></span>
        <div>
            <h1>Importar Excel GEMESEG</h1>
            <small>Analiza el archivo, valida cedulas y guarda staging cifrado para revision.</small>
        </div>
    </div>
    <a href="<?= $view->url('/guardias') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver a guardias
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.2rem;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626;">No se pudo procesar el Excel</div>
            <div class="ds-cell-sub"><?= $e((string) $error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="ds-card">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-cloud-upload"></i></span>
                <div>
                    <div class="ds-card-title">Archivo Excel</div>
                    <div class="ds-card-subtitle">Primero se analiza; luego decides si guardar staging</div>
                </div>
            </div>
            <div class="ds-card-body">
                <form method="post" action="<?= $view->url('/importaciones/gemeseg/analizar') ?>" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Seleccionar Excel <span class="ds-required">*</span></label>
                                <input type="file" name="archivo" class="form-control" accept=".xlsx" required>
                                <span class="ds-form-help">Solo .xlsx. No se sube por Git ni se guarda en public.</span>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Nombre de fuente</label>
                                <input type="text" name="source" class="form-control" maxlength="120" value="<?= $e($source) ?>">
                                <span class="ds-form-help">Se usa para evitar duplicados por hoja/fila.</span>
                            </div>
                        </div>
                    </div>
                    <div class="ds-form-actions" style="margin-top: 1rem;">
                        <button type="submit" class="ds-btn ds-btn-primary">
                            <i class="bi bi-search"></i>Analizar Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (is_array($analysis)): ?>
        <div class="ds-card" style="margin-top: 1rem;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-clipboard-data"></i></span>
                <div>
                    <div class="ds-card-title">Resultado del analisis</div>
                    <div class="ds-card-subtitle">No se muestran cedulas, nombres ni telefonos</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-2" style="margin-bottom: 1rem;">
                    <?php
                    $kpis = [
                        ['Filas', (int) ($totals['data_rows'] ?? 0), 'bi-table'],
                        ['Cedulas validas', (int) ($totals['valid_cedula'] ?? 0), 'bi-check-circle'],
                        ['Cedulas con error', (int) ($totals['invalid_cedula'] ?? 0) + (int) ($totals['missing_cedula'] ?? 0), 'bi-exclamation-triangle'],
                        ['Duplicados hoja', (int) ($totals['duplicate_cedula_in_sheet'] ?? 0), 'bi-intersect'],
                    ];
                    ?>
                    <?php foreach ($kpis as [$label, $value, $icon]): ?>
                    <div class="col-6 col-md-3">
                        <div style="border: 1px solid var(--ds-border); border-radius: 8px; padding: .8rem;">
                            <div class="ds-cell-sub"><i class="bi <?= $e($icon) ?>"></i> <?= $e($label) ?></div>
                            <div style="font-weight: 800; font-size: 1.35rem; color: var(--ds-text);"><?= (int) $value ?></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Hoja</th>
                            <th class="text-end">Filas</th>
                            <th class="text-end">Cedulas</th>
                            <th class="text-end">Validas</th>
                            <th class="text-end">Invalidas</th>
                            <th class="text-end">Duplicadas</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($analysis['sheets'] ?? []) as $sheet): ?>
                            <?php $stats = $sheet['stats'] ?? []; ?>
                            <tr>
                                <td><?= $e((string) $sheet['name']) ?></td>
                                <td class="text-end"><?= (int) ($stats['data_rows'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($stats['with_cedula'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($stats['valid_cedula'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($stats['invalid_cedula'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($stats['duplicate_cedula_in_sheet'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <?php if (is_array($validation)): ?>
                <div class="login-info" style="margin: 1rem 0;">
                    <i class="bi bi-database-check"></i>
                    <div>
                        <strong>Validacion BD lista</strong>
                        Ya existen <?= (int) ($validation['existing_fichas'] ?? 0) ?> cedula(s) del Excel en fichas y <?= (int) ($validation['existing_staging'] ?? 0) ?> fila(s) de esta fuente en staging.
                    </div>
                </div>
                <?php endif ?>

                <form method="post" action="<?= $view->url('/importaciones/gemeseg/aplicar') ?>" style="margin-top: 1rem;">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="temp_token" value="<?= $e((string) ($tempToken ?? '')) ?>">
                    <input type="hidden" name="source" value="<?= $e($source) ?>">
                    <label style="display: flex; gap: .55rem; align-items: flex-start; margin-bottom: 1rem;">
                        <input type="checkbox" name="confirmar" value="1" required style="margin-top: .25rem;">
                        <span class="ds-cell-sub">Confirmo que el archivo corresponde al tenant actual y autorizo guardar sus filas en staging cifrado para revision.</span>
                    </label>
                    <button type="submit" class="ds-btn ds-btn-primary">
                        <i class="bi bi-lock-fill"></i>Guardar staging cifrado
                    </button>
                </form>
            </div>
        </div>
        <?php endif ?>
    </div>

    <div class="col-lg-5">
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-shield-lock"></i></span>
                <div>
                    <div class="ds-card-title">Seguridad y LOPDP</div>
                    <div class="ds-card-subtitle">La importacion queda limitada a staging</div>
                </div>
            </div>
            <div class="ds-card-body">
                <ul style="margin: 0; padding-left: 1.1rem; color: var(--ds-text-soft); font-size: .86rem; line-height: 1.65;">
                    <li>El Excel temporal se guarda fuera del webroot.</li>
                    <li>Al aplicar, cada fila queda cifrada por tenant.</li>
                    <li>Los errores de cedula quedan marcados para revision.</li>
                    <li>El procesamiento convierte staging en fichas, cursos, vacunas y marcadores documentales.</li>
                    <li>Los documentos detectados quedan sin archivo adjunto hasta subir la evidencia.</li>
                </ul>
            </div>
        </div>

        <div class="ds-card" style="margin-top: 1rem;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
                <div>
                    <div class="ds-card-title">Staging reciente</div>
                    <div class="ds-card-subtitle">Fuentes cargadas para este tenant</div>
                </div>
            </div>
            <div class="ds-card-body">
                <?php if (empty($recentSources)): ?>
                    <div class="ds-empty-state" style="padding: 1rem 0;">No hay importaciones en staging.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Fuente</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Pend.</th>
                                <th class="text-end">Proc.</th>
                                <th class="text-end">Omit.</th>
                                <th class="text-end">Errores</th>
                                <th class="text-end">Accion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentSources as $row): ?>
                            <?php $pendientes = (int) ($row['pendientes'] ?? 0); ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--ds-text);"><?= $e((string) $row['fuente']) ?></div>
                                    <div class="ds-cell-sub"><?= $e((string) ($row['ultimo_at'] ?? '')) ?></div>
                                </td>
                                <td class="text-end"><?= (int) $row['total'] ?></td>
                                <td class="text-end"><?= $pendientes ?></td>
                                <td class="text-end"><?= (int) ($row['procesados'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($row['omitidos'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) $row['errores'] ?></td>
                                <td class="text-end">
                                    <?php if ($pendientes > 0): ?>
                                    <form method="post" action="<?= $view->url('/importaciones/gemeseg/procesar') ?>" style="display:grid; gap:.35rem; justify-items:end;">
                                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                        <input type="hidden" name="source" value="<?= $e((string) $row['fuente']) ?>">
                                        <label class="ds-cell-sub" style="display:flex; gap:.35rem; align-items:center; margin:0;">
                                            <input type="checkbox" name="confirmar_procesar" value="1" required>
                                            Confirmar
                                        </label>
                                        <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                                            <i class="bi bi-play-fill"></i>Procesar
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="ds-badge tone-success">Listo</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
