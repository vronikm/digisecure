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

$estadoBadge = static fn(string $estado) => match ($estado) {
    'activo' => 'bg-success',
    'suspendido' => 'bg-warning text-dark',
    'cancelado' => 'bg-danger',
    'pendiente' => 'bg-info text-dark',
    default => 'bg-secondary',
};
?>

<?php $view->section('title') ?>Empresa — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-building me-1"></i>Empresa</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-building me-2"></i>Datos de la empresa</h4>
    <?php if ($user->tienePermiso('tenants.editar')): ?>
    <a href="<?= $view->url('/empresa/editar') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Editar
    </a>
    <?php endif ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Información general</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Razón social</dt>
                    <dd class="col-sm-8"><?= $e((string)$tenant['razon_social']) ?></dd>

                    <dt class="col-sm-4">Nombre comercial</dt>
                    <dd class="col-sm-8"><?= $e((string)($tenant['nombre_comercial'] ?? '—')) ?></dd>

                    <dt class="col-sm-4">RUC</dt>
                    <dd class="col-sm-8"><code><?= $e((string)$tenant['ruc']) ?></code></dd>

                    <dt class="col-sm-4">Email de contacto</dt>
                    <dd class="col-sm-8"><?= $e((string)$tenant['email_contacto']) ?></dd>

                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8"><?= $e((string)($tenant['telefono'] ?? '—')) ?></dd>

                    <dt class="col-sm-4">Dirección</dt>
                    <dd class="col-sm-8"><?= $e((string)($tenant['direccion'] ?? '—')) ?></dd>

                    <dt class="col-sm-4">Ciudad</dt>
                    <dd class="col-sm-8"><?= $e((string)($tenant['ciudad'] ?? '—')) ?></dd>

                    <dt class="col-sm-4">Provincia</dt>
                    <dd class="col-sm-8"><?= $e((string)($tenant['provincia'] ?? '—')) ?></dd>

                    <dt class="col-sm-4">País</dt>
                    <dd class="col-sm-8"><?= $e((string)($tenant['pais'] ?? 'EC')) ?></dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">
                        <span class="badge <?= $estadoBadge((string)$tenant['estado']) ?>"><?= $e(ucfirst((string)$tenant['estado'])) ?></span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header"><strong>Plan y límites</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-6">Plan</dt>
                    <dd class="col-sm-6"><span class="badge bg-primary"><?= $e((string)$tenant['plan_codigo']) ?></span></dd>
                    <dt class="col-sm-6">Inicio</dt>
                    <dd class="col-sm-6"><?= $e((string)($tenant['plan_inicio'] ?? '—')) ?></dd>
                    <dt class="col-sm-6">Vencimiento</dt>
                    <dd class="col-sm-6"><?= $e((string)($tenant['plan_vencimiento'] ?? '—')) ?></dd>
                    <dt class="col-sm-6">Máx. usuarios</dt>
                    <dd class="col-sm-6"><?= (int)$tenant['max_usuarios'] ?></dd>
                    <dt class="col-sm-6">Máx. guardias</dt>
                    <dd class="col-sm-6"><?= (int)$tenant['max_guardias'] ?></dd>
                    <dt class="col-sm-6">Almacenamiento</dt>
                    <dd class="col-sm-6"><?= (int)$tenant['max_storage_mb'] ?> MB</dd>
                </dl>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header"><strong>Configuración regional</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-6">Zona horaria</dt>
                    <dd class="col-sm-6"><?= $e((string)$tenant['timezone']) ?></dd>
                    <dt class="col-sm-6">Locale</dt>
                    <dd class="col-sm-6"><?= $e((string)$tenant['locale']) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
