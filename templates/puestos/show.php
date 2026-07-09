<?php
/**
 * Detalle de un puesto.
 *
 * Variables: $user, $csrfToken, $currentPath, $puesto
 */

use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Puesto\Domain\Enum\ModalidadPuesto;
use DigiSecure\Puesto\Domain\Enum\TipoPuesto;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$tipo = TipoPuesto::tryFrom((string) $puesto['tipo']);
$modalidad = ModalidadPuesto::tryFrom((string) $puesto['modalidad']);

$estadoTone = match ($puesto['estado']) {
    'activo'    => 'tone-success',
    'inactivo'  => 'tone-warning',
    'archivado' => 'tone-muted',
    default     => 'tone-muted',
};
?>

<?php $view->section('title') ?><?= $e((string) $puesto['nombre']) ?> - DigiSecure<?php $view->endSection() ?>

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
        .ds-detail-item.span-8,
        .ds-detail-item.span-12 {
            grid-column: span 12;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/puestos') ?>">Puestos</a></li>
<li class="breadcrumb-item active"><?= $e((string) $puesto['nombre']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi <?= $tipo ? $tipo->icon() : 'bi-geo-alt-fill' ?>"></i></span>
        <div>
            <h1><?= $e((string) $puesto['nombre']) ?></h1>
            <small style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                <?php if (!empty($puesto['codigo'])): ?>
                    <span class="ds-cell-mono"><?= $e((string) $puesto['codigo']) ?></span>
                <?php endif ?>
                <?php if ($tipo): ?>
                    <span class="ds-chip"><i class="bi <?= $tipo->icon() ?>"></i><?= $e($tipo->label()) ?></span>
                <?php endif ?>
                <span class="ds-badge <?= $estadoTone ?>"><?= $e(ucfirst((string) $puesto['estado'])) ?></span>
            </small>
        </div>
    </div>
    <div style="display:flex;gap:.5rem;">
        <?php if ($user->tienePermiso('puestos.editar') && $puesto['estado'] !== 'archivado'): ?>
        <a href="<?= $view->url('/puestos/' . $puesto['id'] . '/editar') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-pencil"></i>Editar
        </a>
        <?php endif ?>
        <a href="<?= $view->url('/puestos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver
        </a>
    </div>
</div>

<?php
$asignacionesActivas = $asignacionesActivas ?? [];
$plazasOcupadas = count($asignacionesActivas);
$plazasTotales = (int) $puesto['num_guardias_requeridos'];
$plazasLibres = max(0, $plazasTotales - $plazasOcupadas);
$coberturaPct = $plazasTotales > 0 ? min(100, (int) round($plazasOcupadas / $plazasTotales * 100)) : 0;
$coberturaTone = $coberturaPct >= 100 ? 'tone-success' : ($coberturaPct >= 60 ? '' : 'tone-warning');
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-briefcase"></i></span>
                <div style="flex:1;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                    <div>
                        <div class="ds-card-title">Cliente vinculado</div>
                        <div class="ds-card-subtitle">A quien se presta el servicio</div>
                    </div>
                    <a href="<?= $view->url('/clientes/' . $puesto['cliente_id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                        <i class="bi bi-arrow-up-right"></i>Ver cliente
                    </a>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="font-size:1.05rem;font-weight:700;color:var(--ds-text);"><?= $e((string) $puesto['cliente_razon_social']) ?></div>
                <?php if (!empty($puesto['cliente_nombre_comercial'])): ?>
                <div class="ds-cell-sub" style="margin-top:.15rem;"><?= $e((string) $puesto['cliente_nombre_comercial']) ?></div>
                <?php endif ?>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
                <div>
                    <div class="ds-card-title">Operacion</div>
                    <div class="ds-card-subtitle">Modalidad, plazas y horario</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-detail-grid">
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Modalidad</div>
                        <div class="ds-detail-value"><?= $modalidad ? $e($modalidad->label()) : $e((string) $puesto['modalidad']) ?></div>
                    </div>
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Plazas requeridas</div>
                        <div><span class="ds-chip"><i class="bi bi-people"></i><?= (int) $puesto['num_guardias_requeridos'] ?> guardia<?= (int) $puesto['num_guardias_requeridos'] !== 1 ? 's' : '' ?></span></div>
                    </div>
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Tipo</div>
                        <div class="ds-detail-value"><?= $tipo ? $e($tipo->label()) : $e((string) $puesto['tipo']) ?></div>
                    </div>
                    <div class="ds-detail-item span-12">
                        <div class="ds-detail-label">Horario / Cobertura</div>
                        <div class="ds-detail-value break"><?= $e((string) ($puesto['horario_descripcion'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-detail-item span-6">
                        <div class="ds-detail-label">Contacto en el sitio</div>
                        <div class="ds-detail-value"><?= $e((string) ($puesto['contacto_sitio'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-detail-item span-6">
                        <div class="ds-detail-label">Telefono del sitio</div>
                        <div class="ds-cell-mono"><?= $e((string) ($puesto['telefono_sitio'] ?? '-')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
                <div>
                    <div class="ds-card-title">Ubicacion</div>
                    <div class="ds-card-subtitle">Direccion fisica y coordenadas</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-detail-grid">
                    <div class="ds-detail-item span-12">
                        <div class="ds-detail-label">Direccion</div>
                        <div class="ds-detail-value break"><?= $e((string) ($puesto['direccion'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Ciudad</div>
                        <div class="ds-detail-value"><?= $e((string) ($puesto['ciudad'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Provincia</div>
                        <div class="ds-detail-value"><?= $e((string) ($puesto['provincia'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-detail-item span-4">
                        <div class="ds-detail-label">Pais</div>
                        <div class="ds-detail-value"><?= $e((string) ($puesto['pais'] ?? 'EC')) ?></div>
                    </div>
                    <?php if (!empty($puesto['latitud']) && !empty($puesto['longitud'])): ?>
                    <div class="ds-detail-item span-12">
                        <div class="ds-detail-label">Coordenadas GPS</div>
                        <div class="ds-cell-mono">
                            <?= $e((string) $puesto['latitud']) ?>, <?= $e((string) $puesto['longitud']) ?>
                            &middot;
                            <a href="https://www.google.com/maps?q=<?= $e((string) $puesto['latitud']) ?>,<?= $e((string) $puesto['longitud']) ?>"
                               target="_blank" rel="noopener" style="color:#2563eb;text-decoration:none;">
                                <i class="bi bi-box-arrow-up-right"></i> Abrir en Google Maps
                            </a>
                        </div>
                    </div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <?php if (!empty($puesto['observaciones'])): ?>
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-sticky"></i></span>
                <div>
                    <div class="ds-card-title">Observaciones</div>
                    <div class="ds-card-subtitle">Detalles operativos del sitio</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="white-space:pre-wrap;color:var(--ds-text);font-size:.92rem;line-height:1.55;"><?= $e((string) $puesto['observaciones']) ?></div>
            </div>
        </div>
        <?php endif ?>

        <?php if ($user->tienePermiso('asignaciones.ver') && $puesto['estado'] === 'activo'): ?>
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-link-45deg"></i></span>
                <div style="flex:1;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                    <div>
                        <div class="ds-card-title">Asignaciones activas</div>
                        <div class="ds-card-subtitle"><?= $plazasOcupadas ?> de <?= $plazasTotales ?> plaza<?= $plazasTotales !== 1 ? 's' : '' ?> cubierta<?= $plazasTotales !== 1 ? 's' : '' ?></div>
                    </div>
                    <?php if ($user->tienePermiso('asignaciones.crear') && $plazasLibres > 0): ?>
                    <a href="<?= $view->url('/asignaciones/crear?puesto_id=' . $puesto['id']) ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-plus-lg"></i>Asignar guardia
                    </a>
                    <?php endif ?>
                </div>
            </div>
            <div class="ds-card-body" style="padding-bottom:.85rem;">
                <div style="display:flex;gap:.65rem;align-items:center;margin-bottom:1rem;">
                    <div class="ds-progress" style="height:10px;flex:1;">
                        <div class="ds-progress-bar <?= $coberturaTone ?>" style="width: <?= $coberturaPct ?>%"></div>
                    </div>
                    <span class="ds-cell-mono" style="font-weight:700;min-width:3rem;text-align:right;"><?= $coberturaPct ?>%</span>
                </div>
            </div>
            <?php if ($asignacionesActivas === []): ?>
            <div class="ds-card-body" style="padding-top:0;">
                <div class="ds-empty-state" style="padding:1.25rem 1rem;">
                    <div class="ds-empty-icon"><i class="bi bi-people"></i></div>
                    <p>Aun no hay guardias asignados a este puesto.</p>
                    <?php if ($user->tienePermiso('asignaciones.crear')): ?>
                    <a href="<?= $view->url('/asignaciones/crear?puesto_id=' . $puesto['id']) ?>" class="ds-btn ds-btn-primary">
                        <i class="bi bi-plus-lg"></i>Crear primera asignacion
                    </a>
                    <?php endif ?>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Guardia</th>
                            <th>Periodo</th>
                            <th>Rol</th>
                            <th class="ds-cell-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asignacionesActivas as $a): ?>
                        <tr>
                            <td>
                                <a href="<?= $view->url('/asignaciones/' . $a['id']) ?>" class="ds-cell-name" style="text-decoration:none;">
                                    <?= $e((string) $a['ficha_apellidos']) ?> <?= $e((string) $a['ficha_nombres']) ?>
                                </a>
                                <div class="ds-cell-sub"><span class="ds-cell-mono"><?= $e((string) $a['ficha_correlativo']) ?></span></div>
                            </td>
                            <td>
                                <div class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $a['fecha_inicio'])) ?></div>
                                <div class="ds-cell-sub">
                                    <?= !empty($a['fecha_fin']) ? 'hasta ' . date('d/m/Y', strtotime((string) $a['fecha_fin'])) : '<span style="color:#059669;">vigente</span>' ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($a['rol_en_puesto'])): ?>
                                    <span class="ds-chip"><?= $e((string) $a['rol_en_puesto']) ?></span>
                                <?php else: ?>
                                    <span class="ds-cell-sub">-</span>
                                <?php endif ?>
                            </td>
                            <td class="ds-cell-end">
                                <a href="<?= $view->url('/asignaciones/' . $a['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver">
                                    Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
        <?php endif ?>
    </div>

    <div class="col-lg-4">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="ds-card-title">Estado actual</div>
                    <div class="ds-card-subtitle">Ciclo de vida del puesto</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="text-align:center;padding:1rem 0;">
                    <span class="ds-badge <?= $estadoTone ?>" style="font-size:.9rem;padding:.35rem .8rem;"><?= $e(ucfirst((string) $puesto['estado'])) ?></span>
                </div>
                <?php if (!empty($puesto['archivado_at'])): ?>
                <div class="ds-detail-label">Archivado el</div>
                <div class="ds-cell-mono" style="margin-bottom:.85rem;"><?= $e(substr((string) $puesto['archivado_at'], 0, 16)) ?></div>
                <?php endif ?>
                <div class="ds-detail-label">Registrado el</div>
                <div class="ds-cell-mono"><?= $e(substr((string) $puesto['created_at'], 0, 16)) ?></div>
            </div>
        </div>

        <?php if ($user->tienePermiso('puestos.archivar')): ?>
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-toggles"></i></span>
                <div>
                    <div class="ds-card-title">Cambiar estado</div>
                    <div class="ds-card-subtitle">Activar, pausar o archivar</div>
                </div>
            </div>
            <div class="ds-card-body" style="padding:.65rem .75rem;">
                <?php
                $opciones = [
                    'activo'    => ['icon' => 'bi-play-circle', 'label' => 'Activar', 'tone' => 'success', 'confirm' => 'Reactivar este puesto?'],
                    'inactivo'  => ['icon' => 'bi-pause-circle', 'label' => 'Marcar inactivo', 'tone' => 'warning', 'confirm' => 'Marcar este puesto como inactivo?'],
                    'archivado' => ['icon' => 'bi-archive', 'label' => 'Archivar', 'tone' => 'danger', 'confirm' => 'Archivar este puesto? Conserva su historico, pero no aparecera en operaciones.'],
                ];
                foreach ($opciones as $estado => $cfg):
                    if ($puesto['estado'] === $estado) {
                        continue;
                    }
                ?>
                <form class="ds-form-main" method="post" action="<?= $view->url('/puestos/' . $puesto['id'] . '/estado') ?>"
                      data-confirm
                      data-confirm-title="<?= $e($cfg['label']) ?> puesto"
                      data-confirm-text="<?= $e($cfg['confirm']) ?>"
                      data-confirm-type="<?= $e($cfg['tone']) ?>"
                      data-confirm-button="Si, continuar"
                      style="margin:.35rem 0;">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="estado" value="<?= $estado ?>">
                    <button type="submit" class="ds-btn ds-btn-<?= $cfg['tone'] === 'success' ? 'success' : ($cfg['tone'] === 'danger' ? 'danger' : 'outline') ?> ds-btn-sm" style="width:100%;justify-content:flex-start;">
                        <i class="bi <?= $cfg['icon'] ?>"></i><?= $e($cfg['label']) ?>
                    </button>
                </form>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
    </div>
</div>
<?php $view->endSection() ?>
