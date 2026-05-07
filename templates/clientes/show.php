<?php
/**
 * Detalle de un cliente.
 *
 * Variables: $user, $csrfToken, $currentPath, $cliente
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = match ($cliente['estado']) {
    'activo'    => 'tone-success',
    'inactivo'  => 'tone-warning',
    'archivado' => 'tone-muted',
    default     => 'tone-muted',
};

$ds_dt = 'font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ds-text-soft); margin-bottom: .15rem;';
$ds_dd = 'font-size: .9rem; color: var(--ds-text); font-weight: 600;';

$initials = mb_strtoupper(mb_substr((string) $cliente['razon_social'], 0, 2));
?>

<?php $view->section('title') ?><?= $e((string) $cliente['razon_social']) ?> — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/clientes') ?>">Clientes</a></li>
<li class="breadcrumb-item active"><?= $e((string) $cliente['razon_social']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-briefcase-fill"></i></span>
        <div>
            <h1><?= $e((string) $cliente['razon_social']) ?></h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-chip"><i class="bi bi-tag"></i><?= $e((string) $cliente['tipo_documento']) ?></span>
                <span class="ds-cell-mono" style="font-weight: 700;"><?= $e((string) $cliente['documento']) ?></span>
                <span class="ds-badge <?= $estadoTone ?>"><?= $e(ucfirst((string) $cliente['estado'])) ?></span>
            </small>
        </div>
    </div>
    <div style="display: flex; gap: .5rem;">
        <?php if ($user->tienePermiso('clientes.editar') && $cliente['estado'] !== 'archivado'): ?>
        <a href="<?= $view->url('/clientes/' . $cliente['id'] . '/editar') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-pencil"></i>Editar
        </a>
        <?php endif ?>
        <a href="<?= $view->url('/clientes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Identidad -->
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-building"></i></span>
                <div>
                    <div class="ds-card-title">Identidad</div>
                    <div class="ds-card-subtitle">Datos legales y comerciales</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="display: flex; gap: 1.25rem; align-items: center; padding-bottom: 1.25rem; border-bottom: 1px solid #eef1f5; margin-bottom: 1.25rem;">
                    <div style="width: 64px; height: 64px; border-radius: 16px; background: linear-gradient(135deg, #2f80ed, #0ea5a4); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; box-shadow: 0 8px 24px rgba(47,128,237,.32); flex-shrink: 0;">
                        <?= $e($initials) ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 1.15rem; font-weight: 700; color: var(--ds-text);"><?= $e((string) $cliente['razon_social']) ?></div>
                        <?php if (!empty($cliente['nombre_comercial'])): ?>
                        <div class="ds-cell-sub"><?= $e((string) $cliente['nombre_comercial']) ?></div>
                        <?php endif ?>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Tipo documento</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) $cliente['tipo_documento']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Documento</div>
                        <div class="ds-cell-mono" style="font-weight: 700;"><?= $e((string) $cliente['documento']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Sector</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($cliente['sector'] ?? '—')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contacto -->
        <div class="ds-card">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
                <div>
                    <div class="ds-card-title">Contacto principal</div>
                    <div class="ds-card-subtitle">Persona de referencia</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Nombre</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($cliente['contacto_nombre'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Cargo</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($cliente['contacto_cargo'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Email</div>
                        <div class="ds-cell-mono"><?= $e((string) ($cliente['contacto_email'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Telefono</div>
                        <div class="ds-cell-mono"><?= $e((string) ($cliente['contacto_telefono'] ?? '—')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domicilio -->
        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
                <div>
                    <div class="ds-card-title">Domicilio</div>
                    <div class="ds-card-subtitle">Ubicacion del cliente</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div style="<?= $ds_dt ?>">Direccion</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($cliente['direccion'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Ciudad</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($cliente['ciudad'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Provincia</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($cliente['provincia'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Pais</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($cliente['pais'] ?? 'EC')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($cliente['notas'])): ?>
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-sticky"></i></span>
                <div>
                    <div class="ds-card-title">Notas internas</div>
                    <div class="ds-card-subtitle">Solo visibles para tu empresa</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="white-space: pre-wrap; color: var(--ds-text); font-size: .92rem; line-height: 1.55;"><?= $e((string) $cliente['notas']) ?></div>
            </div>
        </div>
        <?php endif ?>

        <?php if (!empty($puestos) || $user->tienePermiso('puestos.crear')): ?>
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Puestos / Sitios</div>
                        <div class="ds-card-subtitle"><?= count($puestos ?? []) ?> sitio<?= count($puestos ?? []) !== 1 ? 's' : '' ?> activo<?= count($puestos ?? []) !== 1 ? 's' : '' ?> donde se presta servicio</div>
                    </div>
                    <?php if ($user->tienePermiso('puestos.crear') && $cliente['estado'] !== 'archivado'): ?>
                    <a href="<?= $view->url('/puestos/crear?cliente_id=' . $cliente['id']) ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-plus-lg"></i>Nuevo puesto
                    </a>
                    <?php endif ?>
                </div>
            </div>
            <?php if (empty($puestos)): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
                    <div class="ds-empty-icon"><i class="bi bi-geo-alt"></i></div>
                    <p>Aun no hay puestos registrados para este cliente.</p>
                    <?php if ($user->tienePermiso('puestos.crear') && $cliente['estado'] !== 'archivado'): ?>
                    <a href="<?= $view->url('/puestos/crear?cliente_id=' . $cliente['id']) ?>" class="ds-btn ds-btn-primary">
                        <i class="bi bi-plus-lg"></i>Crear primer puesto
                    </a>
                    <?php endif ?>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Puesto</th>
                            <th>Tipo</th>
                            <th>Modalidad</th>
                            <th class="ds-cell-end">Plazas</th>
                            <th>Estado</th>
                            <th class="ds-cell-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($puestos as $p):
                            $pTone = $p['estado'] === 'activo' ? 'tone-success' : 'tone-warning';
                        ?>
                        <tr>
                            <td>
                                <a href="<?= $view->url('/puestos/' . $p['id']) ?>" class="ds-cell-name" style="text-decoration: none;">
                                    <?= $e((string) $p['nombre']) ?>
                                </a>
                                <?php if (!empty($p['codigo'])): ?>
                                <div class="ds-cell-sub"><span class="ds-cell-mono"><?= $e((string) $p['codigo']) ?></span> &middot; <?= $e((string) ($p['ciudad'] ?? '—')) ?></div>
                                <?php else: ?>
                                <div class="ds-cell-sub"><?= $e((string) ($p['ciudad'] ?? '—')) ?></div>
                                <?php endif ?>
                            </td>
                            <td><span class="ds-chip"><?= $e(ucfirst((string) $p['tipo'])) ?></span></td>
                            <td><span class="ds-cell-sub"><?= $e(ucfirst((string) $p['modalidad'])) ?></span></td>
                            <td class="ds-cell-end"><span class="ds-chip"><i class="bi bi-people"></i><?= (int) $p['num_guardias_requeridos'] ?></span></td>
                            <td><span class="ds-badge <?= $pTone ?>"><?= $e(ucfirst((string) $p['estado'])) ?></span></td>
                            <td class="ds-cell-end">
                                <a href="<?= $view->url('/puestos/' . $p['id']) ?>" class="ds-icon-btn" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
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

    <!-- Sidebar: estado y acciones -->
    <div class="col-lg-4">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="ds-card-title">Estado actual</div>
                    <div class="ds-card-subtitle">Ciclo de vida del cliente</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="text-align: center; padding: 1rem 0;">
                    <span class="ds-badge <?= $estadoTone ?>" style="font-size: .9rem; padding: .35rem .8rem;"><?= $e(ucfirst((string) $cliente['estado'])) ?></span>
                </div>
                <?php if (!empty($cliente['archivado_at'])): ?>
                <div style="<?= $ds_dt ?>">Archivado el</div>
                <div class="ds-cell-mono" style="margin-bottom: .85rem;"><?= $e(substr((string) $cliente['archivado_at'], 0, 16)) ?></div>
                <?php endif ?>
                <div style="<?= $ds_dt ?>">Registrado el</div>
                <div class="ds-cell-mono"><?= $e(substr((string) $cliente['created_at'], 0, 16)) ?></div>
            </div>
        </div>

        <?php if ($user->tienePermiso('clientes.archivar')): ?>
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-toggles"></i></span>
                <div>
                    <div class="ds-card-title">Cambiar estado</div>
                    <div class="ds-card-subtitle">Activar, pausar o archivar</div>
                </div>
            </div>
            <div class="ds-card-body" style="padding: .65rem .75rem;">
                <?php
                $opciones = [
                    'activo'    => ['icon' => 'bi-play-circle', 'label' => 'Activar', 'tone' => 'success', 'confirm' => 'Reactivar este cliente?'],
                    'inactivo'  => ['icon' => 'bi-pause-circle', 'label' => 'Marcar inactivo', 'tone' => 'warning', 'confirm' => 'Marcar este cliente como inactivo?'],
                    'archivado' => ['icon' => 'bi-archive', 'label' => 'Archivar', 'tone' => 'danger', 'confirm' => 'Archivar este cliente? Conservara su historico pero no aparecera en operaciones.'],
                ];
                foreach ($opciones as $estado => $cfg):
                    if ($cliente['estado'] === $estado) continue;
                ?>
                <form method="post" action="<?= $view->url('/clientes/' . $cliente['id'] . '/estado') ?>"
                      onsubmit="return confirm('<?= $e($cfg['confirm']) ?>');"
                      style="margin: .35rem 0;">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="estado" value="<?= $estado ?>">
                    <button type="submit" class="ds-btn ds-btn-<?= $cfg['tone'] === 'success' ? 'success' : ($cfg['tone'] === 'danger' ? 'danger' : 'outline') ?> ds-btn-sm" style="width: 100%; justify-content: flex-start;">
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
