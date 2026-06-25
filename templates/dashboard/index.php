<?php
/**
 * Dashboard principal con foco operativo.
 *
 * Variables: $user, $csrfToken, $currentPath, $stats
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */
/** @var array<string, mixed> $stats */

$view->layout('layout/app');
$e = ViewRenderer::e(...);
$statsError = (bool) ($statsError ?? false);

$guardiasActivos = (int) ($stats['guardias_activos'] ?? 0);
$fichasRevision = (int) ($stats['fichas_revision'] ?? 0);
$fichasBorrador = (int) ($stats['fichas_borrador'] ?? 0);
$docsPorVencer = (int) ($stats['docs_por_vencer'] ?? 0);
$docsVencidos = (int) ($stats['docs_vencidos'] ?? 0);
$expedientesCompletos = (int) ($stats['expedientes_completos'] ?? 0);
$expedientesIncompletos = (int) ($stats['expedientes_incompletos'] ?? 0);

$guardiasTotales = $guardiasActivos + $fichasRevision + $fichasBorrador;
$expedientesTotales = $expedientesCompletos + $expedientesIncompletos;
$docsAtencion = $docsPorVencer + $docsVencidos;

$guardiasCompletitud = $guardiasTotales > 0 ? (int) round(($guardiasActivos / $guardiasTotales) * 100) : 0;
$expedientesCompletitud = $expedientesTotales > 0 ? (int) round(($expedientesCompletos / $expedientesTotales) * 100) : 0;
$saludDocumental = $docsAtencion === 0 ? 100 : max(0, 100 - min(100, $docsAtencion * 10));

$roles = array_values(array_filter(array_map(
    static fn(array $role): string => trim((string) ($role['nombre'] ?? '')),
    $user->roles,
)));

$puedeCrearGuardia = $user->tienePermiso('guardias.crear');
$puedeVerExpedientes = $user->tienePermiso('expedientes.ver');
$puedeVerReportes = $user->tienePermiso('reportes.ver');
$puedeVerUsuarios = $user->tienePermiso('usuarios.ver');
$puedeVerAuditoria = $user->tienePermiso('auditoria.ver');

$tieneAccionesRapidas = $puedeCrearGuardia || $puedeVerExpedientes || $puedeVerReportes || $puedeVerUsuarios;
?>

<?php $view->section('title') ?>Dashboard | DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-dashboard-topbar {
        margin-bottom: 1.1rem;
    }

    .ds-dashboard-toolbar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.55rem;
    }

    .ds-dashboard-section {
        margin-bottom: 2rem;
    }

    .ds-dashboard-section:last-child {
        margin-bottom: 0;
    }

    .ds-dashboard-section > .row {
        --bs-gutter-x: 1.6rem;
        --bs-gutter-y: 1.6rem;
    }

    .ds-dashboard-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.9rem;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        background: rgba(47, 128, 237, 0.12);
        color: #1d4d8f;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .ds-hero-card {
        overflow: hidden;
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(47, 128, 237, 0.18), transparent 34%),
            linear-gradient(135deg, #15304f, #1d4d8f);
        color: #fff;
        position: relative;
    }

    .ds-hero-card .card-body {
        padding: clamp(1.45rem, 2.25vw, 2.65rem) !important;
    }

    .ds-hero-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(176px, 220px);
        align-items: center;
        column-gap: clamp(1rem, 2vw, 2.15rem);
        row-gap: 1.2rem;
        min-height: 250px;
    }

    .ds-hero-copy {
        max-width: 780px;
        display: flex;
        flex-direction: column;
        gap: 0.95rem;
    }

    .ds-hero-copy h2 {
        margin: 0;
        line-height: 1.18;
        font-size: clamp(1.3rem, 1.65vw, 1.9rem);
    }

    .ds-hero-copy p {
        margin: 0;
        max-width: 64ch;
    }

    .ds-hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem 0.65rem;
        margin-top: 0.1rem;
    }

    .ds-hero-ring-wrap {
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .ds-hero-card p,
    .ds-hero-card .text-white-50 {
        color: rgba(255, 255, 255, 0.76) !important;
    }

    .ds-hero-card .ds-dashboard-eyebrow {
        background: rgba(125, 211, 252, 0.18);
        color: #e0f2fe;
        border: 1px solid rgba(186, 230, 253, 0.28);
    }

    .ds-hero-ring {
        width: 176px;
        height: 176px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(circle, rgba(255, 255, 255, 0.14) 40%, transparent 41%),
            conic-gradient(#7dd3fc <?= $guardiasCompletitud ?>%, rgba(255, 255, 255, 0.12) 0);
        box-shadow: inset 0 0 0 18px rgba(7, 20, 38, 0.24);
    }

    .ds-hero-ring span {
        display: block;
        max-width: 132px;
        margin: 0 auto;
        text-align: center;
        font-weight: 700;
        line-height: 1.15;
    }

    .ds-hero-ring small {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.64);
    }

    .ds-chip-soft {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .ds-priority-card {
        border-radius: 24px;
        background: linear-gradient(180deg, #fff8f2, #ffffff);
        border: 1px solid rgba(242, 153, 74, 0.18);
    }

    .ds-priority-card .card-body {
        height: 100%;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        padding: clamp(1.35rem, 2vw, 2.25rem) !important;
    }

    .ds-priority-head {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .ds-priority-title {
        margin: 0;
        line-height: 1.22;
        font-size: clamp(1.1rem, 1.35vw, 1.45rem);
    }

    .ds-priority-grid {
        display: grid;
        gap: 0.7rem;
        margin-top: 0.1rem;
    }

    .ds-priority-stat {
        display: grid;
        gap: 0.35rem;
        padding: 0.82rem 0.92rem;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(21, 48, 79, 0.1);
    }

    .ds-priority-stat strong {
        display: block;
        font-size: clamp(1.35rem, 1.8vw, 1.7rem);
        line-height: 1;
    }

    .ds-surface-card {
        border: 0;
        border-radius: 24px;
        box-shadow: 0 16px 30px rgba(18, 45, 77, 0.08);
    }

    .ds-surface-card .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.15rem 0;
        background: transparent;
        border: 0;
    }

    .ds-surface-card .card-body {
        padding: 1rem 1.15rem 1.15rem;
    }

    .ds-activity-table thead th {
        font-size: 0.73rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .ds-activity-table tbody td {
        font-size: 0.86rem;
    }

    .ds-action-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.8rem;
    }

    .ds-action-tile {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        min-height: 108px;
        padding: 0.95rem;
        border-radius: 18px;
        text-decoration: none;
        color: #16324f;
        background: linear-gradient(180deg, rgba(47, 128, 237, 0.08), rgba(255, 255, 255, 0.9));
        border: 1px solid rgba(47, 128, 237, 0.12);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .ds-action-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 28px rgba(18, 45, 77, 0.12);
        color: #16324f;
    }

    .ds-action-tile i {
        font-size: 1.05rem;
    }

    .ds-health-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }

    .ds-health-row .progress {
        height: 10px;
        border-radius: 999px;
        background: rgba(21, 48, 79, 0.08);
    }

    .ds-mini-chart-wrap {
        display: grid;
        grid-template-columns: minmax(0, 170px) minmax(0, 1fr);
        gap: 1rem;
        align-items: center;
    }

    .ds-mini-legend {
        display: grid;
        gap: 0.7rem;
    }

    .ds-mini-legend-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.6rem 0.75rem;
        border-radius: 16px;
        background: rgba(21, 48, 79, 0.04);
    }

    .ds-mini-legend-item span:first-child {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #5f7288;
        font-size: 0.84rem;
    }

    .ds-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }

    @media (min-width: 1200px) {
        .ds-action-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .ds-mini-chart-wrap {
            grid-template-columns: 1fr;
        }

        .ds-hero-layout {
            grid-template-columns: 1fr;
            min-height: 0;
        }

        .ds-hero-ring-wrap {
            justify-content: center;
            padding-top: 0.2rem;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-speedometer2 me-1"></i>Dashboard</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header ds-dashboard-topbar">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-speedometer2"></i></span>
        <div>
            <h1>Dashboard operativo</h1>
            <small>Seguimiento diario de guardias, expedientes y cumplimiento documental</small>
        </div>
    </div>
    <div class="ds-dashboard-toolbar">
        <?php if ($puedeCrearGuardia): ?>
            <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                <i class="bi bi-person-plus"></i>Nueva ficha
            </a>
        <?php endif; ?>
        <?php if ($puedeVerExpedientes): ?>
            <a href="<?= $view->url('/expedientes/pendientes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-clipboard-check"></i>Pendientes
            </a>
        <?php endif; ?>
        <?php if ($puedeVerReportes): ?>
            <a href="<?= $view->url('/reportes/kpis') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-graph-up-arrow"></i>KPIs
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($statsError): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            No se pudieron cargar las metricas del dashboard. Puedes continuar usando el sistema mientras soporte revisa el log del servidor.
        </div>
    </div>
<?php endif; ?>

<section class="ds-dashboard-section">
    <div class="row g-4">
        <div class="col-12">
            <section class="card ds-hero-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="ds-hero-layout">
                        <div class="ds-hero-copy">
                            <span class="ds-dashboard-eyebrow">
                                <i class="bi bi-stars"></i>Centro de control diario
                            </span>
                            <h2>Hola, <?= $e($user->nombres) ?>. Hoy la operacion pide foco en lo esencial.</h2>
                            <p>
                                Tienes <strong><?= $docsAtencion ?></strong> documentos que necesitan atencion
                                y <strong><?= $expedientesIncompletos ?></strong> expedientes que aun no estan completos.
                            </p>
                            <div class="ds-hero-meta">
                                <span class="ds-chip-soft">
                                    <i class="bi bi-building"></i><?= $e($user->tenantNombre) ?>
                                </span>
                                <?php if ($roles !== []): ?>
                                    <span class="ds-chip-soft">
                                        <i class="bi bi-person-badge"></i><?= $e(implode(' / ', array_slice($roles, 0, 2))) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="ds-chip-soft">
                                    <i class="bi bi-clock"></i><?= date('d/m/Y H:i') ?>
                                </span>
                            </div>
                        </div>
                        <div class="ds-hero-ring-wrap">
                            <div class="ds-hero-ring">
                                <span>
                                    <?= $guardiasCompletitud ?>%
                                    <small>fichas activas sobre el total visible</small>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12">
            <section class="card ds-priority-card shadow-sm h-100">
                <div class="card-body">
                    <div class="ds-priority-head">
                        <div class="text-uppercase fw-bold small text-warning">Atencion prioritaria</div>
                        <h2 class="ds-priority-title">Lo que conviene revisar primero</h2>
                    </div>

                    <div class="ds-priority-grid">
                        <div class="ds-priority-stat">
                            <span class="text-muted small">Documentos vencidos</span>
                            <strong class="text-danger"><?= $docsVencidos ?></strong>
                            <span class="text-muted small">Requieren renovacion o seguimiento inmediato.</span>
                        </div>

                        <div class="ds-priority-stat">
                            <span class="text-muted small">Por vencer en 30 dias</span>
                            <strong class="text-warning"><?= $docsPorVencer ?></strong>
                            <span class="text-muted small">Ideal para activar recordatorios o carga preventiva.</span>
                        </div>

                        <div class="ds-priority-stat">
                            <span class="text-muted small">Expedientes incompletos</span>
                            <strong class="text-primary"><?= $expedientesIncompletos ?></strong>
                            <span class="text-muted small">Seguimiento pendiente antes de cerrar el ciclo documental.</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<section class="ds-dashboard-section">
    <div class="ds-kpi-grid ds-kpi-grid-solid">
        <a href="<?= $view->url('/guardias') ?>" class="ds-kpi-card" style="text-decoration: none;">
            <span class="ds-kpi-icon"><i class="bi bi-person-badge-fill"></i></span>
            <div style="flex:1; min-width:0;">
                <div class="ds-kpi-label">Guardias activos</div>
                <div class="ds-kpi-value"><?= $guardiasActivos ?></div>
                <div class="ds-cell-sub" style="margin-top:.25rem; display:flex; gap:.35rem; flex-wrap:wrap;">
                    <?php if ($fichasRevision > 0): ?>
                    <span class="ds-badge tone-warning no-dot" style="font-size:.65rem;"><?= $fichasRevision ?> en revision</span>
                    <?php endif ?>
                    <?php if ($fichasBorrador > 0): ?>
                    <span class="ds-badge tone-muted no-dot" style="font-size:.65rem;"><?= $fichasBorrador ?> borrador</span>
                    <?php endif ?>
                    <?php if ($fichasRevision === 0 && $fichasBorrador === 0): ?>
                    <span style="color: var(--ds-text-soft);">Sin pendientes</span>
                    <?php endif ?>
                </div>
            </div>
        </a>

        <a href="<?= $view->url('/expedientes') ?>" class="ds-kpi-card tone-warning" style="text-decoration: none;">
            <span class="ds-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
            <div style="flex:1; min-width:0;">
                <div class="ds-kpi-label">Por vencer (30 dias)</div>
                <div class="ds-kpi-value"><?= $docsPorVencer ?></div>
                <div class="ds-cell-sub" style="margin-top:.25rem;">Documentos por renovar pronto</div>
            </div>
        </a>

        <a href="<?= $view->url('/expedientes') ?>" class="ds-kpi-card tone-danger" style="text-decoration: none;">
            <span class="ds-kpi-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
            <div style="flex:1; min-width:0;">
                <div class="ds-kpi-label">Vencidos</div>
                <div class="ds-kpi-value"><?= $docsVencidos ?></div>
                <div class="ds-cell-sub" style="margin-top:.25rem;"><?= $docsVencidos === 0 ? 'Todo al dia' : 'Requieren accion inmediata' ?></div>
            </div>
        </a>

        <a href="<?= $view->url('/expedientes') ?>" class="ds-kpi-card tone-success" style="text-decoration: none;">
            <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
            <div style="flex:1; min-width:0;">
                <div class="ds-kpi-label">Expedientes completos</div>
                <div class="ds-kpi-value"><?= $expedientesCompletos ?></div>
                <div class="ds-cell-sub" style="margin-top:.25rem;">
                    <?php if ($expedientesIncompletos > 0): ?>
                        <?= $expedientesIncompletos ?> aun por completar
                    <?php else: ?>
                        Todos los expedientes cerrados
                    <?php endif ?>
                </div>
            </div>
        </a>
    </div>
</section>

<section class="ds-dashboard-section">
    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card ds-surface-card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-activity me-2 text-primary"></i>Actividad reciente</h2>
                        <p class="text-muted small mb-0">Ultimos movimientos relevantes del sistema.</p>
                    </div>
                    <?php if ($puedeVerAuditoria): ?>
                        <a href="<?= $view->url('/auditoria') ?>" class="btn btn-outline-primary btn-sm">Ver auditoria</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($stats['actividad_reciente'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No hay actividad reciente registrada.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table ds-activity-table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Evento</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($stats['actividad_reciente'] as $act): ?>
                                    <tr>
                                        <td class="small text-muted"><?= $e($act['fecha']) ?></td>
                                        <td><?= $e($act['evento']) ?></td>
                                        <td class="small text-muted"><?= $e($act['usuario']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card ds-surface-card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-lightning-charge me-2 text-warning"></i>Acciones rapidas</h2>
                        <p class="text-muted small mb-0">Tareas frecuentes segun tus permisos.</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!$tieneAccionesRapidas): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-shield-lock fs-4 d-block mb-2"></i>
                            Tu perfil no tiene acciones directas en este panel.
                        </div>
                    <?php else: ?>
                        <div class="ds-action-grid">
                            <?php if ($puedeCrearGuardia): ?>
                                <a href="<?= $view->url('/guardias/crear') ?>" class="ds-action-tile">
                                    <i class="bi bi-person-plus text-primary"></i>
                                    <strong>Nueva ficha</strong>
                                    <span class="small text-muted">Alta rapida de guardias.</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($puedeVerExpedientes): ?>
                                <a href="<?= $view->url('/expedientes/pendientes') ?>" class="ds-action-tile">
                                    <i class="bi bi-clipboard-check text-warning"></i>
                                    <strong>Validar expedientes</strong>
                                    <span class="small text-muted">Ir al flujo pendiente.</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($puedeVerReportes): ?>
                                <a href="<?= $view->url('/reportes/kpis') ?>" class="ds-action-tile">
                                    <i class="bi bi-graph-up-arrow text-success"></i>
                                    <strong>Ver KPIs</strong>
                                    <span class="small text-muted">Panel ejecutivo completo.</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($puedeVerUsuarios): ?>
                                <a href="<?= $view->url('/usuarios') ?>" class="ds-action-tile">
                                    <i class="bi bi-people text-info"></i>
                                    <strong>Gestionar usuarios</strong>
                                    <span class="small text-muted">Accesos y estados.</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</section>

<section class="ds-dashboard-section">
    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card ds-surface-card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-heart-pulse me-2 text-success"></i>Salud operativa</h2>
                        <p class="text-muted small mb-0">Lectura rapida de avance y cumplimiento.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="ds-health-grid">
                        <div class="ds-health-row">
                            <div class="d-flex justify-content-between small mb-2">
                                <span>Fichas activas</span>
                                <strong><?= $guardiasCompletitud ?>%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: <?= $guardiasCompletitud ?>%"></div>
                            </div>
                        </div>

                        <div class="ds-health-row">
                            <div class="d-flex justify-content-between small mb-2">
                                <span>Expedientes completos</span>
                                <strong><?= $expedientesCompletitud ?>%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= $expedientesCompletitud ?>%"></div>
                            </div>
                        </div>

                        <div class="ds-health-row">
                            <div class="d-flex justify-content-between small mb-2">
                                <span>Salud documental</span>
                                <strong><?= $saludDocumental ?>%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" style="width: <?= $saludDocumental ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card ds-surface-card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-pie-chart me-2 text-info"></i>Distribucion de fichas</h2>
                        <p class="text-muted small mb-0">Avance del registro de guardias.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="ds-mini-chart-wrap">
                        <div>
                            <canvas id="miniChartFichas" style="max-height: 190px;"></canvas>
                        </div>
                        <div class="ds-mini-legend">
                            <div class="ds-mini-legend-item">
                                <span><i class="ds-dot" style="background:#27ae60"></i>Activas</span>
                                <strong><?= $guardiasActivos ?></strong>
                            </div>
                            <div class="ds-mini-legend-item">
                                <span><i class="ds-dot" style="background:#f39c12"></i>En revision</span>
                                <strong><?= $fichasRevision ?></strong>
                            </div>
                            <div class="ds-mini-legend-item">
                                <span><i class="ds-dot" style="background:#7f8c8d"></i>Borrador</span>
                                <strong><?= $fichasBorrador ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
    const canvas = document.getElementById('miniChartFichas');
    if (!canvas) {
        return;
    }

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Activas', 'En revision', 'Borrador'],
            datasets: [{
                data: [<?= $guardiasActivos ?>, <?= $fichasRevision ?>, <?= $fichasBorrador ?>],
                backgroundColor: ['#27ae60', '#f39c12', '#7f8c8d'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            cutout: '68%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
})();
</script>
<?php $view->endSection() ?>
