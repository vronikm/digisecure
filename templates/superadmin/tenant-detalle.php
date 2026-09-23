<?php
/**
 * Detalle de un tenant (Fase 13).
 *
 * Variables: $user, $csrfToken, $currentPath, $tenant, $uso,
 *            $usuarios, $usuariosDisponibles, $rolesDisponibles,
 *            $ciclos, $registros, $planes
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$t = $tenant;
$csrfToken = (string) ($csrfToken ?? '');
$usuariosDisponibles = is_array($usuariosDisponibles ?? null) ? $usuariosDisponibles : [];
$rolesDisponibles = is_array($rolesDisponibles ?? null) ? $rolesDisponibles : [];
$storageMb = round(((int) ($uso['storage_bytes'] ?? 0)) / (1024 * 1024), 1);
$maxStorage = (int) ($t['max_storage_mb'] ?? 0);
$maxUsuarios = (int) ($t['max_usuarios'] ?? 0);
$maxGuardias = (int) ($t['max_guardias'] ?? 0);
$usrAct = (int) ($uso['usuarios_cupo'] ?? 0);
$fichas = (int) ($uso['guardias_cupo'] ?? 0);

$estadoTone = [
    'activo'                 => 'tone-success',
    'pendiente'              => 'tone-warning',
    'suspendido'             => 'tone-danger',
    'cancelado'              => 'tone-muted',
    'inactivo'               => 'tone-muted',
    'bloqueado'              => 'tone-danger',
    'pendiente_verificacion' => 'tone-info',
];

$pagoTone = static fn(string $estado) => match ($estado) {
    'pagado'    => 'tone-success',
    'cortesia'  => 'tone-info',
    'pendiente' => 'tone-warning',
    'fallido'   => 'tone-danger',
    default     => 'tone-muted',
};

$eventoTone = [
    'creado'    => 'tone-info',
    'activado'  => 'tone-success',
    'expirado'  => 'tone-warning',
    'reenviado' => 'tone-info',
    'fallo'     => 'tone-danger',
];

$pctUsr = $maxUsuarios > 0 ? min(100, (int) round($usrAct / $maxUsuarios * 100)) : 0;
$pctFic = $maxGuardias > 0 ? min(100, (int) round($fichas / $maxGuardias * 100)) : 0;
$pctSto = $maxStorage > 0 ? min(100, (int) round($storageMb / $maxStorage * 100)) : 0;
$progressTone = static fn(int $p): string => $p >= 95 ? 'tone-danger' : ($p >= 80 ? 'tone-warning' : ($p >= 60 ? 'tone-info' : 'tone-success'));

$ds_dt = 'font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ds-text-soft); margin-bottom: .15rem;';
$ds_dd = 'font-size: .9rem; color: var(--ds-text); font-weight: 600;';
?>

<?php $view->section('title') ?><?= $e((string) $t['razon_social']) ?> | Superadmin<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-company-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .7rem;
    }
    .ds-company-item {
        grid-column: span 6;
        padding: .7rem .8rem;
        border: 1px solid rgba(21, 48, 79, 0.09);
        border-radius: 12px;
        background: linear-gradient(180deg, #fcfdff, #f7f9fc);
        min-width: 0;
    }
    .ds-company-item.span-3 { grid-column: span 3; }
    .ds-company-item.span-4 { grid-column: span 4; }
    .ds-company-item.span-6 { grid-column: span 6; }
    .ds-company-item.span-8 { grid-column: span 8; }
    .ds-company-item.span-12 { grid-column: span 12; }
    .ds-company-label {
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ds-text-soft);
        margin-bottom: .2rem;
        line-height: 1.2;
    }
    .ds-company-value {
        font-size: .93rem;
        font-weight: 600;
        color: var(--ds-text);
        line-height: 1.3;
    }
    .ds-company-value.break {
        white-space: normal;
        word-break: break-word;
    }
    .ds-company-item .ds-cell-mono {
        font-size: .93rem;
        line-height: 1.3;
        color: var(--ds-text);
    }
    @media (max-width: 1199.98px) {
        .ds-company-item.span-3 { grid-column: span 4; }
        .ds-company-item.span-8 { grid-column: span 12; }
    }
    @media (max-width: 767.98px) {
        .ds-company-item,
        .ds-company-item.span-3,
        .ds-company-item.span-4,
        .ds-company-item.span-6,
        .ds-company-item.span-8,
        .ds-company-item.span-12 {
            grid-column: span 12;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-building"></i></span>
        <div>
            <h1><?= $e((string) $t['razon_social']) ?></h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-cell-mono">RUC <?= $e((string) $t['ruc']) ?></span>
                <span>&middot;</span>
                <span class="ds-cell-mono"><?= $e((string) $t['email_contacto']) ?></span>
                <span class="ds-badge <?= $estadoTone[(string) $t['estado']] ?? 'tone-muted' ?>"><?= $e((string) $t['estado']) ?></span>
            </small>
        </div>
    </div>
    <div style="display:flex; gap:.5rem;">
        <a href="<?= $view->url('/admin/tenants/' . $e((string) $t['id']) . '/editar') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-pencil"></i>Editar tenant
        </a>
        <a href="<?= $view->url('/admin/tenants') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver al listado
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Info + Uso -->
    <div class="col-lg-8">
        <!-- Datos generales -->
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="ds-card-title">Informacion general</div>
                    <div class="ds-card-subtitle">Datos administrativos del tenant</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-company-grid">
                    <div class="ds-company-item span-8">
                        <div class="ds-company-label">Razon social</div>
                        <div class="ds-company-value break"><?= $e((string) ($t['razon_social'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Nombre comercial</div>
                        <div class="ds-company-value break"><?= $e((string) ($t['nombre_comercial'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">RUC</div>
                        <div class="ds-cell-mono" style="font-weight: 700;"><?= $e((string) ($t['ruc'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-8">
                        <div class="ds-company-label">Email de contacto</div>
                        <div class="ds-cell-mono"><?= $e((string) ($t['email_contacto'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Telefono</div>
                        <div class="ds-company-value"><?= $e((string) ($t['telefono'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-8">
                        <div class="ds-company-label">Direccion</div>
                        <div class="ds-company-value break"><?= $e((string) ($t['direccion'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Ciudad</div>
                        <div class="ds-company-value"><?= $e((string) ($t['ciudad'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Provincia</div>
                        <div class="ds-company-value"><?= $e((string) ($t['provincia'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Pais</div>
                        <div class="ds-company-value"><?= $e((string) ($t['pais'] ?? 'EC')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Plan</div>
                        <div class="ds-company-value break">
                            <?= $e((string) ($t['plan_codigo'] ?? '-')) ?>
                            <?php if (!empty($t['plan_nombre'])): ?>
                                · <?= $e((string) $t['plan_nombre']) ?>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Inicio plan</div>
                        <div class="ds-cell-mono"><?= $e(substr((string) ($t['plan_inicio'] ?? '-'), 0, 10)) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Vencimiento</div>
                        <div class="ds-cell-mono"><?= $e(substr((string) ($t['plan_vencimiento'] ?? '-'), 0, 10)) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Zona horaria</div>
                        <div class="ds-cell-mono"><?= $e((string) ($t['timezone'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Locale</div>
                        <div class="ds-cell-mono"><?= $e((string) ($t['locale'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Tenant ID</div>
                        <div class="ds-cell-mono"><?= $e((string) ($t['id'] ?? '-')) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Registrado</div>
                        <div class="ds-cell-mono"><?= $e(substr((string) ($t['created_at'] ?? '-'), 0, 16)) ?></div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Activado</div>
                        <div class="ds-cell-mono">
                            <?php if (!empty($t['activado_at'])): ?>
                                <?= $e(substr((string) $t['activado_at'], 0, 16)) ?>
                            <?php else: ?>
                                <span class="ds-badge tone-warning no-dot">No activado</span>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="ds-company-item span-4">
                        <div class="ds-company-label">Estado</div>
                        <div><span class="ds-badge <?= $estadoTone[(string) ($t['estado'] ?? '')] ?? 'tone-muted' ?>"><?= $e(ucfirst((string) ($t['estado'] ?? ''))) ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uso -->
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-speedometer2"></i></span>
                <div>
                    <div class="ds-card-title">Uso actual</div>
                    <div class="ds-card-subtitle">Cuotas consumidas vs limites del plan</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Usuarios (cupo del plan)</div>
                        <div style="<?= $ds_dd ?>; font-size: 1.05rem;"><?= $usrAct ?> <span style="color: var(--ds-text-soft); font-weight: 500; font-size: .85rem;">/ <?= $maxUsuarios ?: 'Ilim.' ?></span></div>
                        <?php if ($maxUsuarios > 0): ?>
                            <div class="ds-progress" style="margin-top: .35rem;">
                                <div class="ds-progress-bar <?= $progressTone($pctUsr) ?>" style="width: <?= $pctUsr ?>%"></div>
                            </div>
                            <div class="ds-cell-sub" style="margin-top: .25rem;"><?= $pctUsr ?>% utilizado</div>
                        <?php endif ?>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Guardias (cupo del plan)</div>
                        <div style="<?= $ds_dd ?>; font-size: 1.05rem;"><?= $fichas ?> <span style="color: var(--ds-text-soft); font-weight: 500; font-size: .85rem;">/ <?= $maxGuardias ?: 'Ilim.' ?></span></div>
                        <?php if ($maxGuardias > 0): ?>
                            <div class="ds-progress" style="margin-top: .35rem;">
                                <div class="ds-progress-bar <?= $progressTone($pctFic) ?>" style="width: <?= $pctFic ?>%"></div>
                            </div>
                            <div class="ds-cell-sub" style="margin-top: .25rem;"><?= $pctFic ?>% utilizado</div>
                        <?php endif ?>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Almacenamiento</div>
                        <div style="<?= $ds_dd ?>; font-size: 1.05rem;"><?= $storageMb ?> MB <span style="color: var(--ds-text-soft); font-weight: 500; font-size: .85rem;">/ <?= $maxStorage ?: 'Ilim.' ?> MB</span></div>
                        <?php if ($maxStorage > 0): ?>
                            <div class="ds-progress" style="margin-top: .35rem;">
                                <div class="ds-progress-bar <?= $progressTone($pctSto) ?>" style="width: <?= $pctSto ?>%"></div>
                            </div>
                            <div class="ds-cell-sub" style="margin-top: .25rem;"><?= $pctSto ?>% utilizado</div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usuarios del tenant -->
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-people"></i></span>
                <div>
                    <div class="ds-card-title">Usuarios</div>
                    <div class="ds-card-subtitle"><?= count($usuarios) ?> cuenta<?= count($usuarios) !== 1 ? 's' : '' ?> registrada<?= count($usuarios) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($usuarios === []): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
                    <div class="ds-empty-icon"><i class="bi bi-person-slash"></i></div>
                    <p>Sin usuarios registrados.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Ultimo login</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><div class="ds-cell-name"><?= $e(trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? ''))) ?></div></td>
                            <td><span class="ds-cell-mono"><?= $e((string) $u['email']) ?></span></td>
                            <td><span class="ds-cell-sub"><?= $e((string) ($u['roles'] ?? '-')) ?></span></td>
                            <td>
                                <?php if (!empty($u['ultimo_login_at'])): ?>
                                    <span class="ds-cell-mono"><?= $e(substr((string) $u['ultimo_login_at'], 0, 16)) ?></span>
                                <?php else: ?>
                                    <span class="ds-cell-sub">Nunca</span>
                                <?php endif ?>
                            </td>
                            <td><span class="ds-badge <?= $estadoTone[(string) $u['estado']] ?? 'tone-muted' ?>"><?= $e((string) $u['estado']) ?></span></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>

        <!-- Ciclos de suscripcion -->
        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-gem"></i></span>
                <div>
                    <div class="ds-card-title">Historial de suscripcion</div>
                    <div class="ds-card-subtitle">Ciclos contratados y renovaciones</div>
                </div>
            </div>
            <?php if ($ciclos === []): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
                    <div class="ds-empty-icon"><i class="bi bi-gem"></i></div>
                    <p>Sin ciclos de suscripcion registrados.</p>
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
                            <th class="ds-cell-end">USD</th>
                            <th>Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ciclos as $c): ?>
                        <tr>
                            <td><div class="ds-cell-name"><?= $e((string) ($c['plan_nombre'] ?? $c['plan_codigo'])) ?></div></td>
                            <td><span class="ds-chip"><?= $e((string) $c['tipo']) ?></span></td>
                            <td><span class="ds-cell-mono"><?= $e(substr((string) $c['fecha_inicio'], 0, 10)) ?></span></td>
                            <td><span class="ds-cell-mono"><?= $e(substr((string) $c['fecha_fin'], 0, 10)) ?></span></td>
                            <td class="ds-cell-end ds-cell-mono">$<?= number_format((float) $c['precio_usd'], 2) ?></td>
                            <td><span class="ds-badge <?= $pagoTone((string) $c['estado_pago']) ?>"><?= $e((string) $c['estado_pago']) ?></span></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Panel derecho: Acciones -->
    <div class="col-lg-4">
        <!-- Cambiar estado -->
        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-toggles"></i></span>
                <div>
                    <div class="ds-card-title">Cambiar estado</div>
                    <div class="ds-card-subtitle">Activar, suspender o cancelar</div>
                </div>
            </div>
            <div class="ds-card-body">
                <form class="ds-form-main" method="POST" action="<?= $view->url('/admin/tenants/' . $e((string) $t['id']) . '/estado') ?>"
                      data-confirm
                      data-confirm-title="Cambiar estado del tenant"
                      data-confirm-text="Se aplicara el nuevo estado seleccionado al tenant."
                      data-confirm-type="warning"
                      data-confirm-button="Si, aplicar">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <div class="ds-form-field" style="margin-bottom: .85rem;">
                        <label class="ds-form-label">Nuevo estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <?php foreach (['activo','pendiente','suspendido','cancelado'] as $est): ?>
                            <option value="<?= $est ?>" <?= (string) $t['estado'] === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <button type="submit" class="ds-btn ds-btn-primary" style="width: 100%;">
                        <i class="bi bi-arrow-repeat"></i>Aplicar cambio
                    </button>
                </form>
            </div>
        </div>

        <!-- Cambiar plan -->
        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-gem"></i></span>
                <div>
                    <div class="ds-card-title">Asignar plan</div>
                    <div class="ds-card-subtitle">Cortesia administrativa</div>
                </div>
            </div>
            <div class="ds-card-body">
                <form class="ds-form-main" method="POST" action="<?= $view->url('/admin/tenants/' . $e((string) $t['id']) . '/plan') ?>"
                      data-confirm
                      data-confirm-title="Asignar plan"
                      data-confirm-text="Se asignara el plan seleccionado al tenant."
                      data-confirm-type="question"
                      data-confirm-button="Si, asignar">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <div class="ds-form-field" style="margin-bottom: .65rem;">
                        <label class="ds-form-label">Plan</label>
                        <select name="plan_codigo" class="form-select form-select-sm">
                            <?php foreach ($planes as $p): ?>
                            <option value="<?= $e($p->codigo) ?>" <?= (string) $t['plan_codigo'] === $p->codigo ? 'selected' : '' ?>>
                                <?= $e($p->nombre) ?> &middot; $<?= number_format($p->precioMensualUsd, 0) ?>/mes
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="ds-form-field" style="margin-bottom: .85rem;">
                        <label class="ds-form-label">Duracion (meses)</label>
                        <input type="number" name="meses" class="form-control form-control-sm" value="12" min="1" max="60">
                    </div>
                    <button type="submit" class="ds-btn ds-btn-primary" style="width: 100%;">
                        <i class="bi bi-arrow-up-circle"></i>Asignar plan
                    </button>
                </form>
            </div>
        </div>

        <!-- Asociar usuarios -->
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-person-plus"></i></span>
                <div>
                    <div class="ds-card-title">Asociar usuario</div>
                    <div class="ds-card-subtitle">Mover usuario de otro tenant a este tenant</div>
                </div>
            </div>
            <div class="ds-card-body">
                <?php if ($usuariosDisponibles === []): ?>
                    <div class="ds-empty-state" style="padding: 1rem .75rem;">
                        <p style="margin:0;">No hay usuarios disponibles para asociar.</p>
                    </div>
                <?php else: ?>
                    <form class="ds-form-main" method="POST" action="<?= $view->url('/admin/tenants/' . $e((string) $t['id']) . '/usuarios/asociar') ?>">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                        <div class="ds-form-field" style="margin-bottom: .75rem;">
                            <label class="ds-form-label">Usuario origen</label>
                            <select name="usuario_id" class="form-select form-select-sm" required>
                                <option value="">Selecciona usuario</option>
                                <?php foreach ($usuariosDisponibles as $u): ?>
                                <option value="<?= $e((string) $u['id']) ?>">
                                    <?= $e(trim((string) $u['apellidos'] . ' ' . (string) $u['nombres'])) ?>
                                    &middot; <?= $e((string) $u['email']) ?>
                                    &middot; <?= $e((string) $u['tenant_actual']) ?>
                                </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="ds-form-field" style="margin-bottom: .85rem;">
                            <label class="ds-form-label">Rol en tenant destino</label>
                            <select name="role_id" class="form-select form-select-sm">
                                <option value="">Sin rol inicial</option>
                                <?php foreach ($rolesDisponibles as $r): ?>
                                <option value="<?= $e((string) $r['id']) ?>">
                                    <?= $e((string) $r['nombre']) ?> (<?= $e((string) $r['codigo']) ?>)
                                </option>
                                <?php endforeach ?>
                            </select>
                            <span class="ds-form-help">Si no seleccionas rol, el usuario quedara sin permisos hasta asignarle uno.</span>
                        </div>
                        <button type="submit" class="ds-btn ds-btn-primary" style="width: 100%;">
                            <i class="bi bi-link-45deg"></i>Asociar usuario
                        </button>
                    </form>
                <?php endif ?>
            </div>
        </div>

        <?php if ($registros !== []): ?>
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
                <div>
                    <div class="ds-card-title">Log de registro</div>
                    <div class="ds-card-subtitle">Trazabilidad del auto-registro</div>
                </div>
            </div>
            <div style="padding: .5rem;">
                <?php foreach (array_slice($registros, 0, 8) as $r):
                    $tone = $eventoTone[(string) $r['evento']] ?? 'tone-muted';
                ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: .5rem .75rem; border-radius: 8px;">
                    <span class="ds-cell-mono" style="font-size: .76rem;"><?= $e(substr((string) $r['created_at'], 0, 16)) ?></span>
                    <span class="ds-badge <?= $tone ?>"><?= $e((string) $r['evento']) ?></span>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
    </div>
</div>
<?php $view->endSection() ?>

