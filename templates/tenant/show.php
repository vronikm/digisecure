<?php
/**
 * Vista de datos de la empresa (tenant).
 *
 * Variables: $user, $csrfToken, $currentPath, $tenant
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = static fn(string $estado) => match ($estado) {
    'activo'     => 'tone-success',
    'suspendido' => 'tone-warning',
    'cancelado'  => 'tone-danger',
    'pendiente'  => 'tone-info',
    default      => 'tone-muted',
};

$ds_dt = 'font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ds-text-soft); margin-bottom: .15rem;';
$ds_dd = 'font-size: .9rem; color: var(--ds-text); font-weight: 600;';
?>

<?php $view->section('title') ?>Empresa — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-building me-1"></i>Empresa</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-building"></i></span>
        <div>
            <h1>Datos de la empresa</h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-cell-mono">RUC <?= $e((string) $tenant['ruc']) ?></span>
                <span class="ds-badge <?= $estadoTone((string) $tenant['estado']) ?>"><?= $e(ucfirst((string) $tenant['estado'])) ?></span>
            </small>
        </div>
    </div>
    <?php if ($user->tienePermiso('tenants.editar')): ?>
    <a href="<?= $view->url('/empresa/editar') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
        <i class="bi bi-pencil"></i>Editar datos
    </a>
    <?php endif ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="ds-card-title">Informacion general</div>
                    <div class="ds-card-subtitle">Datos legales y de contacto</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div style="<?= $ds_dt ?>">Razon social</div>
                        <div style="<?= $ds_dd ?>; font-size: 1.05rem;"><?= $e((string) $tenant['razon_social']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Nombre comercial</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($tenant['nombre_comercial'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">RUC</div>
                        <div class="ds-cell-mono" style="font-weight: 700; color: var(--ds-text);"><?= $e((string) $tenant['ruc']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Email de contacto</div>
                        <div class="ds-cell-mono" style="color: var(--ds-text); font-weight: 600;"><?= $e((string) $tenant['email_contacto']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Telefono</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($tenant['telefono'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-12">
                        <div style="<?= $ds_dt ?>">Direccion</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($tenant['direccion'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Ciudad</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($tenant['ciudad'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Provincia</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($tenant['provincia'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Pais</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) ($tenant['pais'] ?? 'EC')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-gem"></i></span>
                <div>
                    <div class="ds-card-title">Plan y limites</div>
                    <div class="ds-card-subtitle">Cuotas contratadas</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-form-field" style="margin-bottom: .85rem;">
                    <div style="<?= $ds_dt ?>">Plan</div>
                    <div><span class="ds-chip"><i class="bi bi-gem"></i><?= $e((string) $tenant['plan_codigo']) ?></span></div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Inicio</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['plan_inicio'] ?? '—')) ?></div>
                    </div>
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Vencimiento</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['plan_vencimiento'] ?? '—')) ?></div>
                    </div>
                    <div class="col-12" style="margin-top: .75rem; padding-top: .75rem; border-top: 1px solid #eef1f5;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: .65rem;">
                            <div>
                                <div style="<?= $ds_dt ?>">Max. usuarios</div>
                                <div style="<?= $ds_dd ?>; font-size: 1.05rem;"><?= (int) $tenant['max_usuarios'] ?></div>
                            </div>
                            <div>
                                <div style="<?= $ds_dt ?>">Max. guardias</div>
                                <div style="<?= $ds_dd ?>; font-size: 1.05rem;"><?= (int) $tenant['max_guardias'] ?></div>
                            </div>
                        </div>
                        <div style="margin-top: .65rem;">
                            <div style="<?= $ds_dt ?>">Almacenamiento</div>
                            <div style="<?= $ds_dd ?>; font-size: 1.05rem;"><?= (int) $tenant['max_storage_mb'] ?> MB</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-globe"></i></span>
                <div>
                    <div class="ds-card-title">Configuracion regional</div>
                    <div class="ds-card-subtitle">Zona horaria y locale</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Zona horaria</div>
                        <div class="ds-cell-mono"><?= $e((string) $tenant['timezone']) ?></div>
                    </div>
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Locale</div>
                        <div class="ds-cell-mono"><?= $e((string) $tenant['locale']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
