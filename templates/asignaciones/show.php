<?php
/**
 * Detalle de una asignacion guardia <-> puesto.
 *
 * Variables: $user, $csrfToken, $currentPath, $asignacion
 */

use DigiSecure\Asignacion\Domain\Enum\EstadoAsignacion;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estado = EstadoAsignacion::tryFrom((string) $asignacion['estado']);
$tone = $estado ? $estado->tone() : 'tone-muted';
$puedeCerrar = $estado === EstadoAsignacion::ACTIVA;
?>

<?php $view->section('title') ?>Asignacion - DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-detail-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .7rem;
    }

    .ds-detail-item {
        grid-column: span 6;
        padding: .72rem .82rem;
        border: 1px solid rgba(21, 48, 79, 0.09);
        border-radius: 12px;
        background: linear-gradient(180deg, #fcfdff, #f7f9fc);
        min-width: 0;
    }

    .ds-detail-item.span-4 { grid-column: span 4; }
    .ds-detail-item.span-6 { grid-column: span 6; }
    .ds-detail-item.span-8 { grid-column: span 8; }
    .ds-detail-item.span-12 { grid-column: span 12; }

    .ds-detail-label {
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ds-text-soft);
        margin-bottom: .2rem;
        line-height: 1.2;
    }

    .ds-detail-value {
        font-size: .93rem;
        font-weight: 600;
        color: var(--ds-text);
        line-height: 1.3;
    }

    .ds-detail-value.break {
        white-space: normal;
        word-break: break-word;
    }

    @media (max-width: 1199.98px) {
        .ds-detail-item.span-8 { grid-column: span 12; }
    }

    @media (max-width: 767.98px) {
        .ds-detail-item,
        .ds-detail-item.span-4,
        .ds-detail-item.span-6,
        .ds-detail-item.span-8,
        .ds-detail-item.span-12 {
            grid-column: span 12;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/asignaciones') ?>">Asignaciones</a></li>
<li class="breadcrumb-item active">Detalle</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-link-45deg"></i></span>
        <div>
            <h1><?= $e((string) $asignacion['ficha_apellidos']) ?> <?= $e((string) $asignacion['ficha_nombres']) ?></h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-cell-mono"><?= $e((string) $asignacion['ficha_correlativo']) ?></span>
                <i class="bi bi-arrow-right" style="color: var(--ds-text-soft);"></i>
                <strong style="color: var(--ds-text);"><?= $e((string) $asignacion['puesto_nombre']) ?></strong>
                <span class="ds-badge <?= $tone ?>"><?= $estado ? $e($estado->label()) : $e((string) $asignacion['estado']) ?></span>
            </small>
        </div>
    </div>
    <div style="display: flex; gap: .5rem;">
        <?php if ($puedeCerrar && $user->tienePermiso('asignaciones.editar')): ?>
        <a href="<?= $view->url('/asignaciones/' . $asignacion['id'] . '/editar') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-pencil"></i>Editar
        </a>
        <?php endif ?>
        <a href="<?= $view->url('/asignaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-person-badge"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Guardia</div>
                        <div class="ds-card-subtitle">Ficha asignada al puesto</div>
                    </div>
                    <a href="<?= $view->url('/guardias/' . $asignacion['ficha_id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                        <i class="bi bi-arrow-up-right"></i>Ver ficha
                    </a>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-detail-grid">
                    <div class="ds-detail-item span-8">
                        <div class="ds-detail-label">Nombre completo</div>
                        <div class="ds-detail-value"><?= $e((string) $asignacion['ficha_apellidos']) ?> <?= $e((string) $asignacion['ficha_nombres']) ?></div>
                    </div>
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Correlativo</div>
                        <div class="ds-cell-mono"><?= $e((string) $asignacion['ficha_correlativo']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Puesto / Sitio</div>
                        <div class="ds-card-subtitle">Ubicacion donde se presta el servicio</div>
                    </div>
                    <a href="<?= $view->url('/puestos/' . $asignacion['puesto_id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                        <i class="bi bi-arrow-up-right"></i>Ver puesto
                    </a>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-detail-grid">
                    <div class="ds-detail-item span-8">
                        <div class="ds-detail-label">Puesto</div>
                        <div class="ds-detail-value"><?= $e((string) $asignacion['puesto_nombre']) ?></div>
                    </div>
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Codigo</div>
                        <div class="ds-cell-mono"><?= $e((string) ($asignacion['puesto_codigo'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-detail-item span-12">
                        <div class="ds-detail-label">Cliente</div>
                        <div><span class="ds-chip"><i class="bi bi-briefcase"></i><?= $e((string) $asignacion['cliente_razon_social']) ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-calendar-range"></i></span>
                <div>
                    <div class="ds-card-title">Periodo y rol</div>
                    <div class="ds-card-subtitle">Detalles operativos de la asignacion</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-detail-grid">
                    <div class="ds-detail-item span-6">
                        <div class="ds-detail-label">Fecha de inicio</div>
                        <div class="ds-cell-mono" style="font-weight: 700;"><?= date('d/m/Y', strtotime((string) $asignacion['fecha_inicio'])) ?></div>
                    </div>
                    <div class="ds-detail-item span-6">
                        <div class="ds-detail-label">Fecha de fin</div>
                        <?php if (!empty($asignacion['fecha_fin'])): ?>
                        <div class="ds-cell-mono" style="font-weight: 700;"><?= date('d/m/Y', strtotime((string) $asignacion['fecha_fin'])) ?></div>
                        <?php else: ?>
                        <div><span class="ds-badge tone-success no-dot">Vigente</span></div>
                        <?php endif ?>
                    </div>
                    <div class="ds-detail-item span-12">
                        <div class="ds-detail-label">Rol en el sitio</div>
                        <?php if (!empty($asignacion['rol_en_puesto'])): ?>
                        <div><span class="ds-chip"><?= $e((string) $asignacion['rol_en_puesto']) ?></span></div>
                        <?php else: ?>
                        <div class="ds-cell-sub">Sin rol especifico</div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($asignacion['notas'])): ?>
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-sticky"></i></span>
                <div>
                    <div class="ds-card-title">Notas</div>
                    <div class="ds-card-subtitle">Detalles operativos internos</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="white-space: pre-wrap; color: var(--ds-text); font-size: .92rem; line-height: 1.55;"><?= $e((string) $asignacion['notas']) ?></div>
            </div>
        </div>
        <?php endif ?>

        <?php if (!empty($asignacion['motivo_fin'])): ?>
        <div class="ds-card" style="border-left: 4px solid #64748b;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-x-octagon"></i></span>
                <div>
                    <div class="ds-card-title">Motivo del cierre</div>
                    <div class="ds-card-subtitle">Cerrada el <span class="ds-cell-mono"><?= $e(substr((string) $asignacion['finalizada_at'], 0, 16)) ?></span></div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="white-space: pre-wrap; color: var(--ds-text); font-size: .92rem; line-height: 1.55;"><?= $e((string) $asignacion['motivo_fin']) ?></div>
            </div>
        </div>
        <?php endif ?>
    </div>

    <div class="col-lg-4">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="ds-card-title">Estado actual</div>
                    <div class="ds-card-subtitle">Ciclo de vida</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="text-align: center; padding: 1rem 0;">
                    <span class="ds-badge <?= $tone ?>" style="font-size: .9rem; padding: .35rem .8rem;"><?= $estado ? $e($estado->label()) : $e((string) $asignacion['estado']) ?></span>
                </div>
                <div class="ds-detail-label">Creada el</div>
                <div class="ds-cell-mono"><?= $e(substr((string) $asignacion['created_at'], 0, 16)) ?></div>
            </div>
        </div>

        <?php if ($puedeCerrar && $user->tienePermiso('asignaciones.finalizar')): ?>
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-x-square"></i></span>
                <div>
                    <div class="ds-card-title">Cerrar asignacion</div>
                    <div class="ds-card-subtitle">Finalizar (cierre normal) o cancelar</div>
                </div>
            </div>
            <div class="ds-card-body">
                <ul class="nav nav-tabs nav-fill" style="margin-bottom: 1rem; border: 0; gap: .35rem;">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-finalizar"
                           style="border-radius: 8px; font-weight: 600; font-size: .85rem; color: var(--ds-text-soft);">Finalizar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-cancelar"
                           style="border-radius: 8px; font-weight: 600; font-size: .85rem; color: var(--ds-text-soft);">Cancelar</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-finalizar">
                        <form class="ds-form-main" method="post" action="<?= $view->url('/asignaciones/' . $asignacion['id'] . '/cerrar') ?>"
                              data-confirm
                              data-confirm-title="Finalizar asignacion"
                              data-confirm-text="Confirmas el cierre normal de esta asignacion?"
                              data-confirm-type="success"
                              data-confirm-button="Si, finalizar">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="accion" value="finalizar">
                            <div class="ds-form-field" style="margin-bottom: .85rem;">
                                <label class="ds-form-label">Motivo <span class="ds-required">*</span></label>
                                <textarea name="motivo_fin" class="form-control" rows="3" required
                                          placeholder="Ej: Termino de contrato, cambio de puesto..."></textarea>
                            </div>
                            <button type="submit" class="ds-btn ds-btn-success" style="width: 100%;">
                                <i class="bi bi-check2-circle"></i>Finalizar
                            </button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-cancelar">
                        <form class="ds-form-main" method="post" action="<?= $view->url('/asignaciones/' . $asignacion['id'] . '/cerrar') ?>"
                              data-confirm
                              data-confirm-title="Cancelar asignacion"
                              data-confirm-text="Esta accion queda registrada en auditoria."
                              data-confirm-type="danger"
                              data-confirm-button="Si, cancelar">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="accion" value="cancelar">
                            <div class="ds-form-field" style="margin-bottom: .85rem;">
                                <label class="ds-form-label">Motivo <span class="ds-required">*</span></label>
                                <textarea name="motivo_fin" class="form-control" rows="3" required
                                          placeholder="Ej: Asignacion incorrecta, error administrativo..."></textarea>
                            </div>
                            <button type="submit" class="ds-btn ds-btn-danger" style="width: 100%;">
                                <i class="bi bi-x-octagon"></i>Cancelar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif ?>
    </div>
</div>
<?php $view->endSection() ?>
