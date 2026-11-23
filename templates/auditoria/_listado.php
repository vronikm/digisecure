<?php
/**
 * Fragmento: tabla de auditoria + paginacion. Lo incluye auditoria/index.php y
 * lo devuelve solo AuditoriaController::index con "X-Partial: auditoria".
 *
 * Variables: $eventos, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$nivelTone = static fn(string $nivel) => match ($nivel) {
    'critical' => 'tone-danger',
    'warning'  => 'tone-warning',
    'info'     => 'tone-info',
    default    => 'tone-muted',
};
$busqueda = (string) ($filters['q'] ?? '');
$params = [];
foreach (['q', 'evento', 'nivel', 'desde', 'hasta', 'usuario_id', 'entidad_tipo'] as $clave) {
    $params[$clave] = (string) ($filters[$clave] ?? '');
}
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' evento' . ($total !== 1 ? 's' : '') . ($hayFiltros ? ' encontrado' . ($total !== 1 ? 's' : '') : ' registrado' . ($total !== 1 ? 's' : ''));
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($eventos === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/auditoria'),
        'sugerencia' => 'Busca por evento (p. ej. auth.login), tipo o id de entidad, o por correo o nombre del usuario.',
    ]) ?>
<?php elseif ($eventos === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-journal-text"></i></div>
            <h3>No hay eventos en auditoria</h3>
            <p>Los eventos se registran automaticamente cuando los usuarios realizan acciones en el sistema.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Nivel</th>
                    <th>Evento</th>
                    <th>Usuario</th>
                    <th>Entidad</th>
                    <th>IP</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td><span class="ds-cell-mono"><?= date('d/m/Y H:i:s', strtotime($ev['created_at'])) ?></span></td>
                    <td><span class="ds-badge <?= $nivelTone($ev['nivel']) ?>"><?= $e(ucfirst($ev['nivel'])) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e($ev['evento']) ?></span></td>
                    <td>
                        <?php if (!empty($ev['usuario_email'])): ?>
                        <div class="ds-cell-name"><?= $e($ev['usuario_apellidos'] ?? '') ?> <?= $e($ev['usuario_nombres'] ?? '') ?></div>
                        <div class="ds-cell-sub"><?= $e($ev['usuario_email']) ?></div>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($ev['entidad_tipo'])): ?>
                        <span class="ds-chip"><i class="bi bi-tag"></i><?= $e($ev['entidad_tipo']) ?></span>
                        <div class="ds-cell-sub" style="margin-top:.2rem;"><span class="ds-cell-mono"><?= $e($ev['entidad_id'] ?? '') ?></span></div>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e($ev['ip_text'] ?? '-') ?></span></td>
                    <td>
                        <?php if (!empty($ev['detalles_json'])): ?>
                        <details>
                            <summary style="cursor:pointer; color:#2563eb; font-size:.78rem; font-weight:600;">Ver JSON</summary>
                            <pre style="background:#0f172a; color:#e2e8f0; padding:.6rem .75rem; border-radius:8px; max-width:400px; max-height:240px; overflow:auto; font-size:.72rem; margin:.4rem 0 0; white-space:pre-wrap; word-break:break-word;"><?= $e(json_encode(json_decode((string) $ev['detalles_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </details>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/auditoria', 'params' => $params, 'nombre' => 'eventos de auditoria']) ?>
<?php endif ?>
</div>
