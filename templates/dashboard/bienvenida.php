<?php
/**
 * Portada para usuarios sin acceso a los KPIs del dashboard
 * (sin permiso de guardias, expedientes ni reportes).
 *
 * Variables: $user, $csrfToken, $currentPath
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */

$view->layout('layout/app');
$e = ViewRenderer::e(...);
$tieneModulos = $user->permisos !== [];
?>

<?php $view->section('title') ?>Inicio - DigiSecure<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-house-door-fill"></i></span>
        <div>
            <h1>Hola, <?= $e($user->nombres) ?></h1>
            <small><?= $e($user->tenantNombre) ?></small>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-check"></i></div>
            <h3>Tu cuenta está activa</h3>
            <p>
                <?php if ($tieneModulos): ?>
                    Usa el menú lateral para entrar a los módulos que tienes asignados.
                    El resumen de indicadores de la empresa solo lo ven los perfiles con acceso a guardias, expedientes o reportes.
                <?php else: ?>
                    Todavía no tienes módulos asignados. Pide al administrador de tu empresa que te asigne un rol.
                <?php endif ?>
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="<?= $view->url('/perfil') ?>" class="ds-btn ds-btn-ghost">
                    <i class="bi bi-person-circle"></i>Mi perfil
                </a>
                <a href="<?= $view->url('/perfil/password') ?>" class="ds-btn ds-btn-ghost">
                    <i class="bi bi-key"></i>Cambiar contraseña
                </a>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
