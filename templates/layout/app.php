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
use DigiSecure\Security\Auth\AuthenticatedUser;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */
/** @var string $csrfToken */
/** @var string $currentPath */

$user = $user ?? $view->shared('user');
$csrfToken = (string) ($csrfToken ?? $view->shared('csrfToken', ''));
$currentPath = (string) ($currentPath ?? $view->shared('currentPath', '/'));

if (!$user instanceof AuthenticatedUser) {
    $user = new AuthenticatedUser(
        id: '',
        tenantId: '',
        email: '',
        nombres: 'Usuario',
        apellidos: '',
        estado: 'inactivo',
        tenantNombre: '-',
        tenantEstado: 'inactivo',
        permisos: [],
        roles: [],
    );
}

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

$normalizeMenuSearch = static function (array $parts): string {
    $raw = trim(implode(' ', array_filter(
        array_map(static fn(mixed $part): string => trim((string) $part), $parts),
        static fn(string $part): bool => $part !== '',
    )));

    $lower = function_exists('mb_strtolower')
        ? mb_strtolower($raw, 'UTF-8')
        : strtolower($raw);

    $ascii = strtr($lower, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ]);

    return trim((string) preg_replace('/\s+/', ' ', $lower . ' ' . $ascii));
};
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

    <link rel="stylesheet" href="<?= $view->url('/assets/css/ds-system.css') ?>?v=20.0">
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
                    data-bs-toggle="dropdown" aria-expanded="false"
                    aria-label="Menu de usuario"
                    title="<?= $e($user->nombreCompleto()) ?>">
                <span class="ds-user-avatar"><?= $e($initials) ?></span>
                <i class="bi bi-chevron-down ds-user-caret"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end ds-user-menu">
                <div class="ds-dm-header">
                    <span class="ds-dm-avatar"><?= $e($initials) ?></span>
                    <div class="ds-dm-info">
                        <div class="ds-dm-name"><?= $e($user->nombreCompleto()) ?></div>
                        <div class="ds-dm-email"><?= $e($user->email) ?></div>
                        <span class="ds-dm-role"><i class="bi bi-shield-check"></i><?= $e($rolPrincipal) ?></span>
                    </div>
                </div>
                <div class="ds-dm-section">Mi cuenta</div>
                <a class="dropdown-item" href="<?= $view->url('/perfil') ?>">
                    <i class="bi bi-person"></i>
                    <span>Mi perfil</span>
                    <i class="bi bi-chevron-right item-arrow"></i>
                </a>
                <a class="dropdown-item" href="<?= $view->url('/perfil/password') ?>">
                    <i class="bi bi-key"></i>
                    <span>Cambiar contrase&ntilde;a</span>
                    <i class="bi bi-chevron-right item-arrow"></i>
                </a>
                <div class="ds-dm-footer">
                    <form method="POST" action="<?= $view->url('/logout') ?>" class="ds-form-inline">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                        <button type="submit" class="dropdown-item">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Cerrar sesi&oacute;n</span>
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
                <input id="ds-menu-search" type="search" placeholder="Buscar modulo o accion" autocomplete="off">
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
                        <span class="ds-menu-header-copy">
                            <strong><?= $e($section['label']) ?></strong>
                            <span><?= count($sectionItems) ?> modulo<?= count($sectionItems) === 1 ? '' : 's' ?></span>
                        </span>
                    </div>

                    <?php foreach ($sectionItems as $item): ?>
                        <?php
                        $isActive = $item->isActive($currentPath);
                        $isExpanded = $item->children !== [] && $isActive;
                        $visibleChildren = [];
                        $childSearch = [];
                        foreach ($item->children as $child) {
                            if (!$child->isVisibleFor($user->permisos)) {
                                continue;
                            }
                            $childSearchText = $normalizeMenuSearch([
                                $child->label,
                                $child->description ?? '',
                                $child->url ?? '',
                                implode(' ', $child->permisos),
                            ]);
                            $visibleChildren[] = [
                                'item' => $child,
                                'search' => $childSearchText,
                            ];
                            $childSearch[] = $childSearchText;
                        }
                        $ownSearchText = $normalizeMenuSearch([
                            $section['label'],
                            $item->label,
                            $item->description ?? '',
                            $item->url ?? '',
                            implode(' ', $item->permisos),
                        ]);
                        $searchText = $normalizeMenuSearch([$ownSearchText, implode(' ', $childSearch)]);
                        $subId = 'menu-' . md5($section['label'] . $item->label);
                        ?>
                        <div class="ds-menu-node"
                             data-menu-node
                             data-menu-search="<?= $e($searchText) ?>"
                             data-menu-search-own="<?= $e($ownSearchText) ?>">
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
                                   aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>">
                                    <span class="ds-menu-icon"><i class="bi <?= $e($item->icon) ?>"></i></span>
                                    <span class="ds-menu-copy">
                                        <span class="ds-menu-label"><?= $e($item->label) ?></span>
                                    </span>
                                    <span class="ds-menu-chevron"><i class="bi bi-chevron-right"></i></span>
                                </a>
                                <div class="collapse ds-submenu<?= $isExpanded ? ' show' : '' ?>" id="<?= $subId ?>">
                                    <?php foreach ($visibleChildren as $childEntry): ?>
                                        <?php
                                        $child = $childEntry['item'];
                                        $childSearchText = $childEntry['search'];
                                        ?>
                                        <?php $childActive = $child->isActive($currentPath); ?>
                                        <a href="<?= $e($child->url !== null ? $view->url($child->url) : '#') ?>"
                                           class="ds-submenu-item<?= $childActive ? ' active' : '' ?>"
                                           data-menu-child
                                           data-menu-search="<?= $e($childSearchText) ?>">
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

            <div class="ds-menu-empty is-menu-hidden" id="ds-menu-empty">
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
    <?php $breadcrumb = $view->yieldSection('breadcrumb'); ?>
    <?php if ($breadcrumb !== ''): ?>
        <nav aria-label="breadcrumb" class="ds-breadcrumb-outer">
            <ol class="breadcrumb ds-breadcrumb"><?= $breadcrumb ?></ol>
        </nav>
    <?php endif; ?>

    <div class="ds-shell">
        <div class="ds-content-wrap">
            <?= $view->yieldSection('content') ?>
        </div>

        <footer class="ds-footer">
            &copy; <?= date('Y') ?> DigiSecure | Sistema de gestion de seguridad privada
        </footer>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= $view->url('/assets/js/ds-menu-search.js') ?>?v=1.1" defer></script>
<script>
(() => {
    const toasts = document.querySelectorAll('[data-ds-toast]');
    toasts.forEach((toast) => {
        const dismiss = () => {
            if (toast.classList.contains('is-hiding')) return;
            toast.classList.add('is-hiding');
            setTimeout(() => toast.remove(), 220);
        };
        const closeButton = toast.querySelector('[data-ds-toast-close]');
        if (closeButton) {
            closeButton.addEventListener('click', dismiss);
        }
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

    if (overlay) {
        overlay.addEventListener('click', () => {
            if (sidebar) {
                sidebar.classList.remove('show');
            }
            syncOverlay();
        });
    }

    document.querySelectorAll('.ds-sidebar a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (link.matches('[data-bs-toggle="collapse"]')) {
                return;
            }

            if (window.innerWidth < 992 && sidebar) {
                sidebar.classList.remove('show');
                syncOverlay();
            }
        });
    });

    if (!searchInput) {
        syncOverlay();
    } else {
        const normalizeSearch = (value) => {
            const raw = String(value || '').trim().toLowerCase();
            try {
                return raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            } catch (error) {
                return raw;
            }
        };

        const setSubmenuOpen = (node, open) => {
            const submenu = node.querySelector('.ds-submenu');
            const trigger = node.querySelector('.ds-menu-item[data-bs-toggle="collapse"]');
            if (!submenu) {
                return;
            }

            submenu.classList.toggle('show', open);
            if (trigger) {
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        };

        const resetNode = (node) => {
            node.classList.remove('is-menu-hidden');
            node.querySelectorAll('[data-menu-child]').forEach((child) => {
                child.classList.remove('is-menu-hidden');
            });
            setSubmenuOpen(node, Boolean(node.querySelector('.ds-menu-item.active, .ds-submenu-item.active')));
        };

        const runMenuSearch = () => {
            const query = normalizeSearch(searchInput.value);
            let visibleNodes = 0;

            menuSections.forEach((section) => {
                let sectionVisible = 0;
                section.querySelectorAll('[data-menu-node]').forEach((node) => {
                    if (query === '') {
                        resetNode(node);
                        sectionVisible += 1;
                        visibleNodes += 1;
                        return;
                    }

                    const ownText = normalizeSearch(node.getAttribute('data-menu-search-own'));
                    const fullText = normalizeSearch(node.getAttribute('data-menu-search'));
                    const childLinks = Array.from(node.querySelectorAll('[data-menu-child]'));
                    const ownMatches = ownText.includes(query);
                    let visibleChildren = 0;

                    childLinks.forEach((child) => {
                        const childText = normalizeSearch(child.getAttribute('data-menu-search'));
                        const childMatches = ownMatches || childText.includes(query);
                        child.classList.toggle('is-menu-hidden', !childMatches);
                        if (childMatches) {
                            visibleChildren += 1;
                        }
                    });

                    const matches = childLinks.length > 0
                        ? ownMatches || visibleChildren > 0
                        : fullText.includes(query);

                    node.classList.toggle('is-menu-hidden', !matches);
                    if (matches) {
                        sectionVisible += 1;
                        visibleNodes += 1;
                        setSubmenuOpen(node, childLinks.length > 0);
                    } else {
                        setSubmenuOpen(node, false);
                    }
                });
                section.classList.toggle('is-menu-hidden', sectionVisible === 0);
            });

            if (emptyState) {
                emptyState.classList.toggle('is-menu-hidden', visibleNodes !== 0 || query === '');
            }
        };

        searchInput.addEventListener('input', runMenuSearch);
        searchInput.addEventListener('keyup', runMenuSearch);
        searchInput.addEventListener('change', runMenuSearch);
        searchInput.addEventListener('search', runMenuSearch);
    }
})();
</script>

<?= $view->yieldSection('scripts') ?>
</body>
</html>
