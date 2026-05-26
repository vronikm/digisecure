<?php
/**
 * Dashboard de suscripcion: plan actual, uso, ciclo vigente, historial y catalogo.
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

$progressTone = static function (int $pct): string {
    if ($pct >= 95) return 'tone-danger';
    if ($pct >= 80) return 'tone-warning';
    if ($pct >= 60) return 'tone-info';
    return 'tone-success';
};

$pagoTone = static fn(string $v) => match ($v) {
    'pagado'      => 'tone-success',
    'cortesia'    => 'tone-info',
    'pendiente'   => 'tone-warning',
    'vencido'     => 'tone-danger',
    'reembolsado' => 'tone-muted',
    default       => 'tone-muted',
};

$tipoTone = static fn(string $t) => match ($t) {
    'inicial'     => 'tone-info',
    'renovacion'  => 'tone-success',
    'upgrade'     => 'tone-purple',
    'downgrade'   => 'tone-warning',
    'cancelacion' => 'tone-danger',
    default       => 'tone-muted',
};

$globalTone = match ($estadoGlobal) {
    'excedido'    => 'tone-danger',
    'critico'     => 'tone-warning',
    'advertencia' => 'tone-info',
    default       => 'tone-success',
};
?>

<?php $view->section('title') ?>Suscripcion - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-gem me-1"></i>Suscripcion</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-gem"></i></span>
        <div>
            <h1>Mi suscripcion</h1>
            <small>Plan actual, uso de recursos y proximas renovaciones</small>
        </div>
    </div>
    <?php if ($user->tienePermiso('suscripcion.gestionar')): ?>
    <a href="<?= $view->url('/suscripcion/ciclo/nuevo') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-cart-plus"></i>Renovar / hacer upgrade
    </a>
    <?php endif ?>
</div>

<?php if ($vencido): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .85rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.4rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #b91c1c; margin-bottom: .15rem;">Tu suscripcion esta vencida</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;">Vencio hace <strong style="color: #b91c1c;"><?= abs((int) $diasRestantes) ?> dia(s)</strong>. Algunas funciones podrian restringirse. Renueva cuanto antes.</div>
        </div>
    </div>
</div>
<?php elseif ($diasRestantes !== null && $diasRestantes <= 15): ?>
<div class="ds-card" style="border-left: 4px solid #d97706; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .85rem; align-items: flex-start;">
        <span style="color: #d97706; font-size: 1.4rem; line-height: 1;"><i class="bi bi-clock-history"></i></span>
        <div>
            <div style="font-weight: 700; color: #b45309; margin-bottom: .15rem;">Tu plan vence en <?= (int) $diasRestantes ?> dia(s)</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;">Registra la renovacion para evitar interrupciones del servicio.</div>
        </div>
    </div>
</div>
<?php endif ?>

<?php if ($estadoGlobal === 'excedido'): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .85rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.4rem; line-height: 1;"><i class="bi bi-slash-circle-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #b91c1c; margin-bottom: .15rem;">Has excedido los limites de tu plan</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;">No podras crear mas recursos hasta actualizar el plan o liberar espacio.</div>
        </div>
    </div>
</div>
<?php elseif ($estadoGlobal === 'critico'): ?>
<div class="ds-card" style="border-left: 4px solid #d97706; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .85rem; align-items: flex-start;">
        <span style="color: #d97706; font-size: 1.4rem; line-height: 1;"><i class="bi bi-exclamation-triangle-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #b45309; margin-bottom: .15rem;">Estas cerca del limite de tu plan</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;">Considera actualizar el plan para evitar bloqueos.</div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="row g-4">
    <!-- Plan actual -->
    <div class="col-lg-5">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-gem"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-subtitle">Plan actual</div>
                        <div class="ds-card-title" style="font-size: 1.05rem;"><?= $e($plan?->nombre ?? ($tenant['plan_codigo'] ?? 'Sin plan')) ?></div>
                    </div>
                    <span class="ds-chip"><i class="bi bi-tag"></i><?= $e($plan?->codigo ?? '-') ?></span>
                </div>
            </div>
            <div class="ds-card-body">
                <?php if ($plan): ?>
                <p class="ds-cell-sub" style="margin-bottom: 1rem;"><?= $e((string) ($plan->descripcion ?? '')) ?></p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0; padding: 1rem; background: linear-gradient(135deg, rgba(47,128,237,.06), rgba(14,165,164,.05)); border-radius: 12px; margin-bottom: 1rem;">
                    <div style="text-align: center; padding-right: 1rem; border-right: 1px solid #e2e8f0;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #2f80ed; line-height: 1;">$<?= number_format($plan->precioMensualUsd, 2) ?></div>
                        <div class="ds-cell-sub" style="margin-top: .35rem;">Mensual</div>
                    </div>
                    <div style="text-align: center; padding-left: 1rem;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #2f80ed; line-height: 1;">$<?= number_format($plan->precioAnualUsd, 2) ?></div>
                        <div class="ds-cell-sub" style="margin-top: .35rem;">Anual</div>
                    </div>
                </div>

                <ul style="list-style: none; padding: 0; margin: 0; font-size: .85rem;">
                    <?php foreach ($plan->caracteristicas as $k => $v): ?>
                        <?php if ($v === false) continue; ?>
                        <li style="padding: .35rem 0; display: flex; align-items: center; gap: .45rem;">
                            <i class="bi bi-check-circle-fill" style="color: #059669;"></i>
                            <span style="color: var(--ds-text);">
                                <?= $e(ucfirst(str_replace('_', ' ', (string) $k))) ?>
                                <?php if (!is_bool($v)): ?>: <strong><?= $e((string) $v) ?></strong><?php endif ?>
                            </span>
                        </li>
                    <?php endforeach ?>
                </ul>
                <?php else: ?>
                <div class="login-info" style="margin: 0;">
                    <i class="bi bi-info-circle"></i>
                    <div>Plan no encontrado en el catalogo.</div>
                </div>
                <?php endif ?>
            </div>
            <div style="padding: .75rem 1.25rem; background: #f4f7fb; border-top: 1px solid #eef1f5; display: flex; gap: 1rem; flex-wrap: wrap; font-size: .78rem; color: var(--ds-text-soft);">
                <span><i class="bi bi-calendar-check"></i> Inicio: <strong style="color: var(--ds-text);"><?= $e((string) ($tenant['plan_inicio'] ?? '-')) ?></strong></span>
                <span><i class="bi bi-calendar-x"></i> Vence: <strong style="color: var(--ds-text);"><?= $e((string) ($tenant['plan_vencimiento'] ?? '-')) ?></strong></span>
            </div>
        </div>
    </div>

    <!-- Uso de recursos -->
    <div class="col-lg-7">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-bar-chart-line"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Uso de recursos</div>
                        <div class="ds-card-subtitle">Consumo actual del plan</div>
                    </div>
                    <span class="ds-badge <?= $globalTone ?>"><?= strtoupper($e($estadoGlobal)) ?></span>
                </div>
            </div>
            <div class="ds-card-body">
                <!-- Usuarios -->
                <div style="margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: .35rem;">
                        <span style="font-weight: 600; color: var(--ds-text);"><i class="bi bi-people me-2"></i>Usuarios</span>
                        <span class="ds-cell-sub">
                            <strong style="color: var(--ds-text);"><?= (int) $usoArr['usuarios_actuales'] ?></strong> / <?= (int) $usoArr['usuarios_max'] ?>
                            &middot; <?= (int) $usoArr['usuarios_pct'] ?>%
                        </span>
                    </div>
                    <div class="ds-progress" style="height: 12px;">
                        <div class="ds-progress-bar <?= $progressTone((int) $usoArr['usuarios_pct']) ?>" style="width: <?= (int) $usoArr['usuarios_pct'] ?>%"></div>
                    </div>
                </div>
                <!-- Guardias -->
                <div style="margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: .35rem;">
                        <span style="font-weight: 600; color: var(--ds-text);"><i class="bi bi-person-badge me-2"></i>Guardias / Fichas</span>
                        <span class="ds-cell-sub">
                            <strong style="color: var(--ds-text);"><?= (int) $usoArr['guardias_actuales'] ?></strong> / <?= (int) $usoArr['guardias_max'] ?>
                            &middot; <?= (int) $usoArr['guardias_pct'] ?>%
                        </span>
                    </div>
                    <div class="ds-progress" style="height: 12px;">
                        <div class="ds-progress-bar <?= $progressTone((int) $usoArr['guardias_pct']) ?>" style="width: <?= (int) $usoArr['guardias_pct'] ?>%"></div>
                    </div>
                </div>
                <!-- Storage -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: .35rem;">
                        <span style="font-weight: 600; color: var(--ds-text);"><i class="bi bi-hdd me-2"></i>Almacenamiento</span>
                        <span class="ds-cell-sub">
                            <strong style="color: var(--ds-text);"><?= (int) $usoArr['storage_usado_mb'] ?></strong> / <?= (int) $usoArr['storage_max_mb'] ?> MB
                            &middot; <?= (int) $usoArr['storage_pct'] ?>%
                        </span>
                    </div>
                    <div class="ds-progress" style="height: 12px;">
                        <div class="ds-progress-bar <?= $progressTone((int) $usoArr['storage_pct']) ?>" style="width: <?= (int) $usoArr['storage_pct'] ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historial -->
<div class="ds-card" style="margin-top: 1.25rem;">
    <div class="ds-card-header">
        <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
        <div>
            <div class="ds-card-title">Historial de ciclos</div>
            <div class="ds-card-subtitle"><?= count($historial) ?> ciclo<?= count($historial) !== 1 ? 's' : '' ?> registrado<?= count($historial) !== 1 ? 's' : '' ?></div>
        </div>
    </div>
    <?php if (empty($historial)): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
            <div class="ds-empty-icon"><i class="bi bi-inbox"></i></div>
            <p>Aun no hay ciclos de suscripcion registrados.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Tipo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th class="ds-cell-end">Monto USD</th>
                    <th>Estado pago</th>
                    <th>Metodo</th>
                    <th>Pagado el</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $h): ?>
                <tr>
                    <td><span class="ds-cell-mono"><?= $e((string) $h['plan_codigo']) ?></span></td>
                    <td><span class="ds-badge <?= $tipoTone((string) $h['tipo']) ?>"><?= $e((string) $h['tipo']) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $h['fecha_inicio']) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $h['fecha_fin']) ?></span></td>
                    <td class="ds-cell-end ds-cell-mono">$<?= number_format((float) $h['precio_usd'], 2) ?></td>
                    <td><span class="ds-badge <?= $pagoTone((string) $h['estado_pago']) ?>"><?= $e((string) $h['estado_pago']) ?></span></td>
                    <td><span class="ds-cell-sub"><?= $e((string) ($h['metodo_pago'] ?? '-')) ?></span></td>
                    <td>
                        <?php if (!empty($h['pagado_at'])): ?>
                            <span class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $h['pagado_at'])) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>

<!-- Catalogo / Comparativa -->
<div class="ds-card">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-stars"></i></span>
        <div>
            <div class="ds-card-title">Planes disponibles</div>
            <div class="ds-card-subtitle">Catalogo completo y comparativa</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="row g-3">
            <?php foreach ($catalogo as $p):
                $esActual = $plan !== null && $p->codigo === $plan->codigo;
            ?>
            <div class="col-md-6 col-xl-3">
                <div style="height: 100%; padding: 1.25rem; border-radius: 14px; border: 2px solid <?= $esActual ? '#2f80ed' : '#eef2f7' ?>; background: <?= $esActual ? 'linear-gradient(180deg, rgba(47,128,237,.04), #fff)' : '#fff' ?>; display: flex; flex-direction: column; box-shadow: <?= $esActual ? '0 6px 18px rgba(47,128,237,.18)' : '0 4px 14px rgba(9,29,53,.04)' ?>;">
                    <?php if ($esActual): ?>
                    <span class="ds-badge tone-info no-dot" style="align-self: flex-start; margin-bottom: .65rem;"><i class="bi bi-check-circle-fill"></i>Plan actual</span>
                    <?php endif ?>
                    <h5 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 .25rem; color: var(--ds-text);"><?= $e($p->nombre) ?></h5>
                    <div class="ds-cell-sub" style="margin-bottom: .85rem; min-height: 2.4em;"><?= $e((string) ($p->descripcion ?? '')) ?></div>
                    <div style="margin-bottom: 1rem;">
                        <span style="font-size: 1.6rem; font-weight: 700; color: #2f80ed;">$<?= number_format($p->precioMensualUsd, 0) ?></span>
                        <span class="ds-cell-sub">/mes</span>
                    </div>
                    <ul style="list-style: none; padding: 0; margin: 0 0 1rem; flex: 1; font-size: .82rem;">
                        <li style="padding: .25rem 0; color: var(--ds-text-soft);"><i class="bi bi-people"></i> <?= $p->maxUsuarios ?> usuarios</li>
                        <li style="padding: .25rem 0; color: var(--ds-text-soft);"><i class="bi bi-person-badge"></i> <?= $p->maxGuardias ?> guardias</li>
                        <li style="padding: .25rem 0; color: var(--ds-text-soft);"><i class="bi bi-hdd"></i> <?= number_format($p->maxStorageMb / 1024, 1) ?> GB</li>
                        <?php if ($p->tieneCaracteristica('soporte')): ?>
                        <li style="padding: .25rem 0; color: var(--ds-text-soft);"><i class="bi bi-headset"></i> Soporte <?= $e((string) $p->caracteristica('soporte')) ?></li>
                        <?php endif ?>
                        <?php if ($p->tieneCaracteristica('sla')): ?>
                        <li style="padding: .25rem 0; color: var(--ds-text-soft);"><i class="bi bi-speedometer"></i> SLA <?= $e((string) $p->caracteristica('sla')) ?></li>
                        <?php endif ?>
                    </ul>
                    <?php if ($esActual): ?>
                    <button class="ds-btn ds-btn-outline ds-btn-sm" disabled>
                        <i class="bi bi-check-circle"></i>Contratado
                    </button>
                    <?php elseif ($user->tienePermiso('suscripcion.gestionar')): ?>
                    <a href="<?= $view->url('/suscripcion/ciclo/nuevo?plan=' . urlencode($p->codigo)) ?>"
                       class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-arrow-up-right-circle"></i>Contratar
                    </a>
                    <?php endif ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
