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
?>

<?php $view->section('title') ?>Mi perfil — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
<li class="breadcrumb-item active">Mi perfil</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="row">
    <div class="col-lg-8">
        <h4 class="mb-4"><i class="bi bi-person me-2"></i>Mi perfil</h4>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Nombres</label>
                        <div class="fw-bold"><?= $e($user->nombres) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Apellidos</label>
                        <div class="fw-bold"><?= $e($user->apellidos) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Correo electrónico</label>
                        <div class="fw-bold"><?= $e($user->email) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Estado</label>
                        <div>
                            <?php
                            $badgeClass = match ($user->estado) {
                                'activo' => 'bg-success',
                                'inactivo' => 'bg-secondary',
                                'bloqueado' => 'bg-danger',
                                default => 'bg-warning',
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= $e(ucfirst($user->estado)) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Roles y permisos -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-shield-check me-2"></i>Roles y permisos
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small">Roles asignados</label>
                    <div>
                        <?php foreach ($user->roles as $rol): ?>
                            <span class="badge bg-info me-1"><?= $e($rol['nombre']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="form-label text-muted small">Permisos (<?= count($user->permisos) ?>)</label>
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($user->permisos as $p): ?>
                            <span class="badge bg-secondary me-1 mb-1"><?= $e($p) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-building me-2"></i>Empresa
            </div>
            <div class="card-body">
                <dl class="mb-0 small">
                    <dt>Nombre</dt>
                    <dd><?= $e($user->tenantNombre) ?></dd>
                    <dt>Estado</dt>
                    <dd><span class="badge bg-<?= $user->tenantEstado === 'activo' ? 'success' : 'warning' ?>"><?= $e(ucfirst($user->tenantEstado)) ?></span></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-gear me-2"></i>Acciones
            </div>
            <div class="list-group list-group-flush">
                <a href="/perfil/password" class="list-group-item list-group-item-action">
                    <i class="bi bi-key me-2 text-primary"></i>Cambiar contraseña
                </a>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
