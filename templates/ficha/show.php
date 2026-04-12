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
?>

<?php $view->section('title') ?>Ficha <?= $e($ficha['correlativo']) ?> — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item active"><?= $e($ficha['correlativo']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>

<!-- Incumplimientos (si los hay) -->
<?php if (!empty($incumplimientos)): ?>
<div class="alert alert-danger shadow-sm">
    <h6 class="alert-heading mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>No se puede completar la ficha</h6>
    <p class="mb-2">Se encontraron los siguientes incumplimientos de requisitos básicos:</p>
    <ul class="mb-0">
        <?php foreach ($incumplimientos as $inc): ?>
        <li><?= $e($inc) ?></li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-person-badge me-2"></i>
            <?= $e($ficha['apellidos']) ?> <?= $e($ficha['nombres']) ?>
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <code class="fs-6"><?= $e($ficha['correlativo']) ?></code>
            <span class="badge bg-info text-dark"><?= $e($tipoF->label()) ?></span>
            <span class="badge <?= $estado->badgeClass() ?>"><?= $e($estado->label()) ?></span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($user->tienePermiso('fichas.editar') && in_array($estado, [EstadoFicha::BORRADOR, EstadoFicha::EN_REVISION])): ?>
        <a href="<?= $view->url('/guardias/' . $fichaId . '/editar') ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <?php endif ?>
        <?php if ($user->tienePermiso('fichas.completar') && $estado === EstadoFicha::EN_REVISION): ?>
        <form method="post" action="<?= $view->url('/guardias/' . $fichaId . '/completar') ?>" class="d-inline">
            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('¿Marcar esta ficha como COMPLETA? Esta acción validará los requisitos básicos.')">
                <i class="bi bi-check-circle me-1"></i>Completar
            </button>
        </form>
        <?php endif ?>
        <a href="<?= $view->url('/guardias') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Datos Personales -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-person me-2"></i>Datos Personales</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:40%">Cédula</th><td><?= $e($ficha['cedula_display'] ?? '[cifrado]') ?></td></tr>
                    <tr><th class="text-muted">Género</th><td><?= $e($ficha['genero'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Fecha Nacimiento</th><td><?= $ficha['fecha_nacimiento'] ? date('d/m/Y', strtotime($ficha['fecha_nacimiento'])) : '—' ?></td></tr>
                    <tr><th class="text-muted">Lugar Nacimiento</th><td><?= $e(trim(($ficha['lugar_nacimiento_ciudad'] ?? '') . ', ' . ($ficha['lugar_nacimiento_provincia'] ?? ''), ', ')) ?></td></tr>
                    <tr><th class="text-muted">Tipo Sangre</th><td><?= $e($ficha['tipo_sangre'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Estatura</th><td><?= $ficha['estatura'] ? number_format((float)$ficha['estatura'], 2) . ' m' : '—' ?></td></tr>
                    <tr><th class="text-muted">Estado Civil</th><td><?= $e($ficha['estado_civil'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Celular</th><td><?= $e($ficha['celular'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Email</th><td><?= $e($ficha['email'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Domicilio -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-house me-2"></i>Domicilio</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:40%">Ciudad</th><td><?= $e($ficha['domicilio_ciudad'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Tipo Vivienda</th><td><?= $e($ficha['domicilio_tipo_vivienda'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Calle</th><td><?= $e($ficha['domicilio_calle'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Número</th><td><?= $e($ficha['domicilio_numero'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Barrio</th><td><?= $e($ficha['domicilio_barrio'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Referencia</th><td><?= $e($ficha['domicilio_referencia'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Teléfono</th><td><?= $e($ficha['domicilio_telefono'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Contacto Emergencia</th><td><?= $e($ficha['contacto_emergencia_nombre'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Tel. Emergencia</th><td><?= $e($ficha['contacto_emergencia_telefono'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Datos Financieros -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-bank me-2"></i>Datos Financieros</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:40%">Banco</th><td><?= $e($ficha['banco'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Tipo Cuenta</th><td><?= $e($ficha['tipo_cuenta'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Cuenta Bancaria</th><td><?= $e($ficha['cuenta_bancaria_display'] ?? '[cifrado]') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Instrucción -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Instrucción</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:40%">Primaria</th><td><?= $e($ficha['instruccion_primaria'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Secundaria</th><td><?= $e($ficha['instruccion_secundaria'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Superior</th><td><?= $e($ficha['instruccion_superior'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Último Reentrenamiento</th><td><?= $ficha['fecha_ultimo_reentrenamiento'] ? date('d/m/Y', strtotime($ficha['fecha_ultimo_reentrenamiento'])) : '—' ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Trabajos Anteriores -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-briefcase me-2"></i>Trabajos Anteriores</span>
                <span class="badge bg-secondary"><?= count($trabajos) ?></span>
            </div>
            <?php if ($trabajos === []): ?>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-briefcase fs-3 d-block mb-1"></i>Sin trabajos anteriores registrados.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Empresa</th><th>Cargo</th><th>Tiempo</th><th>Dirección</th><th>Teléfonos</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trabajos as $t): ?>
                        <tr>
                            <td><?= (int)$t['orden'] ?></td>
                            <td class="fw-semibold"><?= $e($t['empresa']) ?></td>
                            <td><?= $e($t['cargo'] ?? '—') ?></td>
                            <td><?= $e($t['tiempo'] ?? '—') ?></td>
                            <td><?= $e($t['direccion'] ?? '—') ?></td>
                            <td><?= $e($t['telefonos'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Hijos Menores -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people me-2"></i>Hijos Menores</span>
                <span class="badge bg-secondary"><?= count($hijos) ?></span>
            </div>
            <?php if ($hijos === []): ?>
            <div class="card-body text-center text-muted py-4">Sin hijos menores registrados.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Nombre</th><th>Edad</th><th>Justifica</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hijos as $h): ?>
                        <tr>
                            <td><?= $e($h['nombre']) ?></td>
                            <td><?= (int)$h['edad'] ?></td>
                            <td><?= $h['justifica_con_cedula'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
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
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-lines-fill me-2"></i>Referencias Personales</span>
                <span class="badge bg-secondary"><?= count($referencias) ?></span>
            </div>
            <?php if ($referencias === []): ?>
            <div class="card-body text-center text-muted py-4">Sin referencias registradas.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Parentesco</th><th>Nombre</th><th>Dirección</th><th>Teléfono</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referencias as $r): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark"><?= $e($r['parentesco']) ?></span></td>
                            <td><?= $e($r['nombre']) ?></td>
                            <td><?= $e($r['direccion'] ?? '—') ?></td>
                            <td><?= $e($r['telefono'] ?? '—') ?></td>
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
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-award me-2"></i>Cursos de Seguridad</span>
                <span class="badge bg-secondary"><?= count($cursos) ?></span>
            </div>
            <?php if ($cursos === []): ?>
            <div class="card-body text-center text-muted py-4">Sin cursos registrados.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Código</th><th>Curso</th><th>Fecha Obtención</th><th>Observación</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $c): ?>
                        <tr>
                            <td><code><?= $e($c['codigo']) ?></code></td>
                            <td><?= $e($c['curso_nombre']) ?></td>
                            <td><?= !empty($c['fecha_obtencion']) ? date('d/m/Y', strtotime($c['fecha_obtencion'])) : '—' ?></td>
                            <td><?= $e($c['observacion'] ?? '—') ?></td>
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
<div class="text-muted small mt-4 mb-2">
    <i class="bi bi-clock me-1"></i>
    Creada: <?= $ficha['created_at'] ? date('d/m/Y H:i', strtotime($ficha['created_at'])) : '—' ?>
    <?php if ($ficha['updated_at'] ?? null): ?>
    | Actualizada: <?= date('d/m/Y H:i', strtotime($ficha['updated_at'])) ?>
    <?php endif ?>
    <?php if ($ficha['fecha_revision'] ?? null): ?>
    | Revisión: <?= date('d/m/Y', strtotime($ficha['fecha_revision'])) ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
