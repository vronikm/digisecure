<?php
/**
 * Edicion de asignacion (solo fechas, rol y notas).
 *
 * Variables: $user, $csrfToken, $currentPath, $asignacion, $input
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$valor = static fn(string $k, string $d = ''): string => (string) ($input[$k] ?? $asignacion[$k] ?? $d);
?>

<?php $view->section('title') ?>Editar asignacion — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/asignaciones') ?>">Asignaciones</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/asignaciones/' . $asignacion['id']) ?>">Detalle</a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar asignacion</h1>
            <small><?= $e((string) $asignacion['ficha_apellidos']) ?> <?= $e((string) $asignacion['ficha_nombres']) ?> &mdash; <?= $e((string) $asignacion['puesto_nombre']) ?></small>
        </div>
    </div>
    <a href="<?= $view->url('/asignaciones/' . $asignacion['id']) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al detalle
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudieron guardar los cambios</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Solo campos no criticos</strong>
        Para cambiar el guardia o el puesto, finaliza esta asignacion y crea una nueva.
    </div>
</div>

<form class="ds-form-main" method="post" action="<?= $view->url('/asignaciones/' . $asignacion['id'] . '/editar') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-calendar-range"></i></span>
            <div>
                <div class="ds-card-title">Periodo</div>
                <div class="ds-card-subtitle">Ajusta inicio y fin de la asignacion</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha de inicio <span class="ds-required">*</span></label>
                        <input type="date" name="fecha_inicio" class="form-control" required
                               value="<?= $e($valor('fecha_inicio')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha de fin (opcional)</label>
                        <input type="date" name="fecha_fin" class="form-control"
                               value="<?= $e($valor('fecha_fin')) ?>">
                        <span class="ds-form-help">Vacio = vigente sin fecha definida.</span>
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
                <div class="ds-card-subtitle">Rol y notas</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Rol en el sitio</label>
                        <input type="text" name="rol_en_puesto" class="form-control" maxlength="80"
                               value="<?= $e($valor('rol_en_puesto')) ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="3"><?= $e($valor('notas')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/asignaciones/' . $asignacion['id']) ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>
<?php $view->endSection() ?>
