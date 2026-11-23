<?php
/**
 * Pie de un listado: "Mostrando 11-20 de 37" + paginacion compacta.
 * Los enlaces llevan data-remota: ds-tabla-remota.js los carga sin recargar.
 *
 * Variables:
 *   $paginacion \DigiSecure\Kernel\Http\Paginacion
 *   $ruta       ruta del listado (p. ej. "/clientes")
 *   $params     filtros actuales [nombre => valor] (sin page ni por_pagina)
 *   $nombre     (opcional) que se lista, para el aria-label ("clientes")
 */

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$params = is_array($params ?? null) ? $params : [];
$nombre = (string) ($nombre ?? 'registros');
$actual = $paginacion->pagina();
$ultima = $paginacion->totalPaginas();

$urlPagina = static function (int $p) use ($view, $ruta, $params, $paginacion): string {
    $qs = http_build_query(array_filter(
        $params + ['por_pagina' => $paginacion->parametro(), 'page' => $p > 1 ? $p : null],
        static fn($v): bool => $v !== null && $v !== '',
    ));
    return $view->url($ruta) . ($qs !== '' ? '?' . $qs : '');
};
?>
<?php if ($paginacion->total() > 0): ?>
<div class="ds-table-foot ds-listado-foot">
    <span class="ds-listado-rango">
        Mostrando <strong><?= $paginacion->desde() ?>&ndash;<?= $paginacion->hasta() ?></strong> de <strong><?= $paginacion->total() ?></strong>
    </span>
    <?php if ($ultima > 1): ?>
    <nav aria-label="Paginacion de <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= $actual <= 1 ? 'is-disabled' : '' ?>" data-remota
                   href="<?= $urlPagina(max(1, $actual - 1)) ?>" aria-label="Pagina anterior"
                   <?= $actual <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php foreach ($paginacion->ventana() as $p): ?>
            <li>
                <?php if ($p === null): ?>
                <span class="ds-page is-gap" aria-hidden="true">&hellip;</span>
                <?php else: ?>
                <a class="ds-page <?= $p === $actual ? 'is-active' : '' ?>" data-remota
                   href="<?= $urlPagina($p) ?>" aria-label="Pagina <?= $p ?>"
                   <?= $p === $actual ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                <?php endif ?>
            </li>
            <?php endforeach ?>
            <li>
                <a class="ds-page <?= $actual >= $ultima ? 'is-disabled' : '' ?>" data-remota
                   href="<?= $urlPagina(min($ultima, $actual + 1)) ?>" aria-label="Pagina siguiente"
                   <?= $actual >= $ultima ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif ?>
</div>
<?php endif ?>
