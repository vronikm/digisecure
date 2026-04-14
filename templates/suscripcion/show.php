<?php
/**
 * Dashboard de suscripción: plan actual, uso, ciclo vigente, historial y catálogo.
 *
 * Variables: $user, $csrfToken, $currentPath, $tenant, $plan, $catalogo, $uso,
 *            $cicloVigente, $historial, $diasRestantes, $vencido
 */

use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Subscription\Domain\Plan;
use DigiSecure\Subscription\Domain\UsoTenant;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var UsoTenant $uso */
/** @var ?Plan $plan */
/** @var list<Plan> $catalogo */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$usoArr = $uso->toArray();
$estadoGlobal = $usoArr['estado_global'];

$colorByPct = static function (int $pct): string {
    if ($pct >= 95) return 'bg-danger';
    if ($pct >= 80) return 'bg-warning';
    if ($pct >= 60) return 'bg-info';
    return 'bg-success';
};

$estadoPagoBadge = static fn(string $e) => match ($e) {
    'pagado'      => 'bg-success',
    'cortesia'    => 'bg-info text-dark',
    'pendiente'   => 'bg-warning text-dark',
    'vencido'     => 'bg-danger',
    'reembolsado' => 'bg-secondary',
    default       => 'bg-secondary',
};

$tipoCicloBadge = static fn(string $t) => match ($t) {
    'inicial'     => 'bg-primary',
    'renovacion'  => 'bg-success',
    'upgrade'     => 'bg-info text-dark',
    'downgrade'   => 'bg-warning text-dark',
    'cancelacion' => 'bg-danger',
    default       => 'bg-secondary',
};
?>

<?php $view->section('title') ?>Suscripción — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-gem me-1"></i>Suscripción</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-gem me-2 text-primary"></i>Mi Suscripción</h4>
        <p class="text-muted mb-0">Consulta tu plan actual, uso de recursos y próximas renovaciones.</p>
    </div>
    <?php if ($user->tienePermiso('suscripcion.gestionar')): ?>
    <a href="<?= $view->url('/suscripcion/ciclo/nuevo') ?>" class="btn btn-primary">
        <i class="bi bi-cart-plus me-1"></i>Registrar renovación / upgrade
    </a>
    <?php endif ?>
</div>

<?php if ($vencido): ?>
<div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-octagon-fill fs-3 me-3"></i>
    <div>
        <strong>Tu suscripción está vencida</strong>
        <div class="small">Venció hace <?= abs((int)$diasRestantes) ?> día(s). Algunas funciones podrían restringirse. Renueva cuanto antes.</div>
    </div>
</div>
<?php elseif ($diasRestantes !== null && $diasRestantes <= 15): ?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-clock-history fs-3 me-3"></i>
    <div>
        <strong>Tu plan vence en <?= (int)$diasRestantes ?> día(s)</strong>
        <div class="small">Registra la renovación para evitar interrupciones del servicio.</div>
    </div>
</div>
<?php endif ?>

<?php if ($estadoGlobal === 'excedido'): ?>
<div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-slash-circle-fill fs-3 me-3"></i>
    <div>
        <strong>Has excedido los límites de tu plan.</strong>
        <div class="small">No podrás crear más recursos hasta actualizar el plan o liberar espacio.</div>
    </div>
</div>
<?php elseif ($estadoGlobal === 'critico'): ?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
    <div>
        <strong>Estás cerca del límite de tu plan.</strong>
        <div class="small">Considera actualizar el plan para evitar bloqueos.</div>
    </div>
</div>
<?php endif ?>

<div class="row g-4 mb-4">
    <!-- Plan actual -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100 border-primary-subtle">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small text-uppercase">Plan actual</div>
                        <h3 class="mb-0"><?= $e($plan?->nombre ?? ($tenant['plan_codigo'] ?? 'Sin plan')) ?></h3>
                    </div>
                    <span class="badge bg-primary fs-6"><?= $e($plan?->codigo ?? '—') ?></span>
                </div>

                <?php if ($plan): ?>
                <p class="text-muted small"><?= $e((string)($plan->descripcion ?? '')) ?></p>

                <div class="row text-center border-top pt-3 mt-3">
                    <div class="col">
                        <div class="fs-4 fw-bold text-primary">$<?= number_format($plan->precioMensualUsd, 2) ?></div>
                        <div class="text-muted small">Mensual</div>
                    </div>
                    <div class="col border-start">
                        <div class="fs-4 fw-bold text-primary">$<?= number_format($plan->precioAnualUsd, 2) ?></div>
                        <div class="text-muted small">Anual</div>
                    </div>
                </div>

                <ul class="list-unstyled small mt-3 mb-0">
                    <?php foreach ($plan->caracteristicas as $k => $v): ?>
                        <?php if ($v === false) continue; ?>
                        <li class="mb-1">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <?= $e(ucfirst(str_replace('_', ' ', (string)$k))) ?>
                            <?php if (!is_bool($v)): ?>: <strong><?= $e((string)$v) ?></strong><?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
                <?php else: ?>
                <div class="alert alert-warning mb-0 small">Plan no encontrado en el catálogo.</div>
                <?php endif ?>
            </div>
            <div class="card-footer bg-light small">
                <div class="d-flex justify-content-between">
                    <span><i class="bi bi-calendar-check me-1"></i>Inicio: <strong><?= $e((string)($tenant['plan_inicio'] ?? '—')) ?></strong></span>
                    <span><i class="bi bi-calendar-x me-1"></i>Vence: <strong><?= $e((string)($tenant['plan_vencimiento'] ?? '—')) ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Uso de recursos -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-line me-2"></i><strong>Uso de recursos</strong></span>
                <?php
                $estadoBadge = match ($estadoGlobal) {
                    'excedido' => 'bg-danger',
                    'critico' => 'bg-warning text-dark',
                    'advertencia' => 'bg-info text-dark',
                    default => 'bg-success',
                };
                ?>
                <span class="badge <?= $estadoBadge ?>"><?= strtoupper($e($estadoGlobal)) ?></span>
            </div>
            <div class="card-body">
                <!-- Usuarios -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-people me-2"></i><strong>Usuarios</strong></span>
                        <span class="text-muted small">
                            <?= (int)$usoArr['usuarios_actuales'] ?> / <?= (int)$usoArr['usuarios_max'] ?>
                            (<?= (int)$usoArr['usuarios_pct'] ?>%)
                        </span>
                    </div>
                    <div class="progress" style="height: 14px">
                        <div class="progress-bar <?= $colorByPct((int)$usoArr['usuarios_pct']) ?>"
                             role="progressbar"
                             style="width: <?= (int)$usoArr['usuarios_pct'] ?>%"
                             aria-valuenow="<?= (int)$usoArr['usuarios_pct'] ?>"
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Guardias -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-person-badge me-2"></i><strong>Guardias / Fichas</strong></span>
                        <span class="text-muted small">
                            <?= (int)$usoArr['guardias_actuales'] ?> / <?= (int)$usoArr['guardias_max'] ?>
                            (<?= (int)$usoArr['guardias_pct'] ?>%)
                        </span>
                    </div>
                    <div class="progress" style="height: 14px">
                        <div class="progress-bar <?= $colorByPct((int)$usoArr['guardias_pct']) ?>"
                             role="progressbar"
                             style="width: <?= (int)$usoArr['guardias_pct'] ?>%"
                             aria-valuenow="<?= (int)$usoArr['guardias_pct'] ?>"
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Storage -->
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-hdd me-2"></i><strong>Almacenamiento</strong></span>
                        <span class="text-muted small">
                            <?= (int)$usoArr['storage_usado_mb'] ?> / <?= (int)$usoArr['storage_max_mb'] ?> MB
                            (<?= (int)$usoArr['storage_pct'] ?>%)
                        </span>
                    </div>
                    <div class="progress" style="height: 14px">
                        <div class="progress-bar <?= $colorByPct((int)$usoArr['storage_pct']) ?>"
                             role="progressbar"
                             style="width: <?= (int)$usoArr['storage_pct'] ?>%"
                             aria-valuenow="<?= (int)$usoArr['storage_pct'] ?>"
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historial -->
<div class="card shadow-sm mb-4">
    <div class="card-header"><i class="bi bi-clock-history me-2"></i><strong>Historial de ciclos</strong></div>
    <div class="card-body p-0">
        <?php if (empty($historial)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            Aún no hay ciclos de suscripción registrados.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Plan</th>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th class="text-end">Monto USD</th>
                        <th>Estado pago</th>
                        <th>Método</th>
                        <th>Pagado el</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><code><?= $e((string)$h['plan_codigo']) ?></code></td>
                        <td><span class="badge <?= $tipoCicloBadge((string)$h['tipo']) ?>"><?= $e((string)$h['tipo']) ?></span></td>
                        <td><?= $e((string)$h['fecha_inicio']) ?></td>
                        <td><?= $e((string)$h['fecha_fin']) ?></td>
                        <td class="text-end">$<?= number_format((float)$h['precio_usd'], 2) ?></td>
                        <td><span class="badge <?= $estadoPagoBadge((string)$h['estado_pago']) ?>"><?= $e((string)$h['estado_pago']) ?></span></td>
                        <td><small class="text-muted"><?= $e((string)($h['metodo_pago'] ?? '—')) ?></small></td>
                        <td><small class="text-muted"><?= $h['pagado_at'] ? date('d/m/Y', strtotime((string)$h['pagado_at'])) : '—' ?></small></td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php endif ?>
    </div>
</div>

<!-- Catálogo / Comparativa -->
<div class="card shadow-sm">
    <div class="card-header"><i class="bi bi-stars me-2"></i><strong>Planes disponibles</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($catalogo as $p): ?>
            <?php $esActual = $plan !== null && $p->codigo === $plan->codigo; ?>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 <?= $esActual ? 'border-primary border-2' : '' ?>">
                    <div class="card-body d-flex flex-column">
                        <?php if ($esActual): ?>
                        <span class="badge bg-primary mb-2 align-self-start">Plan actual</span>
                        <?php endif ?>
                        <h5 class="mb-1"><?= $e($p->nombre) ?></h5>
                        <div class="text-muted small mb-2"><?= $e((string)($p->descripcion ?? '')) ?></div>

                        <div class="my-3">
                            <span class="fs-3 fw-bold">$<?= number_format($p->precioMensualUsd, 2) ?></span>
                            <span class="text-muted small">/mes</span>
                        </div>

                        <ul class="list-unstyled small flex-grow-1 mb-3">
                            <li><i class="bi bi-people text-muted me-1"></i> <?= $p->maxUsuarios ?> usuarios</li>
                            <li><i class="bi bi-person-badge text-muted me-1"></i> <?= $p->maxGuardias ?> guardias</li>
                            <li><i class="bi bi-hdd text-muted me-1"></i> <?= number_format($p->maxStorageMb / 1024, 1) ?> GB</li>
                            <?php if ($p->tieneCaracteristica('soporte')): ?>
                            <li><i class="bi bi-headset text-muted me-1"></i> Soporte <?= $e((string)$p->caracteristica('soporte')) ?></li>
                            <?php endif ?>
                            <?php if ($p->tieneCaracteristica('sla')): ?>
                            <li><i class="bi bi-speedometer text-muted me-1"></i> SLA <?= $e((string)$p->caracteristica('sla')) ?></li>
                            <?php endif ?>
                        </ul>

                        <?php if ($esActual): ?>
                        <button class="btn btn-outline-primary btn-sm" disabled>
                            <i class="bi bi-check-circle me-1"></i>Contratado
                        </button>
                        <?php elseif ($user->tienePermiso('suscripcion.gestionar')): ?>
                        <a href="<?= $view->url('/suscripcion/ciclo/nuevo?plan=' . urlencode($p->codigo)) ?>"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-up-right-circle me-1"></i>Contratar
                        </a>
                        <?php endif ?>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<?php $view->endSection() ?>
