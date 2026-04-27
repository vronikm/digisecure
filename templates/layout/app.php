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
    'Plataforma' => ['icon' => 'bi-grid-1x2-fill', 'accent' => '#6f42c1', 'soft' => 'rgba(111, 66, 193, 0.18)'],
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

    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-system.css') ?>">
    <?= $view->yieldSection('head') ?>
</head>
<body>
<?php
    $initials = mb_strtoupper(mb_substr($user->nombres, 0, 1) . mb_substr($user->apellidos, 0, 1));
    $rolPrincipal = $roleLabels[0] ?? 'Usuario';
    $alertas = $view->shared('alertas', ['items' => [], 'total' => 0, 'criticas' => 0]);
    $alertCount = (int) ($alertas['total'] ?? 0);
    $alertCriticas = (int) ($alertas['criticas'] ?? 0);
    $flashes = $view->shared('flashes', []);
    $toastIconMap = [
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];
?>

<?php if (!empty($flashes)): ?>
<div class="ds-toast-stack" id="ds-toast-stack" aria-live="polite" aria-atomic="true">
    <?php foreach ($flashes as $f): ?>
        <?php $tipo = (string) ($f['tipo'] ?? 'info'); ?>
        <div class="ds-toast tone-<?= $e($tipo) ?>" role="status" data-ds-toast>
            <span class="ds-toast-icon"><i class="bi <?= $e($toastIconMap[$tipo] ?? 'bi-info-circle-fill') ?>"></i></span>
            <div class="ds-toast-body">
                <?php if (!empty($f['titulo'])): ?>
                    <div class="ds-toast-title"><?= $e((string) $f['titulo']) ?></div>
                <?php endif; ?>
                <div><?= $e((string) $f['mensaje']) ?></div>
            </div>
            <button type="button" class="ds-toast-close" aria-label="Cerrar" data-ds-toast-close>&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<nav class="ds-topbar">
    <button class="btn btn-link text-white d-lg-none p-0" type="button" data-sidebar-toggle aria-label="Abrir menu lateral">
        <i class="bi bi-list fs-3"></i>
    </button>

    <a href="<?= $view->url('/dashboard') ?>" class="brand">
        <span class="ds-brand-mark"><i class="bi bi-shield-lock-fill"></i></span>
        <span class="ds-brand-copy">
            DigiSecure
            <small>Seguridad privada</small>
        </span>
    </a>

    <div class="ds-topbar-right">
        <div class="ds-topbar-tenant">
            <span class="tt-icon"><i class="bi bi-building"></i></span>
            <span>
                <span class="tt-name"><?= $e($user->tenantNombre) ?></span>
                <span class="tt-sub d-block"><?= $e($rolPrincipal) ?></span>
            </span>
        </div>

        <span class="ds-topbar-sep"></span>

        <div class="dropdown">
            <button class="ds-bell-btn dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="Centro de alertas">
                <i class="bi bi-bell"></i>
                <?php if ($alertCount > 0): ?>
                    <span class="ds-bell-badge<?= $alertCriticas === 0 ? ' is-high' : '' ?>">
                        <?= $alertCount > 9 ? '9+' : (string) $alertCount ?>
                    </span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end ds-bell-menu">
                <div class="ds-bm-header">
                    <span class="ds-bm-title"><i class="bi bi-bell me-1"></i>Centro de alertas</span>
                    <span class="ds-bm-count">
                        <?php if ($alertCount === 0): ?>
                            Sin pendientes
                        <?php elseif ($alertCriticas > 0): ?>
                            <?= $alertCriticas ?> urgente<?= $alertCriticas === 1 ? '' : 's' ?>
                        <?php else: ?>
                            <?= $alertCount ?> item<?= $alertCount === 1 ? '' : 's' ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="ds-bm-body">
                    <?php if ($alertCount === 0): ?>
                        <div class="ds-bell-empty">
                            <i class="bi bi-check2-circle"></i>
                            Todo en orden. No hay pendientes por ahora.
                        </div>
                    <?php else: ?>
                        <?php foreach ($alertas['items'] as $item): ?>
                            <a href="<?= $view->url((string) $item['url']) ?>" class="ds-bell-item">
                                <span class="ds-bi-icon severidad-<?= $e((string) $item['severidad']) ?>">
                                    <i class="bi <?= $e((string) $item['icon']) ?>"></i>
                                </span>
                                <span class="ds-bi-body">
                                    <span class="ds-bi-title d-block"><?= $e((string) $item['titulo']) ?></span>
                                    <span class="ds-bi-detail"><?= $e((string) $item['detalle']) ?></span>
                                </span>
                                <?php if (!empty($item['cantidad']) && (int) $item['cantidad'] > 1): ?>
                                    <span class="ds-bi-qty"><?= (int) $item['cantidad'] > 99 ? '99+' : (int) $item['cantidad'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button class="ds-user-btn dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <span class="ds-user-avatar"><?= $e($initials) ?></span>
                <span class="d-none d-sm-inline"><?= $e($user->nombres) ?></span>
                <i class="bi bi-chevron-down ds-user-caret"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end ds-user-menu">
                <div class="ds-dm-header">
                    <div class="ds-dm-name"><?= $e($user->nombreCompleto()) ?></div>
                    <div class="ds-dm-email"><?= $e($user->email) ?></div>
                </div>
                <a class="dropdown-item" href="<?= $view->url('/perfil') ?>">
                    <i class="bi bi-person me-2 text-muted"></i>Mi perfil
                </a>
                <a class="dropdown-item" href="<?= $view->url('/perfil/password') ?>">
                    <i class="bi bi-key me-2 text-muted"></i>Cambiar contrase&ntilde;a
                </a>
                <div class="ds-dm-footer">
                    <form method="POST" action="<?= $view->url('/logout') ?>">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesion
                        </button>
                    </form>
                </div>
            </div>
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
    const toasts = document.querySelectorAll('[data-ds-toast]');
    toasts.forEach((toast) => {
        const dismiss = () => {
            if (toast.classList.contains('is-hiding')) return;
            toast.classList.add('is-hiding');
            setTimeout(() => toast.remove(), 220);
        };
        toast.querySelector('[data-ds-toast-close]')?.addEventListener('click', dismiss);
        setTimeout(dismiss, 5500);
    });
})();
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
