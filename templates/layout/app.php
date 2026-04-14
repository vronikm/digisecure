<?php
/**
 * Layout principal.
 *
 * Variables esperadas:
 *   $user        AuthenticatedUser
 *   $csrfToken   string
 *   $currentPath string
 *   $_sections   array
 */

use DigiSecure\Kernel\View\Menu\MenuBuilder;
use DigiSecure\Kernel\View\Menu\MenuItem;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */
/** @var string $csrfToken */
/** @var string $currentPath */

$e = ViewRenderer::e(...);
$pageTitle = $view->yieldSection('title', 'DigiSecure');

$sectionMetaMap = [
    'Inicio' => ['icon' => 'bi-house-door', 'accent' => '#2f80ed', 'soft' => 'rgba(47, 128, 237, 0.18)'],
    'Operacion' => ['icon' => 'bi-briefcase', 'accent' => '#0ea5a4', 'soft' => 'rgba(14, 165, 164, 0.18)'],
    'Control' => ['icon' => 'bi-activity', 'accent' => '#f2994a', 'soft' => 'rgba(242, 153, 74, 0.18)'],
    'Configuracion' => ['icon' => 'bi-sliders2', 'accent' => '#00a86b', 'soft' => 'rgba(0, 168, 107, 0.18)'],
    'Privacidad' => ['icon' => 'bi-shield-lock', 'accent' => '#e76f51', 'soft' => 'rgba(231, 111, 81, 0.18)'],
];

$menuSections = [];
$currentSectionLabel = null;

foreach (MenuBuilder::build() as $entry) {
    if (($entry['type'] ?? null) === 'header') {
        $currentSectionLabel = (string) ($entry['label'] ?? 'General');
        $menuSections[$currentSectionLabel] ??= [
            'label' => $currentSectionLabel,
            'meta' => $sectionMetaMap[$currentSectionLabel] ?? ['icon' => 'bi-grid', 'accent' => '#2e86de', 'soft' => 'rgba(46, 134, 222, 0.18)'],
            'items' => [],
        ];
        continue;
    }

    if (($entry['type'] ?? null) !== 'item' || !isset($entry['item']) || !$entry['item'] instanceof MenuItem) {
        continue;
    }

    $item = $entry['item'];
    $isVisible = $item->isVisibleFor($user->permisos);
    $hasVisibleChildren = $item->children === [] || $item->hasVisibleChildren($user->permisos);
    if (!$isVisible || !$hasVisibleChildren) {
        continue;
    }

    $sectionLabel = $currentSectionLabel ?? 'General';
    $menuSections[$sectionLabel] ??= [
        'label' => $sectionLabel,
        'meta' => $sectionMetaMap[$sectionLabel] ?? ['icon' => 'bi-grid', 'accent' => '#2e86de', 'soft' => 'rgba(46, 134, 222, 0.18)'],
        'items' => [],
    ];
    $menuSections[$sectionLabel]['items'][] = $item;
}

$menuSections = array_values(array_filter(
    $menuSections,
    static fn(array $section): bool => $section['items'] !== [],
));

$visibleModules = array_reduce(
    $menuSections,
    static fn(int $carry, array $section): int => $carry + count($section['items']),
    0,
);

$roleLabels = array_values(array_filter(array_map(
    static fn(array $role): string => trim((string) ($role['nombre'] ?? '')),
    $user->roles,
)));
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <title><?= $e($pageTitle) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1p8Rbdc5E3QYkQ5jU3TmgE7pC7dMf1" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --ds-primary: #15304f;
            --ds-primary-dark: #0d1f36;
            --ds-surface: #f4f7fb;
            --ds-border: rgba(21, 48, 79, 0.1);
            --ds-text: #16324f;
            --ds-text-soft: #5f7288;
            --ds-sidebar-w: 310px;
            --ds-topbar-h: 70px;
            --ds-shadow: 0 18px 48px rgba(9, 29, 53, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(47, 128, 237, 0.12), transparent 32%),
                radial-gradient(circle at bottom right, rgba(14, 165, 164, 0.10), transparent 30%),
                var(--ds-surface);
            color: var(--ds-text);
            overflow-x: hidden;
        }

        .ds-topbar {
            position: fixed;
            inset: 0 0 auto 0;
            height: var(--ds-topbar-h);
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0 1.25rem;
            background: rgba(13, 31, 54, 0.94);
            backdrop-filter: blur(16px);
            color: #fff;
            z-index: 1045;
            box-shadow: 0 10px 24px rgba(5, 16, 30, 0.18);
        }

        .ds-topbar .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: inherit;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-decoration: none;
        }

        .ds-brand-mark {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(47, 128, 237, 0.95), rgba(14, 165, 164, 0.9));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .ds-brand-copy small {
            display: block;
            color: rgba(255, 255, 255, 0.62);
            font-size: 0.72rem;
            font-weight: 500;
        }

        .ds-topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .ds-topbar-context {
            display: none;
            text-align: right;
            line-height: 1.15;
        }

        .ds-topbar-context .tenant-name {
            display: block;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.72);
        }

        .ds-topbar-context .module-count {
            display: block;
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .ds-sidebar {
            position: fixed;
            top: var(--ds-topbar-h);
            left: 0;
            bottom: 0;
            width: var(--ds-sidebar-w);
            padding: 1rem 1rem 1.25rem;
            background: linear-gradient(180deg, #10243f 0%, #0d1f36 100%);
            color: #fff;
            z-index: 1035;
            overflow: hidden;
            transition: transform 0.28s ease;
            box-shadow: 24px 0 42px rgba(9, 29, 53, 0.14);
        }

        .ds-sidebar-inner {
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .ds-sidebar-profile {
            padding: 1rem;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.09), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .ds-sidebar-caption {
            margin-bottom: 0.35rem;
            font-size: 0.68rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.54);
        }

        .ds-sidebar-company {
            margin-bottom: 0.65rem;
            font-size: 1.02rem;
            font-weight: 700;
            color: #fff;
        }

        .ds-role-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-bottom: 0.9rem;
        }

        .ds-role-chip {
            padding: 0.32rem 0.6rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.76rem;
            font-weight: 600;
        }

        .ds-search-wrap {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.7rem 0.8rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .ds-search-wrap i {
            color: rgba(255, 255, 255, 0.55);
        }

        .ds-search-wrap input {
            width: 100%;
            padding: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: #fff;
            font-size: 0.9rem;
        }

        .ds-search-wrap input::placeholder {
            color: rgba(255, 255, 255, 0.45);
        }

        .ds-menu-scroll {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.2rem;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.16) transparent;
        }

        .ds-menu-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .ds-menu-scroll::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
        }

        .ds-menu-section + .ds-menu-section {
            margin-top: 1rem;
        }

        .ds-menu-header {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 0.7rem;
            padding: 0 0.35rem;
            color: rgba(255, 255, 255, 0.82);
        }

        .ds-menu-header-mark {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--ds-section-soft);
            color: var(--ds-section-accent);
        }

        .ds-menu-header-copy {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .ds-menu-header-copy strong {
            font-size: 0.82rem;
            font-weight: 700;
        }

        .ds-menu-header-copy span {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .ds-menu-node + .ds-menu-node {
            margin-top: 0.55rem;
        }

        .ds-menu-item,
        .ds-submenu-item {
            width: 100%;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.88rem 0.9rem;
            border-radius: 18px;
            border: 1px solid transparent;
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            transition: transform 0.18s ease, background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .ds-menu-item:hover,
        .ds-submenu-item:hover {
            color: #fff;
            transform: translateX(2px);
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .ds-menu-item.active,
        .ds-submenu-item.active {
            color: #fff;
            background: linear-gradient(135deg, var(--ds-section-soft), rgba(255, 255, 255, 0.06));
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
        }

        .ds-menu-icon,
        .ds-submenu-icon {
            flex: 0 0 auto;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: var(--ds-section-soft);
            color: var(--ds-section-accent);
            font-size: 1rem;
        }

        .ds-submenu-icon {
            width: 32px;
            height: 32px;
            border-radius: 12px;
            font-size: 0.88rem;
        }

        .ds-menu-copy,
        .ds-submenu-copy {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .ds-menu-label,
        .ds-submenu-label {
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .ds-menu-description,
        .ds-submenu-description {
            margin-top: 0.2rem;
            font-size: 0.76rem;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.58);
        }

        .ds-menu-chevron,
        .ds-menu-badge {
            margin-left: auto;
            flex: 0 0 auto;
            align-self: center;
        }

        .ds-menu-chevron {
            color: rgba(255, 255, 255, 0.48);
            transition: transform 0.18s ease;
        }

        .ds-menu-item[aria-expanded="true"] .ds-menu-chevron {
            transform: rotate(90deg);
        }

        .ds-submenu {
            margin-top: 0.45rem;
            padding-left: 1rem;
            border-left: 1px dashed rgba(255, 255, 255, 0.12);
        }

        .ds-submenu-item {
            padding: 0.72rem 0.8rem;
            border-radius: 16px;
        }

        .ds-menu-empty {
            padding: 1rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px dashed rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.84rem;
            text-align: center;
        }

        .ds-sidebar-footer {
            padding: 0.9rem 1rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.72);
        }

        .ds-sidebar-footer strong {
            display: block;
            color: #fff;
            font-size: 0.86rem;
        }

        .ds-sidebar-footer span {
            font-size: 0.76rem;
            color: rgba(255, 255, 255, 0.52);
        }

        .ds-main {
            margin-left: var(--ds-sidebar-w);
            margin-top: var(--ds-topbar-h);
            min-height: calc(100vh - var(--ds-topbar-h));
            padding: 1.6rem;
        }

        .ds-shell {
            min-height: calc(100vh - var(--ds-topbar-h) - 3.2rem);
            padding: 1.4rem;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: var(--ds-shadow);
            backdrop-filter: blur(16px);
        }

        .ds-breadcrumb {
            margin-bottom: 1rem;
            padding: 0;
            background: transparent;
        }

        .ds-footer {
            margin-top: 1.6rem;
            text-align: center;
            color: var(--ds-text-soft);
            font-size: 0.82rem;
        }

        .ds-sidebar-overlay {
            position: fixed;
            inset: var(--ds-topbar-h) 0 0 0;
            background: rgba(8, 17, 29, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 1030;
        }

        .ds-sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        @media (min-width: 992px) {
            .ds-topbar-context {
                display: block;
            }
        }

        @media (max-width: 991.98px) {
            .ds-sidebar {
                transform: translateX(-100%);
            }

            .ds-sidebar.show {
                transform: translateX(0);
            }

            .ds-main {
                margin-left: 0;
                padding: 1rem;
            }

            .ds-shell {
                padding: 1rem;
                border-radius: 22px;
            }
        }
    </style>
    <?= $view->yieldSection('head') ?>
</head>
<body>
<nav class="ds-topbar">
    <button class="btn btn-link text-white d-lg-none p-0" type="button" data-sidebar-toggle aria-label="Abrir menu lateral">
        <i class="bi bi-list fs-3"></i>
    </button>

    <a href="<?= $view->url('/dashboard') ?>" class="brand">
        <span class="ds-brand-mark"><i class="bi bi-shield-lock-fill"></i></span>
        <span class="ds-brand-copy">
            DigiSecure
            <small>Gestion clara para seguridad privada</small>
        </span>
    </a>

    <div class="ds-topbar-right">
        <div class="ds-topbar-context">
            <span class="tenant-name"><i class="bi bi-building me-1"></i><?= $e($user->tenantNombre) ?></span>
            <span class="module-count"><?= $visibleModules ?> modulos visibles segun tu rol</span>
        </div>

        <div class="dropdown">
            <button class="btn btn-link text-white text-decoration-none dropdown-toggle p-0" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i>
                <span class="d-none d-sm-inline"><?= $e($user->nombreCompleto()) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted"><?= $e($user->email) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= $view->url('/perfil') ?>"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                <li><a class="dropdown-item" href="<?= $view->url('/perfil/password') ?>"><i class="bi bi-key me-2"></i>Cambiar contrase&ntilde;a</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="<?= $view->url('/logout') ?>" class="d-inline">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesion
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<aside class="ds-sidebar">
    <div class="ds-sidebar-inner">
        <div class="ds-sidebar-profile">
            <div class="ds-sidebar-caption">Empresa activa</div>
            <div class="ds-sidebar-company"><?= $e($user->tenantNombre) ?></div>

            <div class="ds-sidebar-caption">Tu acceso actual</div>
            <div class="ds-role-list">
                <?php if ($roleLabels === []): ?>
                    <span class="ds-role-chip">Sin roles visibles</span>
                <?php else: ?>
                    <?php foreach (array_slice($roleLabels, 0, 3) as $roleLabel): ?>
                        <span class="ds-role-chip"><?= $e($roleLabel) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($roleLabels) > 3): ?>
                        <span class="ds-role-chip">+<?= count($roleLabels) - 3 ?> mas</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <label class="ds-search-wrap" for="ds-menu-search">
                <i class="bi bi-search"></i>
                <input id="ds-menu-search" type="search" placeholder="Buscar modulo o accion">
            </label>
        </div>

        <div class="ds-menu-scroll">
            <?php foreach ($menuSections as $section): ?>
                <?php
                $meta = $section['meta'];
                $sectionItems = $section['items'];
                ?>
                <section class="ds-menu-section" data-menu-section
                         style="--ds-section-accent: <?= $e($meta['accent']) ?>; --ds-section-soft: <?= $e($meta['soft']) ?>;">
                    <div class="ds-menu-header">
                        <span class="ds-menu-header-mark"><i class="bi <?= $e($meta['icon']) ?>"></i></span>
                        <span class="ds-menu-header-copy">
                            <strong><?= $e($section['label']) ?></strong>
                            <span><?= count($sectionItems) ?> modulo<?= count($sectionItems) === 1 ? '' : 's' ?></span>
                        </span>
                    </div>

                    <?php foreach ($sectionItems as $item): ?>
                        <?php
                        $isActive = $item->isActive($currentPath);
                        $childSearch = [];
                        foreach ($item->children as $child) {
                            if (!$child->isVisibleFor($user->permisos)) {
                                continue;
                            }
                            $childSearch[] = $child->label . ' ' . ($child->description ?? '');
                        }
                        $searchText = strtolower(trim($item->label . ' ' . ($item->description ?? '') . ' ' . implode(' ', $childSearch)));
                        $subId = 'menu-' . md5($section['label'] . $item->label);
                        ?>
                        <div class="ds-menu-node" data-menu-node data-menu-search="<?= $e($searchText) ?>">
                            <?php if ($item->children === []): ?>
                                <a href="<?= $e($item->url !== null ? $view->url($item->url) : '#') ?>"
                                   class="ds-menu-item<?= $isActive ? ' active' : '' ?>">
                                    <span class="ds-menu-icon"><i class="bi <?= $e($item->icon) ?>"></i></span>
                                    <span class="ds-menu-copy">
                                        <span class="ds-menu-label"><?= $e($item->label) ?></span>
                                        <?php if ($item->description !== null): ?>
                                            <span class="ds-menu-description"><?= $e($item->description) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($item->badge !== null): ?>
                                        <span class="badge rounded-pill <?= $e($item->badgeClass) ?> ds-menu-badge"><?= $e($item->badge) ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php else: ?>
                                <a href="#<?= $subId ?>"
                                   class="ds-menu-item<?= $isActive ? ' active' : '' ?>"
                                   data-bs-toggle="collapse"
                                   aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                                    <span class="ds-menu-icon"><i class="bi <?= $e($item->icon) ?>"></i></span>
                                    <span class="ds-menu-copy">
                                        <span class="ds-menu-label"><?= $e($item->label) ?></span>
                                        <?php if ($item->description !== null): ?>
                                            <span class="ds-menu-description"><?= $e($item->description) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="ds-menu-chevron"><i class="bi bi-chevron-right"></i></span>
                                </a>
                                <div class="collapse ds-submenu<?= $isActive ? ' show' : '' ?>" id="<?= $subId ?>">
                                    <?php foreach ($item->children as $child): ?>
                                        <?php if (!$child->isVisibleFor($user->permisos)) continue; ?>
                                        <?php $childActive = $child->isActive($currentPath); ?>
                                        <a href="<?= $e($child->url !== null ? $view->url($child->url) : '#') ?>"
                                           class="ds-submenu-item<?= $childActive ? ' active' : '' ?>">
                                            <span class="ds-submenu-icon"><i class="bi <?= $e($child->icon) ?>"></i></span>
                                            <span class="ds-submenu-copy">
                                                <span class="ds-submenu-label"><?= $e($child->label) ?></span>
                                                <?php if ($child->description !== null): ?>
                                                    <span class="ds-submenu-description"><?= $e($child->description) ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <div class="ds-menu-empty d-none" id="ds-menu-empty">
                No encontramos coincidencias para esa busqueda.
            </div>
        </div>

        <div class="ds-sidebar-footer">
            <strong><i class="bi bi-shield-check me-2"></i>DigiSecure v0.9</strong>
            <span><?= count($user->permisos) ?> permisos activos para esta empresa</span>
        </div>
    </div>
</aside>

<div class="ds-sidebar-overlay" id="sidebar-overlay"></div>

<main class="ds-main">
    <div class="ds-shell">
        <?php $breadcrumb = $view->yieldSection('breadcrumb'); ?>
        <?php if ($breadcrumb !== ''): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb ds-breadcrumb"><?= $breadcrumb ?></ol>
            </nav>
        <?php endif; ?>

        <?= $view->yieldSection('content') ?>

        <footer class="ds-footer">
            &copy; <?= date('Y') ?> DigiSecure | Sistema de gestion de seguridad privada
        </footer>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
(() => {
    const sidebar = document.querySelector('.ds-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    const searchInput = document.getElementById('ds-menu-search');
    const emptyState = document.getElementById('ds-menu-empty');
    const menuSections = Array.from(document.querySelectorAll('[data-menu-section]'));

    const syncOverlay = () => {
        if (!sidebar || !overlay) {
            return;
        }
        overlay.classList.toggle('show', sidebar.classList.contains('show'));
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            if (!sidebar) {
                return;
            }
            sidebar.classList.toggle('show');
            syncOverlay();
        });
    });

    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('show');
        syncOverlay();
    });

    document.querySelectorAll('.ds-sidebar a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                sidebar?.classList.remove('show');
                syncOverlay();
            }
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleNodes = 0;

            menuSections.forEach((section) => {
                let sectionVisible = 0;
                section.querySelectorAll('[data-menu-node]').forEach((node) => {
                    const haystack = (node.getAttribute('data-menu-search') || '').toLowerCase();
                    const matches = query === '' || haystack.includes(query);
                    node.classList.toggle('d-none', !matches);
                    if (matches) {
                        sectionVisible += 1;
                        visibleNodes += 1;
                        const submenu = node.querySelector('.ds-submenu');
                        if (submenu && query !== '') {
                            submenu.classList.add('show');
                        }
                    } else {
                        const submenu = node.querySelector('.ds-submenu');
                        if (submenu && !node.querySelector('.ds-menu-item.active')) {
                            submenu.classList.remove('show');
                        }
                    }
                });
                section.classList.toggle('d-none', sectionVisible === 0);
            });

            if (emptyState) {
                emptyState.classList.toggle('d-none', visibleNodes !== 0 || query === '');
            }
        });
    }
})();
</script>

<?= $view->yieldSection('scripts') ?>
</body>
</html>
