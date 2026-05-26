<?php
/**
 * Detalle de ficha de guardia.
 *
 * Variables: $user, $csrfToken, $currentPath, $ficha, $trabajos, $hijos, $referencias, $cursos
 * Opcionales: $incumplimientos (array de strings)
 */

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estado = EstadoFicha::from($ficha['estado']);
$tipoF = TipoFicha::from($ficha['tipo_ficha']);
$fichaId = $ficha['id'];

$estadoTone = match ($ficha['estado']) {
    'BORRADOR'    => 'tone-muted',
    'EN_REVISION' => 'tone-warning',
    'COMPLETA'    => 'tone-success',
    'ANULADA'     => 'tone-danger',
    default       => 'tone-muted',
};

$initials = mb_strtoupper(mb_substr($ficha['nombres'] ?? 'X', 0, 1) . mb_substr($ficha['apellidos'] ?? 'X', 0, 1));
?>

<?php $view->section('title') ?>Ficha <?= $e($ficha['correlativo']) ?> - DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-personal-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .7rem;
    }
    .ds-personal-item {
        grid-column: span 6;
        padding: .7rem .8rem;
        border: 1px solid rgba(21, 48, 79, 0.09);
        border-radius: 12px;
        background: linear-gradient(180deg, #fcfdff, #f7f9fc);
        min-width: 0;
    }
    .ds-personal-item.span-3 { grid-column: span 3; }
    .ds-personal-item.span-4 { grid-column: span 4; }
    .ds-personal-item.span-8 { grid-column: span 8; }
    .ds-personal-item.span-12 { grid-column: span 12; }
    .ds-personal-label {
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ds-text-soft);
        margin-bottom: .2rem;
        line-height: 1.2;
    }
    .ds-personal-value {
        font-size: .93rem;
        font-weight: 600;
        color: var(--ds-text);
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ds-personal-value.break {
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }
    .ds-personal-item > div:last-child {
        font-size: .93rem;
        font-weight: 600;
        color: var(--ds-text);
        line-height: 1.3;
    }
    .ds-personal-item > .ds-cell-mono {
        font-size: .93rem;
        line-height: 1.3;
    }
    .ds-personal-item > .ds-cell-sub {
        font-size: .9rem;
        line-height: 1.35;
        color: var(--ds-text-soft);
        white-space: normal;
    }
    @media (max-width: 1199.98px) {
        .ds-personal-item.span-3 { grid-column: span 4; }
        .ds-personal-item.span-8 { grid-column: span 12; }
    }
    @media (max-width: 767.98px) {
        .ds-personal-item,
        .ds-personal-item.span-3,
        .ds-personal-item.span-4,
        .ds-personal-item.span-8,
        .ds-personal-item.span-12 {
            grid-column: span 12;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item active"><?= $e($ficha['correlativo']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>

<?php if (!empty($incumplimientos)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .85rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.4rem; line-height: 1;"><i class="bi bi-exclamation-triangle-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #b91c1c; margin-bottom: .35rem;">No se puede completar la ficha</div>
            <div class="ds-cell-sub" style="margin-bottom: .35rem;">Se encontraron incumplimientos de requisitos basicos:</div>
            <ul style="margin: 0; padding-left: 1.1rem; color: var(--ds-text-soft); font-size: .85rem; line-height: 1.6;">
                <?php foreach ($incumplimientos as $inc): ?>
                <li><?= $e($inc) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</div>
<?php endif ?>

<!-- Header con avatar -->
<div class="ds-card" style="margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap;">
        <div style="width: 80px; height: 80px; border-radius: 20px; background: linear-gradient(135deg, #2f80ed, #0ea5a4); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 700; box-shadow: 0 8px 24px rgba(47,128,237,.32); flex-shrink: 0;">
            <?= $e($initials) ?>
        </div>
        <div style="flex: 1; min-width: 240px;">
            <div style="font-size: 1.4rem; font-weight: 700; color: var(--ds-text);">
                <?= $e($ficha['apellidos']) ?> <?= $e($ficha['nombres']) ?>
            </div>
            <div style="display: flex; gap: .55rem; align-items: center; flex-wrap: wrap; margin-top: .35rem;">
                <span class="ds-cell-mono" style="font-weight: 700;"><?= $e($ficha['correlativo']) ?></span>
                <span class="ds-chip"><i class="bi bi-tag"></i><?= $e($tipoF->label()) ?></span>
                <span class="ds-badge <?= $estadoTone ?>"><?= $e($estado->label()) ?></span>
            </div>
        </div>
        <div style="display: flex; gap: .5rem; flex-wrap: wrap;">
            <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                <i class="bi bi-folder2-open"></i>Ver expediente
            </a>
            <?php if ($user->tienePermiso('guardias.editar') && in_array($estado, [EstadoFicha::BORRADOR, EstadoFicha::EN_REVISION])): ?>
            <a href="<?= $view->url('/guardias/' . $fichaId . '/editar') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                <i class="bi bi-pencil"></i>Editar
            </a>
            <?php endif ?>
            <?php if ($user->tienePermiso('guardias.editar') && $estado === EstadoFicha::BORRADOR): ?>
            <form method="post" action="<?= $view->url('/guardias/' . $fichaId . '/revision') ?>" class="ds-form-inline"
                  onsubmit="return confirm('Enviar esta ficha a revision? Luego podras completarla cuando cumpla los requisitos.');"
                  style="display: inline;">
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <button type="submit" class="ds-btn ds-btn-outline ds-btn-sm">
                    <i class="bi bi-send-check"></i>Enviar a revision
                </button>
            </form>
            <?php endif ?>
            <?php if ($user->tienePermiso('guardias.editar') && $estado === EstadoFicha::EN_REVISION): ?>
            <form method="post" action="<?= $view->url('/guardias/' . $fichaId . '/completar') ?>" class="ds-form-inline"
                  onsubmit="return confirm('Marcar esta ficha como COMPLETA? Se validaran los requisitos basicos.');"
                  style="display: inline;">
                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                <button type="submit" class="ds-btn ds-btn-success ds-btn-sm">
                    <i class="bi bi-check-circle"></i>Completar
                </button>
            </form>
            <?php endif ?>
            <div class="dropdown">
                <button class="ds-btn ds-btn-ghost ds-btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-pdf"></i>PDF
                </button>
                <ul class="dropdown-menu dropdown-menu-end ds-user-menu">
                    <li>
                        <a class="dropdown-item" href="<?= $view->url('/guardias/' . $fichaId . '/declaracion-lealtad.pdf') ?>" target="_blank" rel="noopener">
                            <i class="bi bi-file-earmark-text me-2 text-muted"></i>Declaracion de lealtad
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= $view->url('/guardias/' . $fichaId . '/formato-huellas.pdf') ?>" target="_blank" rel="noopener">
                            <i class="bi bi-fingerprint me-2 text-muted"></i>Formato de huellas
                        </a>
                    </li>
                </ul>
            </div>
            <a href="<?= $view->url('/guardias') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-arrow-left"></i>Volver
            </a>
        </div>
    </div>
</div>

<?php
$ds_dt = 'font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ds-text-soft); margin-bottom: .15rem;';
$ds_dd = 'font-size: .9rem; color: var(--ds-text); font-weight: 600;';
$pair = static fn(string $label, ?string $value, bool $mono = false) =>
    '<div style="margin-bottom: .85rem;"><div style="' . $ds_dt . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>'
    . '<div ' . ($mono ? 'class="ds-cell-mono"' : 'style="' . $ds_dd . '"') . '>'
    . htmlspecialchars($value !== null && $value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8') . '</div></div>';
?>

<div class="row g-4">
    <!-- Datos personales -->
    <div class="col-lg-6">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-person"></i></span>
                <div>
                    <div class="ds-card-title">Datos personales</div>
                    <div class="ds-card-subtitle">Identidad y contacto principal</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-personal-grid">
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Cedula</div>
                        <div class="ds-cell-mono" style="font-weight: 700;"><?= $e($ficha['cedula_display'] ?? '[cifrado]') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Genero</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['genero'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Fecha de nacimiento</div>
                        <div class="ds-cell-mono"><?= $ficha['fecha_nacimiento'] ? date('d/m/Y', strtotime($ficha['fecha_nacimiento'])) : '-' ?></div>
                    </div>
                    <div class="ds-personal-item span-8">
                        <div class="ds-personal-label">Lugar de nacimiento</div>
                        <div style="<?= $ds_dd ?>"><?= $e(trim(($ficha['lugar_nacimiento_ciudad'] ?? '') . ', ' . ($ficha['lugar_nacimiento_provincia'] ?? ''), ', ') ?: '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Tipo de sangre</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['tipo_sangre'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Estatura</div>
                        <div style="<?= $ds_dd ?>"><?= $ficha['estatura'] ? number_format((float) $ficha['estatura'], 2) . ' m' : '-' ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Estado civil</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['estado_civil'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Celular</div>
                        <div class="ds-cell-mono"><?= $e($ficha['celular'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-8">
                        <div class="ds-personal-label">Email</div>
                        <div class="ds-cell-mono"><?= $e($ficha['email'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Domicilio -->
    <div class="col-lg-6">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-house"></i></span>
                <div>
                    <div class="ds-card-title">Domicilio</div>
                    <div class="ds-card-subtitle">Direccion y contacto de emergencia</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-personal-grid">
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Ciudad</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['domicilio_ciudad'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Tipo de vivienda</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['domicilio_tipo_vivienda'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-8">
                        <div class="ds-personal-label">Calle</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['domicilio_calle'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Numero</div>
                        <div class="ds-cell-mono"><?= $e($ficha['domicilio_numero'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Barrio</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['domicilio_barrio'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Telefono fijo</div>
                        <div class="ds-cell-mono"><?= $e($ficha['domicilio_telefono'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-12">
                        <div class="ds-personal-label">Referencia</div>
                        <div class="ds-cell-sub"><?= $e($ficha['domicilio_referencia'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Contacto de emergencia</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['contacto_emergencia_nombre'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Tel. emergencia</div>
                        <div class="ds-cell-mono"><?= $e($ficha['contacto_emergencia_telefono'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Datos financieros -->
    <div class="col-lg-6">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-bank"></i></span>
                <div>
                    <div class="ds-card-title">Datos financieros</div>
                    <div class="ds-card-subtitle">Cuenta bancaria para nomina</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-personal-grid">
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Banco</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['banco'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Tipo de cuenta</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['tipo_cuenta'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-12">
                        <div class="ds-personal-label">Cuenta bancaria</div>
                        <div class="ds-cell-mono" style="font-weight: 700;"><?= $e($ficha['cuenta_bancaria_display'] ?? '[cifrado]') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Instruccion -->
    <div class="col-lg-6">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-mortarboard"></i></span>
                <div>
                    <div class="ds-card-title">Instruccion</div>
                    <div class="ds-card-subtitle">Educacion y reentrenamiento</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-personal-grid">
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Primaria</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['instruccion_primaria'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Secundaria</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['instruccion_secundaria'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Superior</div>
                        <div style="<?= $ds_dd ?>"><?= $e($ficha['instruccion_superior'] ?? '-') ?></div>
                    </div>
                    <div class="ds-personal-item span-4">
                        <div class="ds-personal-label">Ultimo reentrenamiento</div>
                        <div class="ds-cell-mono"><?= $ficha['fecha_ultimo_reentrenamiento'] ? date('d/m/Y', strtotime($ficha['fecha_ultimo_reentrenamiento'])) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trabajos -->
    <div class="col-12">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-briefcase"></i></span>
                <div>
                    <div class="ds-card-title">Trabajos anteriores</div>
                    <div class="ds-card-subtitle"><?= count($trabajos) ?> empleo<?= count($trabajos) !== 1 ? 's' : '' ?> registrado<?= count($trabajos) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($trabajos === []): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
                    <div class="ds-empty-icon"><i class="bi bi-briefcase"></i></div>
                    <p>Sin trabajos anteriores registrados.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Empresa</th>
                            <th>Cargo</th>
                            <th>Tiempo</th>
                            <th>Direccion</th>
                            <th>Telefonos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trabajos as $t): ?>
                        <tr>
                            <td><span class="ds-cell-sub"><?= (int) $t['orden'] ?></span></td>
                            <td><div class="ds-cell-name"><?= $e($t['empresa']) ?></div></td>
                            <td><?= $e($t['cargo'] ?? '-') ?></td>
                            <td><span class="ds-cell-sub"><?= $e($t['tiempo'] ?? '-') ?></span></td>
                            <td><span class="ds-cell-sub"><?= $e($t['direccion'] ?? '-') ?></span></td>
                            <td><span class="ds-cell-mono"><?= $e($t['telefonos'] ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Hijos -->
    <div class="col-lg-6">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-people"></i></span>
                <div>
                    <div class="ds-card-title">Hijos menores</div>
                    <div class="ds-card-subtitle"><?= count($hijos) ?> registrado<?= count($hijos) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($hijos === []): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.5rem 1rem;">
                    <div class="ds-empty-icon" style="width: 56px; height: 56px; font-size: 1.6rem;"><i class="bi bi-people"></i></div>
                    <p>Sin hijos menores registrados.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr><th>Nombre</th><th class="ds-cell-end">Edad</th><th class="ds-cell-end">Justifica</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hijos as $h): ?>
                        <tr>
                            <td><div class="ds-cell-name"><?= $e($h['nombre']) ?></div></td>
                            <td class="ds-cell-end"><?= (int) $h['edad'] ?></td>
                            <td class="ds-cell-end">
                                <?php if ($h['justifica_con_cedula']): ?>
                                    <i class="bi bi-check-circle-fill" style="color:#059669;"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle" style="color:#94a3b8;"></i>
                                <?php endif ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Referencias -->
    <div class="col-lg-6">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-person-lines-fill"></i></span>
                <div>
                    <div class="ds-card-title">Referencias personales</div>
                    <div class="ds-card-subtitle"><?= count($referencias) ?> referencia<?= count($referencias) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($referencias === []): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.5rem 1rem;">
                    <div class="ds-empty-icon" style="width: 56px; height: 56px; font-size: 1.6rem;"><i class="bi bi-person-lines-fill"></i></div>
                    <p>Sin referencias registradas.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr><th>Parentesco</th><th>Nombre</th><th>Direccion</th><th>Telefono</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referencias as $r): ?>
                        <tr>
                            <td><span class="ds-chip"><?= $e($r['parentesco']) ?></span></td>
                            <td><div class="ds-cell-name"><?= $e($r['nombre']) ?></div></td>
                            <td><span class="ds-cell-sub"><?= $e($r['direccion'] ?? '-') ?></span></td>
                            <td><span class="ds-cell-mono"><?= $e($r['telefono'] ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Cursos -->
    <div class="col-12">
        <div class="ds-card">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-award"></i></span>
                <div>
                    <div class="ds-card-title">Cursos de seguridad</div>
                    <div class="ds-card-subtitle"><?= count($cursos) ?> certificacion<?= count($cursos) !== 1 ? 'es' : '' ?> registrada<?= count($cursos) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php if ($cursos === []): ?>
            <div class="ds-card-body">
                <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
                    <div class="ds-empty-icon"><i class="bi bi-award"></i></div>
                    <p>Sin cursos de seguridad registrados.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr><th>Codigo</th><th>Curso</th><th>Fecha obtencion</th><th>Observacion</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $c): ?>
                        <tr>
                            <td><span class="ds-cell-mono"><?= $e($c['codigo']) ?></span></td>
                            <td><div class="ds-cell-name"><?= $e($c['curso_nombre']) ?></div></td>
                            <td><span class="ds-cell-mono"><?= !empty($c['fecha_obtencion']) ? date('d/m/Y', strtotime($c['fecha_obtencion'])) : '-' ?></span></td>
                            <td><span class="ds-cell-sub"><?= $e($c['observacion'] ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>
</div>

<!-- Metadata -->
<div class="ds-cell-sub" style="margin-top: 1.5rem; padding: .85rem 1rem; background: #f4f7fb; border-radius: 10px; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
    <span><i class="bi bi-clock"></i> Creada: <span class="ds-cell-mono"><?= $ficha['created_at'] ? date('d/m/Y H:i', strtotime($ficha['created_at'])) : '-' ?></span></span>
    <?php if (!empty($ficha['updated_at'])): ?>
    <span>&middot; Actualizada: <span class="ds-cell-mono"><?= date('d/m/Y H:i', strtotime($ficha['updated_at'])) ?></span></span>
    <?php endif ?>
    <?php if (!empty($ficha['fecha_revision'])): ?>
    <span>&middot; Revision: <span class="ds-cell-mono"><?= date('d/m/Y', strtotime($ficha['fecha_revision'])) ?></span></span>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
