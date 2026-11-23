<?php
/**
 * Fragmento: tabla de notificaciones + paginacion. Lo incluye
 * notificaciones/index.php y lo devuelve solo NotificacionController::index
 * con "X-Partial: notificaciones".
 *
 * Variables: $notificaciones, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$estadoTone = static fn(string $estado) => match ($estado) {
    'PENDIENTE' => 'tone-muted',
    'ENVIADA'   => 'tone-success',
    'FALLIDA'   => 'tone-danger',
    'CANCELADA' => 'tone-warning',
    default     => 'tone-muted',
};
$canalIcon = static fn(string $canal) => match ($canal) {
    'EMAIL'    => 'bi-envelope',
    'WHATSAPP' => 'bi-whatsapp',
    'SMS'      => 'bi-chat-dots',
    'PUSH'     => 'bi-bell',
    default    => 'bi-send',
};
$busqueda = (string) ($filters['q'] ?? '');
$params = [];
foreach (['q', 'estado', 'canal', 'evento'] as $clave) {
    $params[$clave] = (string) ($filters[$clave] ?? '');
}
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' notificacion' . ($total !== 1 ? 'es' : '') . ($hayFiltros ? ' encontrada' . ($total !== 1 ? 's' : '') : ' en total');
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($notificaciones === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/notificaciones'),
        'sugerencia' => 'Busca por asunto, destinatario (correo o nombre), evento, canal o tipo de entidad.',
    ]) ?>
<?php elseif ($notificaciones === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-bell-slash"></i></div>
            <h3>No hay notificaciones</h3>
            <p>Las notificaciones se generan automaticamente al ocurrir eventos del sistema (vencimientos, registros, etc.).</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Evento</th>
                    <th>Canal</th>
                    <th>Destinatario</th>
                    <th>Asunto</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Intentos</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notificaciones as $n): ?>
                <tr>
                    <td><span class="ds-cell-mono"><?= $e(substr((string) $n['created_at'], 0, 16)) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $n['evento']) ?></span></td>
                    <td><span class="ds-chip"><i class="bi <?= $canalIcon((string) $n['canal']) ?>"></i><?= $e((string) $n['canal']) ?></span></td>
                    <td class="ds-cell-mono"><?= $e((string) ($n['destinatario_email'] ?? $n['destinatario_telefono'] ?? '-')) ?></td>
                    <td>
                        <div style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e((string) $n['asunto']) ?>">
                            <?= $e((string) $n['asunto']) ?>
                        </div>
                    </td>
                    <td><span class="ds-badge <?= $estadoTone((string) $n['estado']) ?>"><?= $e((string) $n['estado']) ?></span></td>
                    <td class="ds-cell-end">
                        <span class="ds-cell-sub"><?= (int) $n['intentos'] ?>/<?= (int) $n['max_intentos'] ?></span>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/notificaciones/' . $e((string) $n['id'])) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle">
                                Ver
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/notificaciones', 'params' => $params, 'nombre' => 'notificaciones']) ?>
<?php endif ?>
</div>
