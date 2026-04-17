<?php
/**
 * Detalle de un tenant (Fase 13).
 *
 * Variables: $user, $csrfToken, $currentPath, $tenant, $uso,
 *            $usuarios, $ciclos, $registros, $planes
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$t = $tenant;
$storageMb = round(((int) ($uso['storage_bytes'] ?? 0)) / (1024 * 1024), 1);
$maxStorage = (int) ($t['max_storage_mb'] ?? 0);
$maxUsuarios = (int) ($t['max_usuarios'] ?? 0);
$maxGuardias = (int) ($t['max_guardias'] ?? 0);
$usrAct = (int) ($uso['usuarios_activos'] ?? 0);
$fichas = (int) ($uso['total_fichas'] ?? 0);

$estadoBadge = [
    'activo'     => 'bg-success',
    'pendiente'  => 'bg-warning text-dark',
    'suspendido' => 'bg-danger',
    'cancelado'  => 'bg-secondary',
    'inactivo'   => 'bg-secondary',
    'bloqueado'  => 'bg-danger',
    'pendiente_verificacion' => 'bg-info text-dark',
];

$pctUsr = $maxUsuarios > 0 ? min(100, (int) round($usrAct / $maxUsuarios * 100)) : 0;
$pctFic = $maxGuardias > 0 ? min(100, (int) round($fichas / $maxGuardias * 100)) : 0;
$pctSto = $maxStorage > 0 ? min(100, (int) round($storageMb / $maxStorage * 100)) : 0;
$barColor = fn(int $p) => $p >= 95 ? 'bg-danger' : ($p >= 80 ? 'bg-warning' : ($p >= 60 ? 'bg-info' : 'bg-success'));
?>

<?php $view->section('title') ?><?= $e((string) $t['razon_social']) ?> | Superadmin<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex align-items-center mb-4">
    <a href="<?= $view->url('/admin/tenants') ?>" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-grow-1">
        <h4 class="fw-bold mb-0"><?= $e((string) $t['razon_social']) ?></h4>
        <small class="text-muted">RUC <?= $e((string) $t['ruc']) ?> · <?= $e((string) $t['email_contacto']) ?></small>
    </div>
    <span class="badge fs-6 <?= $estadoBadge[(string) $t['estado']] ?? 'bg-secondary' ?>"><?= $e((string) $t['estado']) ?></span>
</div>

<div class="row g-4">
    <!-- Info + Uso -->
    <div class="col-lg-8">
        <!-- Datos generales -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-info-circle me-1 text-primary"></i>Información general</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Nombre comercial:</strong> <?= $e((string) ($t['nombre_comercial'] ?? '—')) ?></div>
                    <div class="col-md-3"><strong>Teléfono:</strong> <?= $e((string) ($t['telefono'] ?? '—')) ?></div>
                    <div class="col-md-3"><strong>Ciudad:</strong> <?= $e((string) ($t['ciudad'] ?? '—')) ?></div>
                    <div class="col-md-4"><strong>Plan:</strong> <span class="badge bg-primary"><?= $e((string) $t['plan_codigo']) ?></span> <?= $e((string) ($t['plan_nombre'] ?? '')) ?></div>
                    <div class="col-md-4"><strong>Inicio plan:</strong> <?= $e(substr((string) ($t['plan_inicio'] ?? '—'), 0, 10)) ?></div>
                    <div class="col-md-4"><strong>Vencimiento:</strong> <?= $e(substr((string) ($t['plan_vencimiento'] ?? '—'), 0, 10)) ?></div>
                    <div class="col-md-4"><strong>Registrado:</strong> <?= $e(substr((string) ($t['created_at'] ?? ''), 0, 16)) ?></div>
                    <div class="col-md-4"><strong>Activado:</strong> <?= $t['activado_at'] ? $e(substr((string) $t['activado_at'], 0, 16)) : '<em class="text-muted">No activado</em>' ?></div>
                    <div class="col-md-4"><strong>Timezone:</strong> <?= $e((string) ($t['timezone'] ?? '')) ?></div>
                </div>
            </div>
        </div>

        <!-- Uso -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-speedometer2 me-1 text-info"></i>Uso actual</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="small text-muted">Usuarios activos</label>
                        <div class="fw-bold"><?= $usrAct ?> / <?= $maxUsuarios ?: '∞' ?></div>
                        <?php if ($maxUsuarios > 0): ?>
                            <div class="progress mt-1" style="height:6px"><div class="progress-bar <?= $barColor($pctUsr) ?>" style="width:<?= $pctUsr ?>%"></div></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted">Fichas de guardias</label>
                        <div class="fw-bold"><?= $fichas ?> / <?= $maxGuardias ?: '∞' ?></div>
                        <?php if ($maxGuardias > 0): ?>
                            <div class="progress mt-1" style="height:6px"><div class="progress-bar <?= $barColor($pctFic) ?>" style="width:<?= $pctFic ?>%"></div></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted">Almacenamiento</label>
                        <div class="fw-bold"><?= $storageMb ?> MB / <?= $maxStorage ?: '∞' ?> MB</div>
                        <?php if ($maxStorage > 0): ?>
                            <div class="progress mt-1" style="height:6px"><div class="progress-bar <?= $barColor($pctSto) ?>" style="width:<?= $pctSto ?>%"></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usuarios del tenant -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-people me-1 text-primary"></i>Usuarios (<?= count($usuarios) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <?php if ($usuarios === []): ?>
                    <div class="text-center text-muted py-4"><i class="bi bi-person-slash fs-3 d-block mb-1 opacity-50"></i>Sin usuarios</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Nombre</th><th>Email</th><th>Roles</th><th>Último login</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= $e(trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? ''))) ?></td>
                                        <td class="small"><?= $e((string) $u['email']) ?></td>
                                        <td class="small text-muted"><?= $e((string) ($u['roles'] ?? '—')) ?></td>
                                        <td class="small"><?= $u['ultimo_login_at'] ? $e(substr((string) $u['ultimo_login_at'], 0, 16)) : '<em class="text-muted">Nunca</em>' ?></td>
                                        <td><span class="badge <?= $estadoBadge[(string) $u['estado']] ?? 'bg-secondary' ?>"><?= $e((string) $u['estado']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ciclos de suscripción -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-gem me-1 text-warning"></i>Historial de suscripción</h6>
            </div>
            <div class="card-body p-0">
                <?php if ($ciclos === []): ?>
                    <div class="text-center text-muted py-4">Sin ciclos registrados</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Plan</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>USD</th><th>Pago</th></tr></thead>
                            <tbody>
                                <?php foreach ($ciclos as $c): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= $e((string) ($c['plan_nombre'] ?? $c['plan_codigo'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= $e((string) $c['tipo']) ?></span></td>
                                        <td class="small"><?= $e(substr((string) $c['fecha_inicio'], 0, 10)) ?></td>
                                        <td class="small"><?= $e(substr((string) $c['fecha_fin'], 0, 10)) ?></td>
                                        <td class="small">$<?= number_format((float) $c['precio_usd'], 2) ?></td>
                                        <td><span class="badge <?= (string) $c['estado_pago'] === 'pagado' ? 'bg-success' : ((string) $c['estado_pago'] === 'cortesia' ? 'bg-info text-dark' : 'bg-warning text-dark') ?>"><?= $e((string) $c['estado_pago']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel derecho: Acciones -->
    <div class="col-lg-4">
        <!-- Cambiar estado -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-toggles me-1"></i>Cambiar estado</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= $view->url('/admin/tenants/' . $e((string) $t['id']) . '/estado') ?>">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <select name="estado" class="form-select form-select-sm mb-3">
                        <?php foreach (['activo','pendiente','suspendido','cancelado'] as $est): ?>
                            <option value="<?= $est ?>" <?= (string) $t['estado'] === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-warning w-100"
                            onclick="return confirm('¿Cambiar el estado de este tenant?')">
                        <i class="bi bi-arrow-repeat me-1"></i>Aplicar
                    </button>
                </form>
            </div>
        </div>

        <!-- Cambiar plan -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-gem me-1"></i>Asignar plan</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= $view->url('/admin/tenants/' . $e((string) $t['id']) . '/plan') ?>">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <label class="form-label small">Plan</label>
                    <select name="plan_codigo" class="form-select form-select-sm mb-2">
                        <?php foreach ($planes as $p): ?>
                            <option value="<?= $e($p->codigo) ?>" <?= (string) $t['plan_codigo'] === $p->codigo ? 'selected' : '' ?>>
                                <?= $e($p->nombre) ?> ($<?= number_format($p->precioMensualUsd, 0) ?>/mes)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label small">Duración (meses)</label>
                    <input type="number" name="meses" class="form-control form-control-sm mb-3" value="12" min="1" max="60">
                    <button type="submit" class="btn btn-sm btn-primary w-100"
                            onclick="return confirm('¿Asignar este plan al tenant?')">
                        <i class="bi bi-arrow-up-circle me-1"></i>Asignar plan
                    </button>
                </form>
            </div>
        </div>

        <!-- Registros de empresa -->
        <?php if ($registros !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history me-1"></i>Log de registro</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_slice($registros, 0, 8) as $r): ?>
                            <li class="list-group-item small d-flex justify-content-between align-items-center">
                                <span><?= $e(substr((string) $r['created_at'], 0, 16)) ?></span>
                                <span class="badge <?= ['creado' => 'bg-primary', 'activado' => 'bg-success', 'expirado' => 'bg-warning text-dark', 'fallo' => 'bg-danger', 'reenviado' => 'bg-info text-dark'][(string) $r['evento']] ?? 'bg-secondary' ?>"><?= $e((string) $r['evento']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $view->endSection() ?>
