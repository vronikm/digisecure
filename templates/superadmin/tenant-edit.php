<?php
/**
 * Edicion de tenant global (superadmin).
 *
 * Variables: $csrfToken, $tenant, $planes, $input
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$in = is_array($input ?? null) ? $input : [];
$tenant = is_array($tenant ?? null) ? $tenant : [];
$v = static fn(string $key, string $default = ''): string => (string) ($in[$key] ?? $tenant[$key] ?? $default);
?>

<?php $view->section('title') ?>Editar tenant | Superadmin<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/admin/tenants') ?>">Tenants</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/admin/tenants/' . $tenant['id']) ?>"><?= $e((string) ($tenant['razon_social'] ?? 'Tenant')) ?></a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar tenant</h1>
            <small><?= $e((string) ($tenant['razon_social'] ?? '')) ?> &middot; <span class="ds-cell-mono">RUC <?= $e((string) ($tenant['ruc'] ?? '')) ?></span></small>
        </div>
    </div>
    <a href="<?= $view->url('/admin/tenants/' . $tenant['id']) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al detalle
    </a>
</div>

<form class="ds-form-main" method="post" action="<?= $view->url('/admin/tenants/' . $tenant['id'] . '/editar') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-building"></i></span>
            <div>
                <div class="ds-card-title">Identidad y contacto</div>
                <div class="ds-card-subtitle">Campos globales del tenant</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Razon social <span class="ds-required">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required maxlength="255" value="<?= $e($v('razon_social')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">RUC</label>
                        <input type="text" class="form-control" value="<?= $e($v('ruc')) ?>" disabled>
                        <span class="ds-form-help">Por seguridad no se edita desde esta pantalla.</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Pais</label>
                        <input type="text" name="pais" class="form-control" maxlength="2" value="<?= $e($v('pais', 'EC')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control" maxlength="255" value="<?= $e($v('nombre_comercial')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email contacto <span class="ds-required">*</span></label>
                        <input type="email" name="email_contacto" class="form-control" required maxlength="190" value="<?= $e($v('email_contacto')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="30" value="<?= $e($v('telefono')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" maxlength="100" value="<?= $e($v('ciudad')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-control" maxlength="100" value="<?= $e($v('provincia')) ?>">
                    </div>
                </div>
                <div class="col-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Direccion</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255" value="<?= $e($v('direccion')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-gem"></i></span>
            <div>
                <div class="ds-card-title">Plan y limites</div>
                <div class="ds-card-subtitle">Parametros de capacidad y vigencia</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Plan</label>
                        <?php $planSel = $v('plan_codigo', 'trial'); ?>
                        <select name="plan_codigo" class="form-select">
                            <?php foreach ($planes as $p): ?>
                            <option value="<?= $e($p->codigo) ?>" <?= $planSel === $p->codigo ? 'selected' : '' ?>>
                                <?= $e($p->nombre) ?> (<?= $e($p->codigo) ?>)
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Inicio plan</label>
                        <input type="date" name="plan_inicio" class="form-control" value="<?= $e($v('plan_inicio')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Vencimiento plan</label>
                        <input type="date" name="plan_vencimiento" class="form-control" value="<?= $e($v('plan_vencimiento')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max usuarios</label>
                        <input type="number" name="max_usuarios" min="1" class="form-control" value="<?= $e($v('max_usuarios', '5')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max guardias</label>
                        <input type="number" name="max_guardias" min="1" class="form-control" value="<?= $e($v('max_guardias', '50')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max storage (MB)</label>
                        <input type="number" name="max_storage_mb" min="128" class="form-control" value="<?= $e($v('max_storage_mb', '1024')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Timezone</label>
                        <input type="text" name="timezone" class="form-control" value="<?= $e($v('timezone', 'America/Guayaquil')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Locale</label>
                        <input type="text" name="locale" class="form-control" value="<?= $e($v('locale', 'es_EC')) ?>">
                    </div>
                </div>
                <div class="col-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="3"><?= $e($v('notas')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/admin/tenants/' . $tenant['id']) ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>
<?php $view->endSection() ?>
