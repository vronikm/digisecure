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
?>

<?php $view->section('title') ?>Empresa - DigiSecure<?php $view->endSection() ?>

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
<li class="breadcrumb-item active"><i class="bi bi-building me-1"></i>Empresa</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-building"></i></span>
        <div>
            <h1>Datos de la empresa</h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-cell-mono">RUC <?= $e((string) ($tenant['ruc'] ?? '-')) ?></span>
                <span class="ds-badge <?= $estadoTone((string) ($tenant['estado'] ?? '')) ?>">
                    <?= $e(ucfirst((string) ($tenant['estado'] ?? ''))) ?>
                </span>
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
    <div class="col-lg-7">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="ds-card-title">Informacion general</div>
                    <div class="ds-card-subtitle">Datos legales y de contacto</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-company-grid">
                    <div class="ds-company-item span-8">
                        <div class="ds-company-label">Razon social</div>
                        <div class="ds-company-value break"><?= $e((string) ($tenant['razon_social'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Nombre comercial</div>
                        <div class="ds-company-value break"><?= $e((string) ($tenant['nombre_comercial'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">RUC</div>
                        <div class="ds-cell-mono" style="font-weight: 700;"><?= $e((string) ($tenant['ruc'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-8">
                        <div class="ds-company-label">Email de contacto</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['email_contacto'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Telefono</div>
                        <div class="ds-company-value"><?= $e((string) ($tenant['telefono'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-8">
                        <div class="ds-company-label">Direccion</div>
                        <div class="ds-company-value break"><?= $e((string) ($tenant['direccion'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Ciudad</div>
                        <div class="ds-company-value"><?= $e((string) ($tenant['ciudad'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Provincia</div>
                        <div class="ds-company-value"><?= $e((string) ($tenant['provincia'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Pais</div>
                        <div class="ds-company-value"><?= $e((string) ($tenant['pais'] ?? 'EC')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-gem"></i></span>
                <div>
                    <div class="ds-card-title">Plan y limites</div>
                    <div class="ds-card-subtitle">Cuotas contratadas</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-company-grid">
                    <div class="ds-company-item span-6">
                        <div class="ds-company-label">Plan</div>
                        <div class="ds-company-value"><?= $e((string) ($tenant['plan_codigo'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-6">
                        <div class="ds-company-label">Estado</div>
                        <div><span class="ds-badge <?= $estadoTone((string) ($tenant['estado'] ?? '')) ?>"><?= $e(ucfirst((string) ($tenant['estado'] ?? ''))) ?></span></div>
                    </div>
                    <div class="ds-company-item span-6">
                        <div class="ds-company-label">Inicio</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['plan_inicio'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-6">
                        <div class="ds-company-label">Vencimiento</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['plan_vencimiento'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Max. usuarios</div>
                        <div class="ds-company-value"><?= (int) ($tenant['max_usuarios'] ?? 0) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Max. guardias</div>
                        <div class="ds-company-value"><?= (int) ($tenant['max_guardias'] ?? 0) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Almacenamiento</div>
                        <div class="ds-company-value"><?= (int) ($tenant['max_storage_mb'] ?? 0) ?> MB</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="ds-card" style="margin-bottom: 0;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-globe"></i></span>
                <div>
                    <div class="ds-card-title">Configuracion regional</div>
                    <div class="ds-card-subtitle">Zona horaria y locale</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-company-grid">
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Zona horaria</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['timezone'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Locale</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['locale'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Tenant ID</div>
                        <div class="ds-cell-mono"><?= $e((string) ($tenant['id'] ?? '-')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
