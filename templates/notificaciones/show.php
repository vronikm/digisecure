<?php
/**
 * Detalle de una notificacion + historial de intentos.
 *
 * Variables: $user, $csrfToken, $currentPath, $notificacion, $intentos
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
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

$ds_dt = 'font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--ds-text-soft); padding: .55rem 0 .15rem;';
$ds_dd = 'font-size: .88rem; color: var(--ds-text); padding: 0 0 .55rem; margin: 0; border-bottom: 1px solid #f1f5f9;';
?>

<?php $view->section('title') ?>Notificacion — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/notificaciones') ?>">Notificaciones</a></li>
<li class="breadcrumb-item active">Detalle</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-bell-fill"></i></span>
        <div>
            <h1>Detalle de notificacion</h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-cell-mono"><?= $e((string) $notificacion['evento']) ?></span>
                <span class="ds-badge <?= $estadoTone((string) $notificacion['estado']) ?>"><?= $e((string) $notificacion['estado']) ?></span>
            </small>
        </div>
    </div>
    <div style="display: flex; gap: .5rem;">
        <?php if ($notificacion['estado'] === 'FALLIDA'): ?>
        <form class="ds-form-main" method="post" action="<?= $view->url('/notificaciones/' . $e((string) $notificacion['id']) . '/retry') ?>"
              onsubmit="return confirm('Reintentar el envio de esta notificacion?');" style="display: inline;">
            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                <i class="bi bi-arrow-clockwise"></i>Reintentar
            </button>
        </form>
        <?php endif ?>
        <a href="<?= $view->url('/notificaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Info principal -->
    <div class="col-lg-8">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="ds-card-title">Informacion</div>
                    <div class="ds-card-subtitle">Metadatos y destinatario</div>
                </div>
            </div>
            <div class="ds-card-body">
                <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 0;">
                    <dt style="<?= $ds_dt ?>">ID</dt>
                    <dd style="<?= $ds_dd ?>"><span class="ds-cell-mono"><?= $e((string) $notificacion['id']) ?></span></dd>

                    <dt style="<?= $ds_dt ?>">Evento</dt>
                    <dd style="<?= $ds_dd ?>"><span class="ds-cell-mono"><?= $e((string) $notificacion['evento']) ?></span></dd>

                    <dt style="<?= $ds_dt ?>">Canal</dt>
                    <dd style="<?= $ds_dd ?>">
                        <span class="ds-chip"><i class="bi <?= $canalIcon((string) $notificacion['canal']) ?>"></i><?= $e((string) $notificacion['canal']) ?></span>
                    </dd>

                    <dt style="<?= $ds_dt ?>">Prioridad</dt>
                    <dd style="<?= $ds_dd ?>"><?= $e((string) $notificacion['prioridad']) ?></dd>

                    <dt style="<?= $ds_dt ?>">Estado</dt>
                    <dd style="<?= $ds_dd ?>"><span class="ds-badge <?= $estadoTone((string) $notificacion['estado']) ?>"><?= $e((string) $notificacion['estado']) ?></span></dd>

                    <dt style="<?= $ds_dt ?>">Destinatario</dt>
                    <dd style="<?= $ds_dd ?>">
                        <?php if (!empty($notificacion['destinatario_nombre'])): ?>
                            <div style="font-weight: 600;"><?= $e((string) $notificacion['destinatario_nombre']) ?></div>
                        <?php endif ?>
                        <?php if (!empty($notificacion['destinatario_email'])): ?>
                            <div class="ds-cell-mono"><i class="bi bi-envelope"></i> <?= $e((string) $notificacion['destinatario_email']) ?></div>
                        <?php endif ?>
                        <?php if (!empty($notificacion['destinatario_telefono'])): ?>
                            <div class="ds-cell-mono"><i class="bi bi-telephone"></i> <?= $e((string) $notificacion['destinatario_telefono']) ?></div>
                        <?php endif ?>
                    </dd>

                    <dt style="<?= $ds_dt ?>">Asunto</dt>
                    <dd style="<?= $ds_dd ?>"><?= $e((string) $notificacion['asunto']) ?></dd>

                    <dt style="<?= $ds_dt ?>">Creada</dt>
                    <dd style="<?= $ds_dd ?>"><span class="ds-cell-mono"><?= $e((string) $notificacion['created_at']) ?></span></dd>

                    <dt style="<?= $ds_dt ?>">Programada</dt>
                    <dd style="<?= $ds_dd ?>"><span class="ds-cell-mono"><?= $e((string) ($notificacion['scheduled_at'] ?? '—')) ?></span></dd>

                    <dt style="<?= $ds_dt ?>">Enviada</dt>
                    <dd style="<?= $ds_dd ?>"><span class="ds-cell-mono"><?= $e((string) ($notificacion['enviada_at'] ?? '—')) ?></span></dd>

                    <dt style="<?= $ds_dt ?>">Intentos</dt>
                    <dd style="<?= $ds_dd ?>"><span class="ds-chip"><?= (int) $notificacion['intentos'] ?> / <?= (int) $notificacion['max_intentos'] ?></span></dd>

                    <?php if (!empty($notificacion['ultimo_error'])): ?>
                    <dt style="<?= $ds_dt ?>; color: #b91c1c;">Ultimo error</dt>
                    <dd style="<?= $ds_dd ?>; color: #b91c1c; font-size: .82rem;"><?= $e((string) $notificacion['ultimo_error']) ?></dd>
                    <?php endif ?>

                    <?php if (!empty($notificacion['dedupe_key'])): ?>
                    <dt style="<?= $ds_dt ?>">Dedupe key</dt>
                    <dd style="<?= $ds_dd ?>; border-bottom: 0;"><span class="ds-cell-mono"><?= $e((string) $notificacion['dedupe_key']) ?></span></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <?php if (!empty($notificacion['cuerpo_texto']) || !empty($notificacion['cuerpo_html'])): ?>
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-file-text"></i></span>
                <div>
                    <div class="ds-card-title">Contenido</div>
                    <div class="ds-card-subtitle">Cuerpo del mensaje a enviar</div>
                </div>
            </div>
            <div class="ds-card-body">
                <?php if (!empty($notificacion['cuerpo_texto'])): ?>
                <pre style="background: #0f172a; color: #e2e8f0; padding: 1rem 1.1rem; border-radius: 10px; font-size: .82rem; max-height: 360px; overflow: auto; white-space: pre-wrap; word-break: break-word; margin: 0;"><?= $e((string) $notificacion['cuerpo_texto']) ?></pre>
                <?php elseif (!empty($notificacion['cuerpo_html'])): ?>
                <div class="login-info" style="margin: 0;">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>Contenido HTML</strong>
                        Vista previa no disponible por razones de seguridad.
                    </div>
                </div>
                <?php endif ?>
            </div>
        </div>
        <?php endif ?>
    </div>

    <!-- Historial de intentos -->
    <div class="col-lg-4">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
                <div>
                    <div class="ds-card-title">Historial de envio</div>
                    <div class="ds-card-subtitle"><?= count($intentos) ?> intento<?= count($intentos) !== 1 ? 's' : '' ?> registrado<?= count($intentos) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if (empty($intentos)): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.5rem 1rem;">
                    <div class="ds-empty-icon" style="width: 56px; height: 56px; font-size: 1.6rem;"><i class="bi bi-clock-history"></i></div>
                    <p>Sin intentos de envio aun.</p>
                </div>
            </div>
            <?php else: ?>
            <div style="padding: .5rem; max-height: 480px; overflow-y: auto;">
                <?php foreach ($intentos as $intento):
                    $ok = $intento['resultado'] === 'OK';
                ?>
                <div style="padding: .75rem .85rem; border-radius: 10px; border-left: 3px solid <?= $ok ? '#059669' : '#dc2626' ?>; background: <?= $ok ? 'rgba(5,150,105,.04)' : 'rgba(220,38,38,.04)' ?>; margin-bottom: .5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .25rem;">
                        <span style="font-weight: 700; font-size: .85rem; color: <?= $ok ? '#047857' : '#b91c1c' ?>; display: inline-flex; align-items: center; gap: .35rem;">
                            <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i><?= $e((string) $intento['resultado']) ?>
                        </span>
                        <span class="ds-cell-mono" style="font-size: .72rem;"><?= $e(substr((string) $intento['intentado_at'], 0, 16)) ?></span>
                    </div>
                    <?php if (!empty($intento['codigo_respuesta'])): ?>
                    <div class="ds-cell-sub" style="margin-top: .15rem;">
                        Codigo <span class="ds-cell-mono" style="font-weight: 600;"><?= $e((string) $intento['codigo_respuesta']) ?></span>
                        <?php if ($intento['duracion_ms']): ?> &middot; <?= (int) $intento['duracion_ms'] ?>ms<?php endif ?>
                    </div>
                    <?php endif ?>
                    <?php if (!empty($intento['mensaje'])): ?>
                    <div class="ds-cell-sub" style="margin-top: .25rem; font-size: .75rem;"><?= $e(mb_substr((string) $intento['mensaje'], 0, 200)) ?></div>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
