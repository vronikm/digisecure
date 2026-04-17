<?php
/**
 * Formulario para registrar un ciclo de suscripción (renovación/upgrade).
 *
 * Variables: $user, $csrfToken, $currentPath, $tenant, $plan, $catalogo, $old
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Subscription\Domain\Plan;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var ?Plan $plan */
/** @var list<Plan> $catalogo */
/** @var array<string, mixed> $old */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$preseleccionPlan = (string)($old['plan_codigo'] ?? $_GET['plan'] ?? $plan?->codigo ?? '');
$preseleccionMeses = (int)($old['meses'] ?? 12);
$preseleccionTipo = (string)($old['tipo'] ?? 'renovacion');
$preseleccionEstado = (string)($old['estado_pago'] ?? 'pagado');
$hoy = date('Y-m-d');
?>

<?php $view->section('title') ?>Nueva renovación — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/suscripcion') ?>">Suscripción</a></li>
<li class="breadcrumb-item active">Nuevo ciclo</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-cart-plus me-2 text-primary"></i>Registrar ciclo de suscripción</h4>
        <p class="text-muted mb-0">Renovación, upgrade o downgrade. Se aplicará al tenant y quedará registrado en auditoría.</p>
    </div>
    <a href="<?= $view->url('/suscripcion') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="post" action="<?= $view->url('/suscripcion/ciclo/nuevo') ?>" id="cicloForm">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><i class="bi bi-stars me-2"></i><strong>Plan y duración</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Plan <span class="text-danger">*</span></label>
                        <select name="plan_codigo" class="form-select" required id="planSelect">
                            <option value="">— Selecciona un plan —</option>
                            <?php foreach ($catalogo as $p): ?>
                            <option value="<?= $e($p->codigo) ?>"
                                    data-mensual="<?= $p->precioMensualUsd ?>"
                                    data-anual="<?= $p->precioAnualUsd ?>"
                                    <?= $p->codigo === $preseleccionPlan ? 'selected' : '' ?>>
                                <?= $e($p->nombre) ?> — $<?= number_format($p->precioMensualUsd, 2) ?>/mes
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de ciclo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select" required>
                                <?php foreach (['inicial','renovacion','upgrade','downgrade','cancelacion'] as $t): ?>
                                <option value="<?= $t ?>" <?= $t === $preseleccionTipo ? 'selected' : '' ?>><?= $e(ucfirst($t)) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duración (meses) <span class="text-danger">*</span></label>
                            <select name="meses" class="form-select" required id="mesesSelect">
                                <?php foreach ([1, 3, 6, 12, 24] as $m): ?>
                                <option value="<?= $m ?>" <?= $m === $preseleccionMeses ? 'selected' : '' ?>>
                                    <?= $m ?> <?= $m === 1 ? 'mes' : 'meses' ?><?= $m === 12 ? ' (anual)' : '' ?>
                                </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Fecha de inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control"
                               value="<?= $e((string)($old['fecha_inicio'] ?? $hoy)) ?>">
                        <div class="form-text">Si la dejas vacía, se usa hoy (<?= $hoy ?>).</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-credit-card me-2"></i><strong>Pago</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado del pago <span class="text-danger">*</span></label>
                            <select name="estado_pago" class="form-select" required>
                                <?php foreach (['pagado','pendiente','cortesia','vencido','reembolsado'] as $ep): ?>
                                <option value="<?= $ep ?>" <?= $ep === $preseleccionEstado ? 'selected' : '' ?>><?= $e(ucfirst($ep)) ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text"><code>pagado</code> o <code>cortesia</code> activan el plan inmediatamente.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Método de pago</label>
                            <input type="text" name="metodo_pago" class="form-control"
                                   value="<?= $e((string)($old['metodo_pago'] ?? '')) ?>"
                                   placeholder="Transferencia, tarjeta, efectivo...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Referencia de pago</label>
                        <input type="text" name="referencia_pago" class="form-control"
                               value="<?= $e((string)($old['referencia_pago'] ?? '')) ?>"
                               placeholder="Número de transacción, comprobante, etc.">
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="2" placeholder="Opcional..."><?= $e((string)($old['notas'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm sticky-top" style="top: 80px">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-receipt me-2"></i><strong>Resumen</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Plan seleccionado</dt>
                        <dd class="col-sm-7" id="rPlan">—</dd>

                        <dt class="col-sm-5 text-muted">Duración</dt>
                        <dd class="col-sm-7" id="rDur">—</dd>

                        <dt class="col-sm-5 text-muted">Precio mensual</dt>
                        <dd class="col-sm-7" id="rMensual">—</dd>

                        <dt class="col-sm-5 text-muted">Precio anual</dt>
                        <dd class="col-sm-7" id="rAnual">—</dd>

                        <dt class="col-sm-5 fs-5 border-top pt-2">Total estimado</dt>
                        <dd class="col-sm-7 fs-5 fw-bold text-primary border-top pt-2" id="rTotal">$ 0.00</dd>
                    </dl>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-check-circle me-1"></i>Registrar ciclo
                    </button>
                    <a href="<?= $view->url('/suscripcion') ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function() {
    const sel = document.getElementById('planSelect');
    const meses = document.getElementById('mesesSelect');
    const fmt = n => '$ ' + n.toFixed(2);

    function recalcular() {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) {
            document.getElementById('rPlan').textContent = '—';
            document.getElementById('rDur').textContent = '—';
            document.getElementById('rMensual').textContent = '—';
            document.getElementById('rAnual').textContent = '—';
            document.getElementById('rTotal').textContent = '$ 0.00';
            return;
        }
        const m = parseFloat(opt.dataset.mensual || '0');
        const a = parseFloat(opt.dataset.anual || '0');
        const n = parseInt(meses.value || '1', 10);

        document.getElementById('rPlan').textContent = opt.textContent.split('—')[0].trim();
        document.getElementById('rDur').textContent = n + (n === 1 ? ' mes' : ' meses');
        document.getElementById('rMensual').textContent = fmt(m);
        document.getElementById('rAnual').textContent = fmt(a);

        let total;
        if (n >= 12 && a > 0) {
            const anios = Math.floor(n / 12);
            const rest = n % 12;
            total = (anios * a) + (rest * m);
        } else {
            total = n * m;
        }
        document.getElementById('rTotal').textContent = fmt(total);
    }

    sel.addEventListener('change', recalcular);
    meses.addEventListener('change', recalcular);
    recalcular();
})();
</script>

<?php $view->endSection() ?>
