<?php
/**
 * Layout principal AdminLTE 4 / Bootstrap 5.
 *
 * Variables esperadas:
 *   $user       AuthenticatedUser
 *   $csrfToken  string
 *   $currentPath string
 *   $_sections  array (inyectadas por ViewRenderer)
 *
 * Secciones disponibles:
 *   content     Contenido principal
 *   title       Título de la página (default: 'DigiSecure')
 *   head        CSS/JS extra en <head>
 *   scripts     JS extra antes de </body>
 *   breadcrumb  Breadcrumb HTML
 */

use DigiSecure\Kernel\View\Menu\MenuBuilder;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */
/** @var string $csrfToken */
/** @var string $currentPath */

$e = ViewRenderer::e(...);
$pageTitle = $view->yieldSection('title', 'DigiSecure');
$menuItems = MenuBuilder::build();
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <title><?= $e($pageTitle) ?></title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1p8Rbdc5E3QYkQ5jU3TmgE7pC7dMf1" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --ds-primary: #1a3a5c;
            --ds-primary-dark: #0f2744;
            --ds-accent: #2e86de;
            --ds-accent-hover: #1e6fbf;
            --ds-sidebar-w: 260px;
            --ds-topbar-h: 56px;
            --ds-success: #27ae60;
            --ds-warning: #f39c12;
            --ds-danger: #e74c3c;
            --ds-info: #3498db;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f4f6f9;
            overflow-x: hidden;
        }

        /* ========== TOPBAR ========== */
        .ds-topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--ds-topbar-h);
            background: var(--ds-primary);
            color: #fff;
            z-index: 1040;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
        }
        .ds-topbar .brand {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: .5px;
            white-space: nowrap;
        }
        .ds-topbar .brand i { font-size: 1.3rem; }
        .ds-topbar-right { margin-left: auto; display: flex; align-items: center; gap: .75rem; }
        .ds-topbar-right .tenant-name { font-size: .8rem; opacity: .7; }
        .ds-topbar-right .user-name { font-size: .85rem; }

        /* ========== SIDEBAR ========== */
        .ds-sidebar {
            position: fixed;
            top: var(--ds-topbar-h);
            left: 0;
            bottom: 0;
            width: var(--ds-sidebar-w);
            background: var(--ds-primary-dark);
            color: rgba(255,255,255,.7);
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1030;
            transition: transform .25s ease;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,.15) transparent;
        }
        .ds-sidebar::-webkit-scrollbar { width: 5px; }
        .ds-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 3px; }

        /* Menu header */
        .ds-menu-header {
            padding: .75rem 1rem .25rem;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,.35);
            font-weight: 700;
        }

        /* Menu items */
        .ds-menu-item {
            display: flex;
            align-items: center;
            padding: .6rem 1rem;
            color: rgba(255,255,255,.7);
            text-decoration: none;
            font-size: .875rem;
            transition: background .15s, color .15s;
            border-left: 3px solid transparent;
        }
        .ds-menu-item:hover {
            background: rgba(255,255,255,.06);
            color: #fff;
        }
        .ds-menu-item.active {
            background: rgba(46,134,222,.15);
            color: #fff;
            border-left-color: var(--ds-accent);
        }
        .ds-menu-item i {
            width: 22px;
            font-size: 1rem;
            margin-right: .65rem;
            text-align: center;
            flex-shrink: 0;
        }
        .ds-menu-item .arrow {
            margin-left: auto;
            font-size: .7rem;
            transition: transform .2s;
        }
        .ds-menu-item[aria-expanded="true"] .arrow { transform: rotate(90deg); }

        .ds-submenu {
            background: rgba(0,0,0,.12);
        }
        .ds-submenu .ds-menu-item {
            padding-left: 2.75rem;
            font-size: .825rem;
        }
        .ds-submenu .ds-menu-item i { font-size: .85rem; }

        .ds-menu-badge {
            margin-left: auto;
            font-size: .7rem;
            padding: .15rem .45rem;
            border-radius: 4px;
        }

        /* ========== MAIN CONTENT ========== */
        .ds-main {
            margin-left: var(--ds-sidebar-w);
            margin-top: var(--ds-topbar-h);
            min-height: calc(100vh - var(--ds-topbar-h));
            padding: 1.5rem;
        }

        /* ========== CARDS ========== */
        .ds-card-stat { border-left: 4px solid var(--ds-accent); }
        .ds-card-stat.success { border-left-color: var(--ds-success); }
        .ds-card-stat.warning { border-left-color: var(--ds-warning); }
        .ds-card-stat.danger { border-left-color: var(--ds-danger); }
        .ds-card-stat.info { border-left-color: var(--ds-info); }

        /* ========== BREADCRUMB ========== */
        .ds-breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 991.98px) {
            .ds-sidebar { transform: translateX(-100%); }
            .ds-sidebar.show { transform: translateX(0); }
            .ds-main { margin-left: 0; }
        }

        /* ========== FOOTER ========== */
        .ds-footer {
            padding: 1rem 1.5rem;
            font-size: .8rem;
            color: #999;
            text-align: center;
            border-top: 1px solid #e9ecef;
            margin-top: 2rem;
        }
    </style>
    <?= $view->yieldSection('head') ?>
</head>
<body>

<!-- ========== TOPBAR ========== -->
<nav class="ds-topbar">
    <!-- Hamburger (mobile) -->
    <button class="btn btn-link text-white d-lg-none me-2 p-0" type="button"
            onclick="document.querySelector('.ds-sidebar').classList.toggle('show')">
        <i class="bi bi-list fs-4"></i>
    </button>

    <span class="brand">
        <i class="bi bi-shield-lock-fill me-2"></i>DigiSecure
    </span>

    <div class="ds-topbar-right">
        <span class="tenant-name d-none d-md-inline">
            <i class="bi bi-building me-1"></i><?= $e($user->tenantNombre) ?>
        </span>

        <!-- User dropdown -->
        <div class="dropdown">
            <button class="btn btn-link text-white text-decoration-none dropdown-toggle p-0" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i>
                <span class="user-name d-none d-sm-inline"><?= $e($user->nombreCompleto()) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted"><?= $e($user->email) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= $view->url('/perfil') ?>"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                <li><a class="dropdown-item" href="<?= $view->url('/perfil/password') ?>"><i class="bi bi-key me-2"></i>Cambiar contraseña</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="<?= $view->url('/logout') ?>" class="d-inline">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ========== SIDEBAR ========== -->
<aside class="ds-sidebar">
    <div class="pt-2 pb-3">
    <?php foreach ($menuItems as $entry): ?>
        <?php if ($entry['type'] === 'header'): ?>
            <div class="ds-menu-header"><?= $e($entry['label']) ?></div>
        <?php elseif ($entry['type'] === 'item'):
            /** @var \DigiSecure\Kernel\View\Menu\MenuItem $item */
            $item = $entry['item'];
            if (!$item->isVisibleFor($user->permisos)) continue;
            $isActive = $item->isActive($currentPath);
        ?>
            <?php if ($item->children === []): ?>
                <!-- Item hoja -->
                <a href="<?= $e($item->url !== null ? $view->url($item->url) : '#') ?>"
                   class="ds-menu-item<?= $isActive ? ' active' : '' ?>">
                    <i class="bi <?= $e($item->icon) ?>"></i>
                    <?= $e($item->label) ?>
                    <?php if ($item->badge !== null): ?>
                        <span class="ds-menu-badge <?= $e($item->badgeClass) ?>"><?= $e($item->badge) ?></span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <!-- Item con submenú -->
                <?php
                    $subId = 'sub-' . md5($item->label);
                    $hasVisibleChild = $item->hasVisibleChildren($user->permisos);
                    if (!$hasVisibleChild) continue;
                ?>
                <a href="#<?= $subId ?>" class="ds-menu-item<?= $isActive ? ' active' : '' ?>"
                   data-bs-toggle="collapse" aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                    <i class="bi <?= $e($item->icon) ?>"></i>
                    <?= $e($item->label) ?>
                    <i class="bi bi-chevron-right arrow"></i>
                </a>
                <div class="collapse ds-submenu<?= $isActive ? ' show' : '' ?>" id="<?= $subId ?>">
                    <?php foreach ($item->children as $child):
                        if (!$child->isVisibleFor($user->permisos)) continue;
                        $childActive = $child->isActive($currentPath);
                    ?>
                        <a href="<?= $e($child->url !== null ? $view->url($child->url) : '#') ?>"
                           class="ds-menu-item<?= $childActive ? ' active' : '' ?>">
                            <i class="bi <?= $e($child->icon) ?>"></i>
                            <?= $e($child->label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>

    <!-- Sidebar footer -->
    <div class="mt-auto p-3 border-top border-secondary border-opacity-25" style="font-size:.75rem;">
        <div class="text-white-50">
            <i class="bi bi-shield-check me-1"></i>DigiSecure v0.9
        </div>
    </div>
</aside>

<!-- ========== MAIN CONTENT ========== -->
<main class="ds-main">
    <!-- Breadcrumb -->
    <?php $breadcrumb = $view->yieldSection('breadcrumb'); ?>
    <?php if ($breadcrumb !== ''): ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb ds-breadcrumb"><?= $breadcrumb ?></ol>
        </nav>
    <?php endif; ?>

    <!-- Page Content -->
    <?= $view->yieldSection('content') ?>

    <!-- Footer -->
    <footer class="ds-footer">
        &copy; <?= date('Y') ?> DigiSecure — Sistema de Gestión de Seguridad Privada
    </footer>
</main>

<!-- Overlay para sidebar mobile -->
<div class="d-lg-none" onclick="document.querySelector('.ds-sidebar').classList.remove('show')"
     style="display:none" id="sidebar-overlay"></div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
// Overlay sidebar mobile
const sidebar = document.querySelector('.ds-sidebar');
const overlay = document.getElementById('sidebar-overlay');
if (sidebar && overlay) {
    const obs = new MutationObserver(() => {
        overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
    });
    obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
}
</script>

<?= $view->yieldSection('scripts') ?>
</body>
</html>
