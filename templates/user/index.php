<?php
/**
 * Listado de usuarios.
 *
 * Variables: $user, $csrfToken, $currentPath, $usuarios, $rolesByUser, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
$total = $paginacion->total();
?>

<?php $view->section('title') ?>Usuarios - DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-op-filter .ds-filter-bar {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .ds-op-filter .ds-filter-row {
        padding: 0;
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-people me-1"></i>Usuarios</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-people-fill"></i></span>
        <div>
            <h1>Usuarios del sistema</h1>
            <small data-resumen-de="#usuariosResultados" aria-live="polite"><?= $total ?> usuario<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('usuarios.crear')): ?>
    <a href="<?= $view->url('/usuarios/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nuevo usuario
    </a>
    <?php endif ?>
</div>

<div class="ds-card ds-op-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de usuarios</div>
            <div class="ds-card-subtitle">Consulta por estado de cuenta</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosUsuarios" method="get" action="<?= $view->url('/usuarios') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroUsuariosEstado">Estado</label>
                        <select name="estado" id="filtroUsuariosEstado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <?php foreach (['activo', 'inactivo', 'bloqueado', 'pendiente_verificacion'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>><?= $e(ucfirst(str_replace('_', ' ', $est))) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/usuarios') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-people"></i></span>
        <div>
            <div class="ds-card-title">Listado de usuarios</div>
            <div class="ds-card-subtitle">Todos los usuarios creados, roles y ultimo acceso</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarUsuarios',
            'destino' => '#usuariosResultados',
            'parcial' => 'usuarios',
            'ruta' => '/usuarios',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar usuario...',
            'ayuda' => 'Buscar usuarios por nombres, apellidos, correo, cargo o telefono',
            'filtros' => '#filtrosUsuarios',
            'ocultos' => ['estado' => (string) ($filters['estado'] ?? '')],
        ]) ?>
    </div>
    <div id="usuariosResultados" class="ds-listado-wrap">
        <?= $view->include('user/_listado', [
            'user' => $user,
            'csrfToken' => $csrfToken,
            'usuarios' => $usuarios,
            'rolesByUser' => $rolesByUser,
            'paginacion' => $paginacion,
            'filters' => $filters,
        ]) ?>
    </div>
</div>
<?php $view->endSection() ?>
