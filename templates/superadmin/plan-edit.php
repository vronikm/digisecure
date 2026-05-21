<?php
/**
 * Edicion de plan global (superadmin).
 *
 * Variables: $csrfToken, $plan, $input
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$plan = is_array($plan ?? null) ? $plan : [];
$in = is_array($input ?? null) ? $input : [];
$v = static fn(string $key, string $default = ''): string => (string) ($in[$key] ?? $plan[$key] ?? $default);

$featuresJson = (string) ($in['caracteristicas_json'] ?? '');
if ($featuresJson === '') {
    $raw = $plan['caracteristicas'] ?? '{}';
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $featuresJson = $encoded !== false ? $encoded : $raw;
        } else {
            $featuresJson = $raw;
        }
    } else {
        $featuresJson = "{}\n";
    }
}
?>

<?php $view->section('title') ?>Editar plan | Superadmin<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/admin/planes') ?>">Planes</a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar plan</h1>
            <small><?= $e((string) ($plan['nombre'] ?? '')) ?> &middot; <span class="ds-cell-mono"><?= $e((string) ($plan['codigo'] ?? '')) ?></span></small>
        </div>
    </div>
    <a href="<?= $view->url('/admin/planes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<div class="ds-form-wrap ds-user-form-wrap">
<form class="ds-form-main" method="post" action="<?= $view->url('/admin/planes/' . urlencode((string) ($plan['codigo'] ?? '')) . '/editar') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
            <div>
                <div class="ds-card-title">Identidad del plan</div>
                <div class="ds-card-subtitle">Datos base del catalogo comercial</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-user-grid">
                <div class="ds-col-12 ds-col-md-3 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Codigo <span class="ds-required">*</span></label>
                        <input type="text" name="codigo" class="form-control" required maxlength="50"
                               value="<?= $e($v('codigo')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-3 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre <span class="ds-required">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="120"
                               value="<?= $e($v('nombre')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-3 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nivel</label>
                        <input type="number" name="nivel" class="form-control" min="0" max="255"
                               value="<?= $e($v('nivel', '0')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-3 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" min="0"
                               value="<?= $e($v('orden', '0')) ?>">
                    </div>
                </div>
                <div class="ds-col-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Descripcion</label>
                        <textarea name="descripcion" class="form-control" rows="2"><?= $e($v('descripcion')) ?></textarea>
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Visibilidad</label>
                        <?php $esPublico = $v('es_publico', '1'); ?>
                        <select name="es_publico" class="form-select">
                            <option value="1" <?= $esPublico === '1' ? 'selected' : '' ?>>Publico</option>
                            <option value="0" <?= $esPublico === '0' ? 'selected' : '' ?>>Privado</option>
                        </select>
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <?php $activo = $v('activo', '1'); ?>
                        <select name="activo" class="form-select">
                            <option value="1" <?= $activo === '1' ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= $activo === '0' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-12 ds-col-lg-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ultima actualizacion</label>
                        <input type="text" class="form-control ds-cell-mono" readonly
                               value="<?= $e(substr((string) ($plan['updated_at'] ?? ''), 0, 19)) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-currency-dollar"></i></span>
            <div>
                <div class="ds-card-title">Precios y limites</div>
                <div class="ds-card-subtitle">Capacidades operativas asignadas al plan</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-user-grid">
                <div class="ds-col-12 ds-col-md-4 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Precio mensual (USD)</label>
                        <input type="number" step="0.01" min="0" name="precio_mensual_usd" class="form-control"
                               value="<?= $e($v('precio_mensual_usd', '0.00')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-4 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Precio anual (USD)</label>
                        <input type="number" step="0.01" min="0" name="precio_anual_usd" class="form-control"
                               value="<?= $e($v('precio_anual_usd', '0.00')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-4 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max usuarios</label>
                        <input type="number" min="1" name="max_usuarios" class="form-control"
                               value="<?= $e($v('max_usuarios', '5')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max guardias</label>
                        <input type="number" min="1" name="max_guardias" class="form-control"
                               value="<?= $e($v('max_guardias', '50')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Storage (MB)</label>
                        <input type="number" min="128" name="max_storage_mb" class="form-control"
                               value="<?= $e($v('max_storage_mb', '1024')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-braces"></i></span>
            <div>
                <div class="ds-card-title">Caracteristicas (JSON)</div>
                <div class="ds-card-subtitle">Configuracion avanzada de capacidades del plan</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-user-grid">
                <div class="ds-col-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">JSON de caracteristicas</label>
                        <textarea name="caracteristicas_json" class="form-control ds-cell-mono" rows="10"><?= $e($featuresJson) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/admin/planes') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>
</div>
<?php $view->endSection() ?>
