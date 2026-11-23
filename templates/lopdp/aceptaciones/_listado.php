<?php
/**
 * Fragmento: tabla de aceptaciones de la politica LOPDP + paginacion. Lo incluye
 * lopdp/aceptaciones/index.php y lo devuelve solo
 * LopdpController::listarAceptacionesPolitica con "X-Partial: aceptaciones".
 *
 * Variables: $aceptaciones, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$formatDateTime = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : $value;
};
$busqueda = (string) ($filters['q'] ?? '');
$params = [];
foreach (['q', 'documento_lopdp', 'desde', 'hasta'] as $clave) {
    $params[$clave] = (string) ($filters[$clave] ?? '');
}
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' registro' . ($total !== 1 ? 's' : '') . ' encontrado' . ($total !== 1 ? 's' : '');
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($aceptaciones === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/lopdp/aceptaciones'),
        'sugerencia' => 'Busca por nombres, apellidos, documento, correo o celular del aspirante.',
    ]) ?>
<?php elseif ($aceptaciones === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-check"></i></div>
            <h3>Sin aceptaciones registradas</h3>
            <p>Cuando un aspirante acepte la Politica de Privacidad desde el modal de registro, aparecera aqui.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Aspirante</th>
                    <th>Aceptacion politica</th>
                    <th>Origen</th>
                    <th>Autorizacion LOPDP</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aceptaciones as $row):
                    $nombre = trim((string) ($row['nombres'] ?? '') . ' ' . (string) ($row['apellidos'] ?? ''));
                    $autorizacionSubida = !empty($row['lopdp_autorizacion_subida_at']);
                ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e($nombre !== '' ? $nombre : 'Sin nombre') ?></div>
                        <div class="ds-cell-sub"><?= $e((string) ($row['correo'] ?? '')) ?></div>
                        <div class="ds-cell-sub">
                            <span class="ds-cell-mono"><?= $e(strtoupper((string) ($row['tipo_documento'] ?? ''))) ?> <?= $e((string) ($row['documento'] ?? '')) ?></span>
                        </div>
                    </td>
                    <td>
                        <div><span class="ds-cell-mono"><?= $e($formatDateTime($row['politicas_aceptadas_at'] ?? $row['created_at'] ?? '')) ?></span></div>
                        <div class="ds-cell-sub">Version: <?= $e((string) ($row['politicas_version'] ?? 'No registrada')) ?></div>
                        <div class="ds-lopdp-meta">
                            <span><i class="bi bi-globe2"></i> IP <?= $e((string) ($row['politicas_aceptadas_ip_texto'] ?? '-')) ?></span>
                            <span><i class="bi bi-envelope-check"></i> <?= ((int) ($row['email_verificado'] ?? 0)) === 1 ? 'Correo verificado' : 'Correo pendiente' ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="ds-cell-name"><?= $e((string) ($row['politicas_aceptadas_desde'] ?? '-')) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) ($row['politicas_aceptadas_ruta'] ?? '-')) ?></div>
                        <?php if (!empty($row['politicas_aceptadas_user_agent'])): ?>
                        <div class="ds-lopdp-user-agent" title="<?= $e((string) $row['politicas_aceptadas_user_agent']) ?>">
                            <?= $e((string) $row['politicas_aceptadas_user_agent']) ?>
                        </div>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($autorizacionSubida): ?>
                            <span class="ds-badge tone-success">Subida</span>
                            <div class="ds-cell-sub" style="margin-top:.35rem;">
                                <?= $e($formatDateTime($row['lopdp_autorizacion_subida_at'] ?? '')) ?>
                            </div>
                            <div class="ds-cell-sub" title="<?= $e((string) ($row['lopdp_autorizacion_archivo_nombre'] ?? '')) ?>">
                                <?= $e((string) ($row['lopdp_autorizacion_archivo_nombre'] ?? 'Archivo registrado')) ?>
                            </div>
                        <?php else: ?>
                            <span class="ds-badge tone-warning">Pendiente</span>
                            <div class="ds-cell-sub" style="margin-top:.35rem;">Aun no sube el PDF de autorizacion.</div>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="ds-badge <?= (string) ($row['estado'] ?? '') === 'aspirante' ? 'tone-info' : 'tone-muted' ?>">
                            <?= $e((string) ($row['estado'] ?? 'sin_estado')) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/lopdp/aceptaciones', 'params' => $params, 'nombre' => 'aceptaciones']) ?>
<?php endif ?>
</div>
