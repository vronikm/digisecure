<?php
/**
 * Fragmento: tabla de usuarios + paginacion. Lo incluye user/index.php y lo
 * devuelve solo UserController::index con "X-Partial: usuarios".
 *
 * Variables: $user, $csrfToken, $usuarios, $rolesByUser, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$badgeMap = [
    'activo' => 'tone-success',
    'inactivo' => 'tone-muted',
    'bloqueado' => 'tone-danger',
    'pendiente_verificacion' => 'tone-warning',
];
$busqueda = (string) ($filters['q'] ?? '');
$params = ['q' => $busqueda, 'estado' => (string) ($filters['estado'] ?? '')];
$hayFiltros = implode('', $params) !== '';
$total = $paginacion->total();
$resumen = $total . ' usuario' . ($total !== 1 ? 's' : '') . ($hayFiltros ? ' encontrado' . ($total !== 1 ? 's' : '') : ' registrado' . ($total !== 1 ? 's' : ''));
?>
<div class="ds-listado" data-total="<?= $total ?>" data-resumen="<?= $e($resumen) ?>">
<?php if ($usuarios === [] && $hayFiltros): ?>
    <?= $view->include('partials/sin-resultados', [
        'busqueda' => $busqueda,
        'urlLimpiar' => $view->url('/usuarios'),
        'sugerencia' => 'Busca por nombres, apellidos, correo, cargo o telefono.',
    ]) ?>
<?php elseif ($usuarios === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-people"></i></div>
            <h3>No hay usuarios para mostrar</h3>
            <p>Empieza dando de alta a tu equipo. Cada usuario podra acceder con su email y rol asignado.</p>
            <?php if ($user->tienePermiso('usuarios.crear')): ?>
            <a href="<?= $view->url('/usuarios/crear') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-plus-lg"></i>Crear el primer usuario
            </a>
            <?php endif ?>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Estado</th>
                    <th>Ultimo login</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u):
                    $estado = $u['estado'];
                    $tone = $badgeMap[$estado] ?? 'tone-muted';
                ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e($u['apellidos']) ?> <?= $e($u['nombres']) ?></div>
                        <?php if (!empty($u['cargo'])): ?>
                        <div class="ds-cell-sub"><?= $e($u['cargo']) ?></div>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-mono"><?= $e($u['email']) ?></td>
                    <td>
                        <?php foreach ($rolesByUser[$u['id']] ?? [] as $r): ?>
                        <span class="ds-chip"><i class="bi bi-shield-check"></i><?= $e($r['nombre']) ?></span>
                        <?php endforeach ?>
                        <?php if (empty($rolesByUser[$u['id']])): ?>
                        <span class="ds-cell-sub">Sin rol asignado</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="ds-badge <?= $tone ?>"><?= $e(ucfirst(str_replace('_', ' ', $estado))) ?></span>
                    </td>
                    <td>
                        <?php if ($u['ultimo_login_at']): ?>
                        <span class="ds-cell-mono"><?= date('d/m/Y H:i', strtotime($u['ultimo_login_at'])) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">Nunca</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <?php if ($user->tienePermiso('usuarios.editar')): ?>
                            <a href="<?= $view->url('/usuarios/' . $u['id'] . '/editar') ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Editar">
                                <i class="bi bi-pencil-square"></i>Editar
                            </a>
                            <?php endif ?>
                            <?php if ($user->tienePermiso('usuarios.bloquear') && $u['id'] !== $user->id): ?>
                                <?php if ($estado === 'activo'): ?>
                                <form method="post" action="<?= $view->url('/usuarios/' . $u['id'] . '/estado') ?>" class="d-inline ds-form-inline"
                                      data-confirm
                                      data-confirm-title="Bloquear usuario"
                                      data-confirm-text="Bloquear a <?= $e($u['nombres']) ?>?"
                                      data-confirm-type="warning"
                                      data-confirm-button="Si, bloquear">
                                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="estado" value="bloqueado">
                                    <button type="submit" class="ds-btn ds-btn-danger ds-btn-sm" title="Bloquear">
                                        <i class="bi bi-lock"></i>Bloquear
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="post" action="<?= $view->url('/usuarios/' . $u['id'] . '/estado') ?>" class="d-inline ds-form-inline"
                                      data-confirm
                                      data-confirm-title="Reactivar usuario"
                                      data-confirm-text="Reactivar a <?= $e($u['nombres']) ?>?"
                                      data-confirm-type="success"
                                      data-confirm-button="Si, reactivar">
                                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="estado" value="activo">
                                    <button type="submit" class="ds-btn ds-btn-outline ds-btn-sm" title="Reactivar">
                                        <i class="bi bi-unlock"></i>Reactivar
                                    </button>
                                </form>
                                <?php endif ?>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $view->include('partials/paginacion', ['paginacion' => $paginacion, 'ruta' => '/usuarios', 'params' => $params, 'nombre' => 'usuarios']) ?>
<?php endif ?>
</div>
