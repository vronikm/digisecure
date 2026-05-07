<?php
/**
 * Formulario de alta de asignacion guardia <-> puesto.
 *
 * Variables: $user, $csrfToken, $currentPath, $fichas, $puestos, $input
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$old = static fn(string $k, string $d = ''): string => (string) ($input[$k] ?? $d);
?>

<?php $view->section('title') ?>Nueva asignacion — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/asignaciones') ?>">Asignaciones</a></li>
<li class="breadcrumb-item active">Nueva</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-link-45deg"></i></span>
        <div>
            <h1>Nueva asignacion</h1>
            <small>Vincula un guardia con ficha COMPLETA a un puesto activo durante un periodo</small>
        </div>
    </div>
    <a href="<?= $view->url('/asignaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo crear la asignacion</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form method="post" action="<?= $view->url('/asignaciones/crear') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-person-badge"></i></span>
            <div>
                <div class="ds-card-title">Guardia y puesto</div>
                <div class="ds-card-subtitle">Solo aparecen guardias COMPLETAS y puestos activos</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Guardia (ficha) <span class="ds-required">*</span></label>
                        <select name="ficha_id" class="form-select" required>
                            <option value="">— Seleccione un guardia —</option>
                            <?php foreach ($fichas as $f): ?>
                            <option value="<?= $e($f['id']) ?>" <?= $old('ficha_id') === $f['id'] ? 'selected' : '' ?>>
                                <?= $e($f['label']) ?> &middot; <?= $e($f['correlativo']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                        <span class="ds-form-help">Solo guardias con ficha en estado COMPLETA.</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Puesto <span class="ds-required">*</span></label>
                        <select name="puesto_id" class="form-select" required>
                            <option value="">— Seleccione un puesto —</option>
                            <?php foreach ($puestos as $p):
                                $disponibles = max(0, $p['plazas'] - $p['ocupadas']);
                                $disabled = $disponibles === 0;
                            ?>
                            <option value="<?= $e($p['id']) ?>"
                                    <?= $old('puesto_id') === $p['id'] ? 'selected' : '' ?>
                                    <?= $disabled ? 'disabled' : '' ?>>
                                <?= $e($p['nombre']) ?> &middot; <?= $e($p['cliente']) ?>
                                &middot; <?= $disponibles ?>/<?= $p['plazas'] ?> plazas libres<?= $disabled ? ' (cubierto)' : '' ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                        <span class="ds-form-help">Solo puestos activos con plazas disponibles.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-calendar-range"></i></span>
            <div>
                <div class="ds-card-title">Periodo</div>
                <div class="ds-card-subtitle">Fecha de inicio obligatoria. Si no hay fin, queda vigente.</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha de inicio <span class="ds-required">*</span></label>
                        <input type="date" name="fecha_inicio" class="form-control" required
                               value="<?= $e($old('fecha_inicio', date('Y-m-d'))) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha de fin (opcional)</label>
                        <input type="date" name="fecha_fin" class="form-control"
                               value="<?= $e($old('fecha_fin')) ?>">
                        <span class="ds-form-help">Dejar vacio si la asignacion es indefinida.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-stickies"></i></span>
            <div>
                <div class="ds-card-title">Detalles operativos</div>
                <div class="ds-card-subtitle">Rol en el sitio y notas</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Rol en el sitio</label>
                        <input type="text" name="rol_en_puesto" class="form-control" maxlength="80"
                               placeholder="Ej: Guardia A, Supervisor turno noche, Reten..."
                               value="<?= $e($old('rol_en_puesto')) ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="3"
                                  placeholder="Detalles, observaciones, instrucciones especificas..."><?= $e($old('notas')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/asignaciones') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Crear asignacion
        </button>
    </div>
</form>
<?php $view->endSection() ?>
