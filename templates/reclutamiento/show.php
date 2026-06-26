<?php
/**
 * Detalle de un aspirante + acciones de aprobación/rechazo (Fase 4).
 *
 * Variables: $user, $csrfToken, $currentPath, $aspirante
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$a = $aspirante;
$id = (string) $a['id'];
$estado = (string) $a['estado'];
$verificado = (int) ($a['email_verificado'] ?? 0) === 1;

$estadoTone = [
    'nuevo' => 'tone-warning', 'en_revision' => 'tone-info', 'contactado' => 'tone-info',
    'aprobado' => 'tone-success', 'descartado' => 'tone-muted', 'contratado' => 'tone-success',
][$estado] ?? 'tone-muted';
$estadoLabel = [
    'nuevo' => 'Nuevo', 'en_revision' => 'En revisión', 'contactado' => 'Contactado',
    'aprobado' => 'Aprobado', 'descartado' => 'Descartado', 'contratado' => 'Contratado',
][$estado] ?? $estado;
$tipoLabel = ['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'][$a['tipo_documento']] ?? (string) $a['tipo_documento'];

$nombre = trim($a['nombres'] . ' ' . $a['apellidos']);
$estatura = (int) ($a['estatura_cm'] ?? 0);
$estaturaTxt = $estatura > 0 ? number_format($estatura / 100, 2) . ' m' : '-';
$puedeAprobar = $verificado && in_array($estado, ['nuevo', 'en_revision', 'contactado'], true);
$puedeRechazar = !in_array($estado, ['aprobado', 'descartado'], true);

$item = static function (string $label, string $value, string $span = '6') use ($e): void {
    echo '<div class="rc-item rc-span-' . $span . '"><div class="rc-label">' . $e($label) . '</div><div class="rc-value">' . $e($value) . '</div></div>';
};
?>

<?php $view->section('title') ?><?= $e($nombre) ?> - Reclutamiento<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .rc-grid { display: grid; grid-template-columns: repeat(12, minmax(0,1fr)); gap: .7rem; }
    .rc-item { grid-column: span 6; padding: .72rem .82rem; border: 1px solid rgba(21,48,79,.09); border-radius: 12px; background: linear-gradient(180deg,#fcfdff,#f7f9fc); }
    .rc-span-3 { grid-column: span 3; } .rc-span-4 { grid-column: span 4; } .rc-span-12 { grid-column: span 12; }
    .rc-label { font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ds-text-soft); margin-bottom: .2rem; }
    .rc-value { font-size: .93rem; font-weight: 600; color: var(--ds-text); }
    .rc-actions { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; }
    .rc-reject { flex: 1 1 280px; }
    .rc-reject textarea { width: 100%; border: 1px solid #d8dfe7; border-radius: 10px; padding: .55rem .7rem; font: inherit; font-size: .88rem; margin-bottom: .5rem; }
    @media (max-width: 991.98px) { .rc-item { grid-column: span 12; } }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/reclutamiento') ?>">Reclutamiento</a></li>
<li class="breadcrumb-item active"><?= $e($nombre) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-vcard"></i></span>
        <div>
            <h1><?= $e($nombre) ?></h1>
            <small>
                <span class="ds-badge <?= $estadoTone ?>"><?= $e($estadoLabel) ?></span>
                <?php if ($verificado): ?>
                    <span class="ds-badge tone-success"><i class="bi bi-check-circle"></i> Correo verificado</span>
                <?php else: ?>
                    <span class="ds-badge tone-warning"><i class="bi bi-clock"></i> Correo sin verificar</span>
                <?php endif ?>
            </small>
        </div>
    </div>
    <a href="<?= $view->url('/reclutamiento') ?>" class="ds-btn ds-btn-ghost"><i class="bi bi-arrow-left"></i>Volver</a>
</div>

<?php if ($estado === 'aprobado'): ?>
<div class="login-success" role="status" style="border-radius:12px;">
    <i class="bi bi-check-circle-fill"></i>
    <div><strong>Aspirante aprobado</strong> Se creó su cuenta de usuario activa<?= !empty($a['usuario_id']) ? ' (ID: ' . $e((string) $a['usuario_id']) . ')' : '' ?>.</div>
</div>
<?php elseif ($estado === 'descartado'): ?>
<div class="login-error" role="alert" style="border-radius:12px;">
    <i class="bi bi-x-octagon-fill"></i>
    <div><strong>Aspirante descartado</strong> <?= !empty($a['motivo_descarte']) ? $e((string) $a['motivo_descarte']) : 'Sin motivo registrado.' ?></div>
</div>
<?php endif ?>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-person-lines-fill"></i></span>
        <div>
            <div class="ds-card-title">Datos del aspirante</div>
            <div class="ds-card-subtitle">Información proporcionada en el registro</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="rc-grid">
            <?php
            $item('Tipo de documento', $tipoLabel, '3');
            $item('Número de documento', (string) $a['documento'], '3');
            $item('Apellidos', (string) $a['apellidos'], '3');
            $item('Nombres', (string) $a['nombres'], '3');
            $item('Celular', (string) $a['celular'], '4');
            $item('Correo', (string) $a['correo'], '4');
            $item('Estatura', $estaturaTxt, '4');
            $item('Fecha de nacimiento', (string) $a['fecha_nacimiento'], '4');
            $item('Registrado', (string) $a['created_at'], '4');
            $item('Verificación de correo', $verificado ? 'Verificado' . (!empty($a['email_verificado_at']) ? ' · ' . $a['email_verificado_at'] : '') : 'Pendiente', '4');
            ?>
        </div>
    </div>
</div>

<?php if ($puedeAprobar || $puedeRechazar): ?>
<div class="ds-card">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-clipboard-check"></i></span>
        <div>
            <div class="ds-card-title">Revisión</div>
            <div class="ds-card-subtitle">Aprobar crea la cuenta de usuario; rechazar descarta la postulación</div>
        </div>
    </div>
    <div class="ds-card-body">
        <?php if (!$verificado): ?>
        <div class="login-info" role="status" style="border-radius:12px;">
            <i class="bi bi-info-circle"></i>
            <div><strong>Aún no puedes aprobar</strong> El aspirante debe confirmar su correo antes de poder crear su cuenta. Puedes rechazar la postulación si corresponde.</div>
        </div>
        <?php endif ?>
        <div class="rc-actions">
            <?php if ($puedeAprobar): ?>
            <form method="POST" action="<?= $view->url('/reclutamiento/' . $id . '/aprobar') ?>"
                  onsubmit="return confirm('¿Aprobar a este aspirante y crear su cuenta de usuario activa?');">
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <button type="submit" class="ds-btn ds-btn-primary"><i class="bi bi-person-check"></i>Aprobar y crear usuario</button>
            </form>
            <?php endif ?>
            <?php if ($puedeRechazar): ?>
            <form method="POST" action="<?= $view->url('/reclutamiento/' . $id . '/rechazar') ?>" class="rc-reject"
                  onsubmit="return confirm('¿Rechazar esta postulación?');">
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <textarea name="motivo" rows="2" maxlength="255" placeholder="Motivo del rechazo (opcional)"></textarea>
                <button type="submit" class="ds-btn ds-btn-danger"><i class="bi bi-x-circle"></i>Rechazar postulación</button>
            </form>
            <?php endif ?>
        </div>
    </div>
</div>
<?php endif ?>
<?php $view->endSection() ?>
