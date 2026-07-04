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
    $value = trim($value);
    echo '<div class="rc-item rc-span-' . $span . '"><div class="rc-label">' . $e($label) . '</div><div class="rc-value">' . ($value !== '' ? $e($value) : '<span style="color:var(--ds-text-soft);font-weight:500">—</span>') . '</div></div>';
};

$ficha = isset($ficha) && is_array($ficha) ? $ficha : null;
$fv = static fn(string $k): string => $ficha !== null ? trim((string) ($ficha[$k] ?? '')) : '';
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
    .rc-subhead { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--ds-text-soft); margin: 1.1rem 0 .55rem; padding-bottom: .3rem; border-bottom: 1px solid rgba(21,48,79,.09); display: flex; align-items: center; gap: .4rem; }
    .rc-subhead:first-child { margin-top: 0; }
    .rc-media { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: .3rem; }
    .rc-media figure { margin: 0; }
    .rc-media img { display: block; border: 1px solid rgba(21,48,79,.12); border-radius: 12px; background: #fff; }
    .rc-foto img { width: 130px; height: 160px; object-fit: cover; }
    .rc-croquis img { max-width: 100%; width: 420px; height: auto; }
    .rc-media figcaption { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--ds-text-soft); margin-top: .3rem; }
    .rc-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .rc-table th, .rc-table td { text-align: left; padding: .45rem .6rem; border-bottom: 1px solid rgba(21,48,79,.08); }
    .rc-table th { font-size: .66rem; text-transform: uppercase; letter-spacing: .06em; color: var(--ds-text-soft); }
    .rc-table-wrap { overflow-x: auto; }
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

<div class="ds-card">
    <div class="ds-card-header tone-info">
        <span class="ds-card-mark"><i class="bi bi-clipboard2-data"></i></span>
        <div>
            <div class="ds-card-title">Ficha del aspirante</div>
            <div class="ds-card-subtitle">Datos que el aspirante completó en su ingreso</div>
        </div>
    </div>
    <div class="ds-card-body">
        <?php if ($ficha === null): ?>
            <div class="login-info" role="status" style="border-radius:12px; margin:0;">
                <i class="bi bi-info-circle"></i>
                <div><strong>Ficha pendiente</strong> El aspirante aún no ha completado su ficha de ingreso.</div>
            </div>
        <?php else:
            $generoLabel = ['MASCULINO' => 'Masculino', 'FEMENINO' => 'Femenino', 'OTRO' => 'Otro'][$fv('genero')] ?? $fv('genero');
            $estadoFichaLabel = ['BORRADOR' => 'Borrador', 'EN_REVISION' => 'En revisión', 'COMPLETA' => 'Completa', 'ELIMINADA' => 'Eliminada'][$fv('estado')] ?? $fv('estado');
            $tipoFichaLabel = ['INGRESO' => 'Ingreso', 'REINGRESO' => 'Reingreso', 'ACTUALIZACION' => 'Actualización'][$fv('tipo_ficha')] ?? $fv('tipo_ficha');
            $estaturaFicha = $fv('estatura');
            $estaturaFichaTxt = $estaturaFicha !== '' ? number_format((float) $estaturaFicha, 2) . ' m' : '';
        ?>
            <div style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-bottom:.9rem;">
                <span class="ds-badge tone-info"><i class="bi bi-hash"></i> <?= $e($fv('correlativo')) ?></span>
                <span class="ds-badge tone-muted"><?= $e($tipoFichaLabel) ?></span>
                <span class="ds-badge tone-muted"><?= $e($estadoFichaLabel) ?></span>
            </div>

            <?php if ($ficha['foto_data_uri'] !== '' || $ficha['croquis_data_uri'] !== ''): ?>
            <div class="rc-media">
                <?php if ($ficha['foto_data_uri'] !== ''): ?>
                <figure class="rc-foto"><img src="<?= $e($ficha['foto_data_uri']) ?>" alt="Foto del aspirante"><figcaption>Foto</figcaption></figure>
                <?php endif ?>
                <?php if ($ficha['croquis_data_uri'] !== ''): ?>
                <figure class="rc-croquis"><img src="<?= $e($ficha['croquis_data_uri']) ?>" alt="Croquis del domicilio"><figcaption>Croquis del domicilio</figcaption></figure>
                <?php endif ?>
            </div>
            <?php endif ?>

            <div class="rc-subhead"><i class="bi bi-person-vcard"></i>Datos personales</div>
            <div class="rc-grid">
                <?php
                $item('Cédula', $ficha['cedula_display'], '3');
                $item('Apellidos', $fv('apellidos'), '3');
                $item('Nombres', $fv('nombres'), '3');
                $item('Género', $generoLabel, '3');
                $item('Fecha de nacimiento', $fv('fecha_nacimiento'), '3');
                $item('Estado civil', $fv('estado_civil'), '3');
                $item('Tipo de sangre', $fv('tipo_sangre'), '3');
                $item('Estatura', $estaturaFichaTxt, '3');
                $item('Provincia de nacimiento', $fv('lugar_nacimiento_provincia'), '3');
                $item('Ciudad de nacimiento', $fv('lugar_nacimiento_ciudad'), '3');
                ?>
            </div>

            <div class="rc-subhead"><i class="bi bi-telephone"></i>Contacto</div>
            <div class="rc-grid">
                <?php
                $item('Celular', $fv('celular'), '4');
                $item('Email', $fv('email'), '4');
                $item('Contacto de emergencia', trim($fv('contacto_emergencia_nombre') . ' ' . $fv('contacto_emergencia_telefono')), '4');
                ?>
            </div>

            <div class="rc-subhead"><i class="bi bi-house"></i>Domicilio</div>
            <div class="rc-grid">
                <?php
                $item('Ciudad', $fv('domicilio_ciudad'), '4');
                $item('Barrio', $fv('domicilio_barrio'), '4');
                $item('Teléfono', $fv('domicilio_telefono'), '4');
                $item('Dirección', trim($fv('domicilio_calle') . ' ' . $fv('domicilio_numero')), '6');
                $item('Referencia', $fv('domicilio_referencia'), '6');
                ?>
            </div>

            <div class="rc-subhead"><i class="bi bi-mortarboard"></i>Instrucción y datos bancarios</div>
            <div class="rc-grid">
                <?php
                $item('Primaria', $fv('instruccion_primaria'), '4');
                $item('Secundaria', $fv('instruccion_secundaria'), '4');
                $item('Superior', $fv('instruccion_superior'), '4');
                $item('Banco', $fv('banco'), '4');
                $item('Tipo de cuenta', $fv('tipo_cuenta'), '4');
                $item('N.º de cuenta', $ficha['cuenta_bancaria_display'], '4');
                ?>
            </div>

            <?php if (!empty($ficha['trabajos'])): ?>
            <div class="rc-subhead"><i class="bi bi-briefcase"></i>Experiencia laboral</div>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead><tr><th>Empresa</th><th>Cargo</th><th>Tiempo</th><th>Teléfonos</th></tr></thead>
                    <tbody>
                    <?php foreach ($ficha['trabajos'] as $t): ?>
                        <tr>
                            <td><?= $e((string) ($t['empresa'] ?? '')) ?></td>
                            <td><?= $e((string) ($t['cargo'] ?? '')) ?></td>
                            <td><?= $e((string) ($t['tiempo'] ?? '')) ?></td>
                            <td><?= $e((string) ($t['telefonos'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>

            <?php if (!empty($ficha['referencias'])): ?>
            <div class="rc-subhead"><i class="bi bi-people"></i>Referencias</div>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead><tr><th>Parentesco</th><th>Nombre</th><th>Dirección</th><th>Teléfono</th></tr></thead>
                    <tbody>
                    <?php foreach ($ficha['referencias'] as $r): ?>
                        <tr>
                            <td><?= $e((string) ($r['parentesco'] ?? '')) ?></td>
                            <td><?= $e((string) ($r['nombre'] ?? '')) ?></td>
                            <td><?= $e((string) ($r['direccion'] ?? '')) ?></td>
                            <td><?= $e((string) ($r['telefono'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>

            <?php if (!empty($ficha['hijos'])): ?>
            <div class="rc-subhead"><i class="bi bi-people-fill"></i>Hijos menores</div>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead><tr><th>Nombre</th><th>Edad</th></tr></thead>
                    <tbody>
                    <?php foreach ($ficha['hijos'] as $h): ?>
                        <tr>
                            <td><?= $e((string) ($h['nombre'] ?? '')) ?></td>
                            <td><?= $e((string) ($h['edad'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>

            <?php if (!empty($ficha['cursos'])): ?>
            <div class="rc-subhead"><i class="bi bi-award"></i>Cursos</div>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead><tr><th>Curso</th><th>Fecha</th><th>Observación</th></tr></thead>
                    <tbody>
                    <?php foreach ($ficha['cursos'] as $c): ?>
                        <tr>
                            <td><?= $e((string) ($c['curso_nombre'] ?? $c['codigo'] ?? '')) ?></td>
                            <td><?= $e((string) ($c['fecha_obtencion'] ?? '')) ?></td>
                            <td><?= $e((string) ($c['observacion'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        <?php endif ?>
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
