<?php
/**
 * Detalle de una notificación + historial de intentos.
 *
 * Variables: $user, $csrfToken, $currentPath, $notificacion, $intentos
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoBadge = static fn(string $estado) => match ($estado) {
    'PENDIENTE' => 'bg-secondary',
    'ENVIADA' => 'bg-success',
    'FALLIDA' => 'bg-danger',
    'CANCELADA' => 'bg-warning text-dark',
    default => 'bg-dark',
};

$canalIcon = static fn(string $canal) => match ($canal) {
    'EMAIL' => 'bi-envelope',
    'WHATSAPP' => 'bi-whatsapp',
    'SMS' => 'bi-chat-dots',
    'PUSH' => 'bi-bell',
    default => 'bi-send',
};
?>

<?php $view->section('title') ?>Notificación — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/notificaciones') ?>">Notificaciones</a></li>
<li class="breadcrumb-item active">Detalle</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bell me-2"></i>Detalle de notificación</h4>
    <div class="d-flex gap-2">
        <?php if ($notificacion['estado'] === 'FALLIDA'): ?>
        <form method="post" action="<?= $view->url('/notificaciones/' . $e((string)$notificacion['id']) . '/retry') ?>">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="btn btn-warning btn-sm"
                    onclick="return confirm('¿Reintentar el envío de esta notificación?')">
                <i class="bi bi-arrow-clockwise me-1"></i>Reintentar
            </button>
        </form>
        <?php endif ?>
        <a href="<?= $view->url('/notificaciones') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Info principal -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header"><strong>Información</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">ID</dt>
                    <dd class="col-sm-9"><code class="small"><?= $e((string)$notificacion['id']) ?></code></dd>

                    <dt class="col-sm-3">Evento</dt>
                    <dd class="col-sm-9"><code><?= $e((string)$notificacion['evento']) ?></code></dd>

                    <dt class="col-sm-3">Canal</dt>
                    <dd class="col-sm-9">
                        <i class="bi <?= $canalIcon((string)$notificacion['canal']) ?> me-1"></i>
                        <?= $e((string)$notificacion['canal']) ?>
                    </dd>

                    <dt class="col-sm-3">Prioridad</dt>
                    <dd class="col-sm-9"><?= $e((string)$notificacion['prioridad']) ?></dd>

                    <dt class="col-sm-3">Estado</dt>
                    <dd class="col-sm-9">
                        <span class="badge <?= $estadoBadge((string)$notificacion['estado']) ?>"><?= $e((string)$notificacion['estado']) ?></span>
                    </dd>

                    <dt class="col-sm-3">Destinatario</dt>
                    <dd class="col-sm-9">
                        <?php if (!empty($notificacion['destinatario_nombre'])): ?>
                            <?= $e((string)$notificacion['destinatario_nombre']) ?><br>
                        <?php endif ?>
                        <?php if (!empty($notificacion['destinatario_email'])): ?>
                            <i class="bi bi-envelope me-1"></i><?= $e((string)$notificacion['destinatario_email']) ?><br>
                        <?php endif ?>
                        <?php if (!empty($notificacion['destinatario_telefono'])): ?>
                            <i class="bi bi-telephone me-1"></i><?= $e((string)$notificacion['destinatario_telefono']) ?>
                        <?php endif ?>
                    </dd>

                    <dt class="col-sm-3">Asunto</dt>
                    <dd class="col-sm-9"><?= $e((string)$notificacion['asunto']) ?></dd>

                    <dt class="col-sm-3">Creada</dt>
                    <dd class="col-sm-9"><?= $e((string)$notificacion['created_at']) ?></dd>

                    <dt class="col-sm-3">Programada</dt>
                    <dd class="col-sm-9"><?= $e((string)($notificacion['scheduled_at'] ?? '—')) ?></dd>

                    <dt class="col-sm-3">Enviada</dt>
                    <dd class="col-sm-9"><?= $e((string)($notificacion['enviada_at'] ?? '—')) ?></dd>

                    <dt class="col-sm-3">Intentos</dt>
                    <dd class="col-sm-9"><?= (int)$notificacion['intentos'] ?> / <?= (int)$notificacion['max_intentos'] ?></dd>

                    <?php if (!empty($notificacion['ultimo_error'])): ?>
                    <dt class="col-sm-3">Último error</dt>
                    <dd class="col-sm-9"><span class="text-danger small"><?= $e((string)$notificacion['ultimo_error']) ?></span></dd>
                    <?php endif ?>

                    <?php if (!empty($notificacion['dedupe_key'])): ?>
                    <dt class="col-sm-3">Dedupe key</dt>
                    <dd class="col-sm-9"><code class="small"><?= $e((string)$notificacion['dedupe_key']) ?></code></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <!-- Cuerpo -->
        <?php if (!empty($notificacion['cuerpo_texto']) || !empty($notificacion['cuerpo_html'])): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header"><strong>Contenido</strong></div>
            <div class="card-body">
                <?php if (!empty($notificacion['cuerpo_texto'])): ?>
                <pre class="bg-light p-3 rounded small mb-0" style="white-space:pre-wrap"><?= $e((string)$notificacion['cuerpo_texto']) ?></pre>
                <?php elseif (!empty($notificacion['cuerpo_html'])): ?>
                <div class="border rounded p-3 bg-light small">
                    <em class="text-muted">Contenido HTML — vista previa no disponible por seguridad</em>
                </div>
                <?php endif ?>
            </div>
        </div>
        <?php endif ?>
    </div>

    <!-- Historial de intentos -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Historial de envío</strong></div>
            <?php if (empty($intentos)): ?>
                <div class="card-body text-center text-muted py-4">
                    <i class="bi bi-clock-history fs-3 d-block mb-2"></i>
                    Sin intentos de envío registrados.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($intentos as $intento): ?>
                    <div class="list-group-item small">
                        <div class="d-flex justify-content-between">
                            <span>
                                <?php if ($intento['resultado'] === 'OK'): ?>
                                    <i class="bi bi-check-circle text-success me-1"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-danger me-1"></i>
                                <?php endif ?>
                                <?= $e((string)$intento['resultado']) ?>
                            </span>
                            <span class="text-muted"><?= $e(substr((string)$intento['intentado_at'], 0, 16)) ?></span>
                        </div>
                        <?php if (!empty($intento['codigo_respuesta'])): ?>
                        <div class="text-muted mt-1">
                            Código: <code><?= $e((string)$intento['codigo_respuesta']) ?></code>
                            <?php if ($intento['duracion_ms']): ?>
                                — <?= (int)$intento['duracion_ms'] ?>ms
                            <?php endif ?>
                        </div>
                        <?php endif ?>
                        <?php if (!empty($intento['mensaje'])): ?>
                        <div class="text-muted mt-1 small"><?= $e(mb_substr((string)$intento['mensaje'], 0, 200)) ?></div>
                        <?php endif ?>
                    </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
