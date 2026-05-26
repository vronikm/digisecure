<?php
/**
 * Formulario para registrar un ciclo de suscripcion (renovacion/upgrade).
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

$preseleccionPlan = (string) ($old['plan_codigo'] ?? $_GET['plan'] ?? $plan?->codigo ?? '');
$preseleccionMeses = (int) ($old['meses'] ?? 12);
$preseleccionTipo = (string) ($old['tipo'] ?? 'renovacion');
$preseleccionEstado = (string) ($old['estado_pago'] ?? 'pagado');
$hoy = date('Y-m-d');
?>

<?php $view->section('title') ?>Nueva renovacion - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/suscripcion') ?>">Suscripcion</a></li>
<li class="breadcrumb-item active">Nuevo ciclo</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-cart-plus"></i></span>
        <div>
            <h1>Registrar ciclo de suscripcion</h1>
            <small>Renovacion, upgrade o downgrade &mdash; queda registrado en auditoria</small>
        </div>
    </div>
    <a href="<?= $view->url('/suscripcion') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo registrar el ciclo</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url('/suscripcion/ciclo/nuevo') ?>" id="cicloForm">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="ds-card">
                <div class="ds-card-header tone-purple">
                    <span class="ds-card-mark"><i class="bi bi-stars"></i></span>
                    <div>
                        <div class="ds-card-title">Plan y duracion</div>
                        <div class="ds-card-subtitle">Selecciona el plan a contratar y su periodo</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="ds-form-field" style="margin-bottom: 1rem;">
                        <label class="ds-form-label">Plan <span class="ds-required">*</span></label>
                        <select name="plan_codigo" class="form-select" required id="planSelect">
                            <option value="">- Selecciona un plan -</option>
                            <?php foreach ($catalogo as $p): ?>
                            <option value="<?= $e($p->codigo) ?>"
                                    data-mensual="<?= $p->precioMensualUsd ?>"
                                    data-anual="<?= $p->precioAnualUsd ?>"
                                    <?= $p->codigo === $preseleccionPlan ? 'selected' : '' ?>>
                                <?= $e($p->nombre) ?> - $<?= number_format($p->precioMensualUsd, 2) ?>/mes
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="row g-3" style="margin-bottom: 1rem;">
                        <div class="col-md-6">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Tipo de ciclo <span class="ds-required">*</span></label>
                                <select name="tipo" class="form-select" required>
                                    <?php foreach (['inicial','renovacion','upgrade','downgrade','cancelacion'] as $t): ?>
                                    <option value="<?= $t ?>" <?= $t === $preseleccionTipo ? 'selected' : '' ?>><?= $e(ucfirst($t)) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Duracion (meses) <span class="ds-required">*</span></label>
                                <select name="meses" class="form-select" required id="mesesSelect">
                                    <?php foreach ([1, 3, 6, 12, 24] as $m): ?>
                                    <option value="<?= $m ?>" <?= $m === $preseleccionMeses ? 'selected' : '' ?>>
                                        <?= $m ?> <?= $m === 1 ? 'mes' : 'meses' ?><?= $m === 12 ? ' (anual)' : '' ?>
                                    </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha de inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control"
                               value="<?= $e((string) ($old['fecha_inicio'] ?? $hoy)) ?>">
                        <span class="ds-form-help">Si la dejas vacia, se usa hoy (<?= $hoy ?>).</span>
                    </div>
                </div>
            </div>

            <div class="ds-card">
                <div class="ds-card-header tone-success">
                    <span class="ds-card-mark"><i class="bi bi-credit-card"></i></span>
                    <div>
                        <div class="ds-card-title">Pago</div>
                        <div class="ds-card-subtitle">Estado del pago y referencia</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="row g-3" style="margin-bottom: 1rem;">
                        <div class="col-md-6">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Estado del pago <span class="ds-required">*</span></label>
                                <select name="estado_pago" class="form-select" required>
                                    <?php foreach (['pagado','pendiente','cortesia','vencido','reembolsado'] as $ep): ?>
                                    <option value="<?= $ep ?>" <?= $ep === $preseleccionEstado ? 'selected' : '' ?>><?= $e(ucfirst($ep)) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <span class="ds-form-help"><span class="ds-cell-mono">pagado</span> o <span class="ds-cell-mono">cortesia</span> activan el plan inmediatamente.</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Metodo de pago</label>
                                <input type="text" name="metodo_pago" class="form-control"
                                       value="<?= $e((string) ($old['metodo_pago'] ?? '')) ?>"
                                       placeholder="Transferencia, tarjeta, efectivo...">
                            </div>
                        </div>
                    </div>

                    <div class="ds-form-field" style="margin-bottom: 1rem;">
                        <label class="ds-form-label">Referencia de pago</label>
                        <input type="text" name="referencia_pago" class="form-control"
                               value="<?= $e((string) ($old['referencia_pago'] ?? '')) ?>"
                               placeholder="Numero de transaccion, comprobante, etc.">
                    </div>

                    <div class="ds-form-field">
                        <label class="ds-form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="2" placeholder="Opcional..."><?= $e((string) ($old['notas'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ds-card" style="position: sticky; top: 90px;">
                <div class="ds-card-header" style="background: linear-gradient(135deg, #2f80ed, #1e63c5); color: #fff;">
                    <span class="ds-card-mark" style="background: rgba(255,255,255,.2); color: #fff;"><i class="bi bi-receipt"></i></span>
                    <div>
                        <div class="ds-card-title" style="color: #fff;">Resumen del ciclo</div>
                        <div class="ds-card-subtitle" style="color: rgba(255,255,255,.7);">Calculado en tiempo real</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <dl style="margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: .65rem 0;">
                        <dt class="ds-cell-sub">Plan seleccionado</dt>
                        <dd id="rPlan" style="margin: 0; font-weight: 600; color: var(--ds-text); text-align: right;">-</dd>

                        <dt class="ds-cell-sub">Duracion</dt>
                        <dd id="rDur" style="margin: 0; font-weight: 600; color: var(--ds-text); text-align: right;">-</dd>

                        <dt class="ds-cell-sub">Precio mensual</dt>
                        <dd id="rMensual" class="ds-cell-mono" style="margin: 0; text-align: right;">-</dd>

                        <dt class="ds-cell-sub">Precio anual</dt>
                        <dd id="rAnual" class="ds-cell-mono" style="margin: 0; text-align: right;">-</dd>
                    </dl>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #eef1f5; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700; font-size: .95rem; color: var(--ds-text);">Total estimado</span>
                        <span id="rTotal" style="font-size: 1.6rem; font-weight: 700; color: #2f80ed;">$ 0.00</span>
                    </div>
                </div>
                <div style="padding: .85rem 1.25rem; background: #fbfcfe; border-top: 1px solid #eef1f5; display: flex; gap: .5rem;">
                    <button type="submit" class="ds-btn ds-btn-primary" style="flex: 1;">
                        <i class="bi bi-check-circle"></i>Registrar ciclo
                    </button>
                    <a href="<?= $view->url('/suscripcion') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">Cancelar</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const sel = document.getElementById('planSelect');
    const meses = document.getElementById('mesesSelect');
    const fmt = n => '$ ' + n.toFixed(2);

    function recalcular() {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) {
            document.getElementById('rPlan').textContent = '-';
            document.getElementById('rDur').textContent = '-';
            document.getElementById('rMensual').textContent = '-';
            document.getElementById('rAnual').textContent = '-';
            document.getElementById('rTotal').textContent = '$ 0.00';
            return;
        }
        const m = parseFloat(opt.dataset.mensual || '0');
        const a = parseFloat(opt.dataset.anual || '0');
        const n = parseInt(meses.value || '1', 10);

        document.getElementById('rPlan').textContent = opt.textContent.split('-')[0].trim();
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
