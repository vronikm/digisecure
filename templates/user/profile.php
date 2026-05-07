<?php
/**
 * Perfil del usuario autenticado.
 *
 * Variables: $user, $csrfToken, $currentPath
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */

$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = match ($user->estado) {
    'activo' => 'tone-success',
    'inactivo' => 'tone-muted',
    'bloqueado' => 'tone-danger',
    default => 'tone-warning',
};

$tenantTone = $user->tenantEstado === 'activo' ? 'tone-success' : 'tone-warning';

$initials = mb_strtoupper(mb_substr($user->nombres, 0, 1) . mb_substr($user->apellidos, 0, 1));
?>

<?php $view->section('title') ?>Mi perfil — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Mi perfil</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-circle"></i></span>
        <div>
            <h1>Mi perfil</h1>
            <small>Datos de tu cuenta y permisos asignados</small>
        </div>
    </div>
    <a href="<?= $view->url('/perfil/password') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
        <i class="bi bi-key"></i>Cambiar contrasena
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Tarjeta principal del usuario -->
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-person"></i></span>
                <div>
                    <div class="ds-card-title">Datos personales</div>
                    <div class="ds-card-subtitle">Identidad y contacto</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="display: flex; gap: 1.25rem; align-items: center; padding-bottom: 1.25rem; border-bottom: 1px solid #eef1f5; margin-bottom: 1.25rem;">
                    <div style="width: 72px; height: 72px; border-radius: 18px; background: linear-gradient(135deg, #2f80ed, #0ea5a4); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 700; box-shadow: 0 8px 24px rgba(47,128,237,.32); flex-shrink: 0;">
                        <?= $e($initials) ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 1.15rem; font-weight: 700; color: var(--ds-text);"><?= $e($user->nombreCompleto()) ?></div>
                        <div class="ds-cell-sub" style="margin-top: .15rem;"><span class="ds-cell-mono"><?= $e($user->email) ?></span></div>
                        <div style="margin-top: .5rem; display: flex; gap: .35rem; flex-wrap: wrap;">
                            <span class="ds-badge <?= $estadoTone ?>"><?= $e(ucfirst($user->estado)) ?></span>
                            <?php if ($user->esSuperAdmin()): ?>
                                <span class="ds-badge tone-purple"><i class="bi bi-stars"></i>Superadmin</span>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="ds-form-field">
                            <label class="ds-form-label">Nombres</label>
                            <div style="font-weight: 600; color: var(--ds-text);"><?= $e($user->nombres) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="ds-form-field">
                            <label class="ds-form-label">Apellidos</label>
                            <div style="font-weight: 600; color: var(--ds-text);"><?= $e($user->apellidos) ?></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="ds-form-field">
                            <label class="ds-form-label">Correo electronico</label>
                            <div class="ds-cell-mono" style="color: var(--ds-text);"><?= $e($user->email) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Roles y permisos -->
        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-shield-check"></i></span>
                <div>
                    <div class="ds-card-title">Roles y permisos</div>
                    <div class="ds-card-subtitle">Niveles de acceso asignados a tu cuenta</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-form-field" style="margin-bottom: 1rem;">
                    <label class="ds-form-label">Roles asignados</label>
                    <div style="display: flex; flex-wrap: wrap; gap: .35rem;">
                        <?php if (empty($user->roles)): ?>
                            <span class="ds-badge tone-muted">Sin roles asignados</span>
                        <?php else: ?>
                            <?php foreach ($user->roles as $rol): ?>
                                <span class="ds-badge tone-info no-dot"><i class="bi bi-shield-check"></i><?= $e($rol['nombre']) ?></span>
                            <?php endforeach ?>
                        <?php endif ?>
                    </div>
                </div>
                <div class="ds-form-field">
                    <label class="ds-form-label">
                        Permisos efectivos
                        <span style="color: var(--ds-text-soft); font-weight: 500; font-size: .75rem; margin-left: .35rem;">(<?= count($user->permisos) ?> permisos activos)</span>
                    </label>
                    <div style="max-height: 220px; overflow-y: auto; padding: .65rem .75rem; background: #f4f7fb; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <?php if (empty($user->permisos)): ?>
                            <div class="ds-cell-sub">Sin permisos asignados.</div>
                        <?php else: ?>
                            <div style="display: flex; flex-wrap: wrap; gap: .25rem;">
                                <?php foreach ($user->permisos as $p): ?>
                                    <span class="ds-chip"><?= $e($p) ?></span>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Empresa -->
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-building"></i></span>
                <div>
                    <div class="ds-card-title">Empresa</div>
                    <div class="ds-card-subtitle">Tenant al que perteneces</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-form-field" style="margin-bottom: .85rem;">
                    <label class="ds-form-label">Nombre</label>
                    <div style="font-weight: 700; color: var(--ds-text);"><?= $e($user->tenantNombre) ?></div>
                </div>
                <div class="ds-form-field">
                    <label class="ds-form-label">Estado</label>
                    <div><span class="ds-badge <?= $tenantTone ?>"><?= $e(ucfirst($user->tenantEstado)) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Acciones rapidas -->
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-gear"></i></span>
                <div>
                    <div class="ds-card-title">Acciones</div>
                    <div class="ds-card-subtitle">Gestion de tu cuenta</div>
                </div>
            </div>
            <div class="ds-card-body" style="padding: .35rem .5rem;">
                <a href="<?= $view->url('/perfil/password') ?>"
                   style="display: flex; align-items: center; gap: .65rem; padding: .75rem .85rem; border-radius: 10px; color: var(--ds-text); text-decoration: none; transition: background .12s;"
                   onmouseover="this.style.background='#f4f7fb'" onmouseout="this.style.background='transparent'">
                    <span style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(47,128,237,.12); color: #2f80ed;"><i class="bi bi-key"></i></span>
                    <span style="flex: 1;">
                        <span style="font-weight: 600; display: block; font-size: .88rem;">Cambiar contrasena</span>
                        <span class="ds-cell-sub">Actualiza tus credenciales</span>
                    </span>
                    <i class="bi bi-chevron-right" style="color: var(--ds-text-soft); font-size: .85rem;"></i>
                </a>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
