<?php
/**
 * Formulario de creación de ficha de guardia.
 *
 * Variables: $user, $csrfToken, $currentPath, $cursos, $input (array para repoblar)
 * Opcionales: $error (string)
 */

use DigiSecure\Ficha\Domain\Enum\EstadoCivil;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Domain\Enum\Parentesco;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoSangre;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$old = static fn(string $key, string $default = '') => $e((string)($input[$key] ?? $default));
$prefillTrabajos = array_values(is_array($input['trabajos_anteriores'] ?? null) ? $input['trabajos_anteriores'] : []);
$prefillHijos = array_values(is_array($input['hijos_menores'] ?? null) ? $input['hijos_menores'] : []);
$prefillReferencias = array_values(is_array($input['referencias'] ?? null) ? $input['referencias'] : []);
$cursosAsignados = [];
foreach ((array)($input['cursos'] ?? []) as $cursoInput) {
    $cursoId = (string)($cursoInput['curso_id'] ?? '');
    if ($cursoId === '') {
        continue;
    }
    $cursosAsignados[$cursoId] = $cursoInput;
}
?>

<?php $view->section('title') ?>Nueva Ficha — DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-form-kicker {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .85rem;
        padding: .38rem .72rem;
        border-radius: 999px;
        background: rgba(47, 128, 237, .12);
        color: #1d4d8f;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .ds-step-nav {
        position: sticky;
        top: 1rem;
    }
    .ds-step-nav-card,
    .ds-summary-card {
        border: 0;
        border-radius: 24px;
        box-shadow: 0 16px 30px rgba(18, 45, 77, .08);
    }
    .ds-step-nav-card .card-body,
    .ds-summary-card .card-body {
        padding: 1.1rem;
    }
    .ds-step-list {
        display: grid;
        gap: .75rem;
    }
    .ds-step-link {
        display: flex;
        align-items: center;
        gap: .75rem;
        width: 100%;
        padding: .85rem .9rem;
        border: 1px solid rgba(21, 48, 79, .08);
        border-radius: 18px;
        background: #fff;
        color: #16324f;
        text-align: left;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .ds-step-link.active {
        border-color: rgba(47, 128, 237, .28);
        box-shadow: 0 12px 24px rgba(18, 45, 77, .08);
        transform: translateY(-1px);
    }
    .ds-step-index {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: rgba(47, 128, 237, .12);
        color: #2f80ed;
        font-weight: 700;
        flex: 0 0 auto;
    }
    .ds-step-copy {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .ds-step-copy strong {
        font-size: .92rem;
        line-height: 1.2;
    }
    .ds-step-copy span {
        color: #607489;
        font-size: .76rem;
    }
    .ds-step-status {
        margin-left: auto;
        font-size: .75rem;
        font-weight: 700;
    }
    .ds-step-panel {
        display: none;
    }
    .ds-step-panel.active {
        display: block;
    }
    .ds-form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    .ds-progress-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        margin-bottom: .5rem;
    }
    .ds-summary-card .progress {
        height: 12px;
        border-radius: 999px;
        background: rgba(21, 48, 79, .08);
    }
    .ds-summary-list {
        display: grid;
        gap: .7rem;
        margin-top: 1rem;
    }
    .ds-summary-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem .9rem;
        border-radius: 16px;
        background: rgba(21, 48, 79, .04);
        border: 1px solid transparent;
    }
    .ds-summary-item.active {
        border-color: rgba(47, 128, 237, .25);
        background: rgba(47, 128, 237, .08);
    }
    .ds-summary-note {
        margin-top: .85rem;
        padding: .8rem .9rem;
        border-radius: 16px;
        background: rgba(21, 48, 79, .04);
        color: #607489;
        font-size: .84rem;
    }
    .ds-repeat-card {
        padding: 1rem;
        border-radius: 16px;
        background: rgba(21, 48, 79, .03);
        border: 1px solid rgba(21, 48, 79, .08);
    }
    .ds-repeat-card + .ds-repeat-card {
        margin-top: .85rem;
    }
    @media (max-width: 1199.98px) {
        .ds-step-nav {
            position: static;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item active">Nueva Ficha</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <span class="ds-form-kicker"><i class="bi bi-ui-checks-grid"></i>Formulario guiado</span>
        <h4 class="mb-1"><i class="bi bi-plus-circle me-2"></i>Nueva Ficha de Guardia</h4>
        <p class="text-muted mb-0">Organizamos el formulario por pasos para reducir la carga visual y mantener toda la funcionalidad.</p>
    </div>
    <a href="<?= $view->url('/guardias') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>

<div class="row g-4">
    <div class="col-xl-8">
<form method="post" action="<?= $view->url('/guardias/crear') ?>" id="fichaForm" novalidate>
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="card ds-step-nav-card shadow-sm mb-4">
        <div class="card-body">
            <div class="ds-step-list">
                <button type="button" class="ds-step-link active" data-step-target="base">
                    <span class="ds-step-index">1</span>
                    <span class="ds-step-copy">
                        <strong>Base</strong>
                        <span>Tipo y datos personales</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="base">0/6</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="contacto">
                    <span class="ds-step-index">2</span>
                    <span class="ds-step-copy">
                        <strong>Contacto</strong>
                        <span>Domicilio y contacto</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="contacto">0/0</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="historial">
                    <span class="ds-step-index">3</span>
                    <span class="ds-step-copy">
                        <strong>Historial</strong>
                        <span>Finanzas, instruccion y referencias</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="historial">0/0</span>
                </button>
                <button type="button" class="ds-step-link" data-step-target="cursos">
                    <span class="ds-step-index">4</span>
                    <span class="ds-step-copy">
                        <strong>Cursos</strong>
                        <span>Seleccion y confirmacion</span>
                    </span>
                    <span class="badge text-bg-light ds-step-status" data-step-status="cursos">0/0</span>
                </button>
            </div>
        </div>
    </div>

    <div class="ds-step-panel active" data-step-panel="base">

    <!-- 1. Tipo de Ficha -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-tag me-2"></i>Tipo de Ficha</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select name="tipo_ficha" class="form-select" required>
                        <?php foreach (TipoFicha::cases() as $tipo): ?>
                        <option value="<?= $tipo->value ?>" <?= ($input['tipo_ficha'] ?? 'INGRESO') === $tipo->value ? 'selected' : '' ?>>
                            <?= $e($tipo->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Datos Personales -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-person me-2"></i>Datos Personales</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" name="apellidos" class="form-control" required maxlength="120" value="<?= $old('apellidos') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nombres <span class="text-danger">*</span></label>
                    <input type="text" name="nombres" class="form-control" required maxlength="120" value="<?= $old('nombres') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cédula <span class="text-danger">*</span></label>
                    <input type="text" name="cedula" class="form-control" required maxlength="10" minlength="10"
                           pattern="\d{10}" placeholder="10 dígitos" value="<?= $old('cedula') ?>">
                    <div class="form-text">Cédula ecuatoriana (10 dígitos)</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Género <span class="text-danger">*</span></label>
                    <select name="genero" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach (Genero::cases() as $g): ?>
                        <option value="<?= $g->value ?>" <?= ($input['genero'] ?? '') === $g->value ? 'selected' : '' ?>>
                            <?= $e($g->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha Nacimiento <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_nacimiento" class="form-control" required value="<?= $old('fecha_nacimiento') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ciudad Nacimiento</label>
                    <input type="text" name="lugar_nacimiento_ciudad" class="form-control" maxlength="100" value="<?= $old('lugar_nacimiento_ciudad') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Provincia Nacimiento</label>
                    <input type="text" name="lugar_nacimiento_provincia" class="form-control" maxlength="100" value="<?= $old('lugar_nacimiento_provincia') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo Sangre</label>
                    <select name="tipo_sangre" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach (TipoSangre::cases() as $ts): ?>
                        <option value="<?= $e($ts->value) ?>" <?= ($input['tipo_sangre'] ?? '') === $ts->value ? 'selected' : '' ?>>
                            <?= $e($ts->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estatura (m)</label>
                    <input type="number" name="estatura" class="form-control" step="0.01" min="1.00" max="2.50"
                           placeholder="1.70" value="<?= $old('estatura') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado Civil</label>
                    <select name="estado_civil" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach (EstadoCivil::cases() as $ec): ?>
                        <option value="<?= $ec->value ?>" <?= ($input['estado_civil'] ?? '') === $ec->value ? 'selected' : '' ?>>
                            <?= $e($ec->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Celular</label>
                    <input type="tel" name="celular" class="form-control" maxlength="30" value="<?= $old('celular') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="190" value="<?= $old('email') ?>">
                </div>
            </div>
        </div>
    </div>

    </div>

    <div class="ds-step-panel" data-step-panel="contacto">
    <!-- 3. Domicilio -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-house me-2"></i>Domicilio</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Ciudad</label>
                    <input type="text" name="domicilio_ciudad" class="form-control" maxlength="100" value="<?= $old('domicilio_ciudad') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo Vivienda</label>
                    <select name="domicilio_tipo_vivienda" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach (['PROPIA' => 'Propia', 'ARRENDADA' => 'Arrendada', 'FAMILIAR' => 'Familiar'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($input['domicilio_tipo_vivienda'] ?? '') === $v ? 'selected' : '' ?>>
                            <?= $l ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Teléfono Domicilio</label>
                    <input type="tel" name="domicilio_telefono" class="form-control" maxlength="30" value="<?= $old('domicilio_telefono') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Calle</label>
                    <input type="text" name="domicilio_calle" class="form-control" maxlength="255" value="<?= $old('domicilio_calle') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Número</label>
                    <input type="text" name="domicilio_numero" class="form-control" maxlength="30" value="<?= $old('domicilio_numero') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Barrio</label>
                    <input type="text" name="domicilio_barrio" class="form-control" maxlength="150" value="<?= $old('domicilio_barrio') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Referencia</label>
                    <input type="text" name="domicilio_referencia" class="form-control" maxlength="255" value="<?= $old('domicilio_referencia') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contacto Emergencia (Nombre)</label>
                    <input type="text" name="contacto_emergencia_nombre" class="form-control" maxlength="200" value="<?= $old('contacto_emergencia_nombre') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contacto Emergencia (Teléfono)</label>
                    <input type="tel" name="contacto_emergencia_telefono" class="form-control" maxlength="30" value="<?= $old('contacto_emergencia_telefono') ?>">
                </div>
            </div>
        </div>
    </div>

    </div>

    <div class="ds-step-panel" data-step-panel="historial">
    <!-- 4. Datos Financieros -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-bank me-2"></i>Datos Financieros</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Banco</label>
                    <input type="text" name="banco" class="form-control" maxlength="120" value="<?= $old('banco') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo Cuenta</label>
                    <select name="tipo_cuenta" class="form-select">
                        <option value="">Seleccione...</option>
                        <option value="AHORROS" <?= ($input['tipo_cuenta'] ?? '') === 'AHORROS' ? 'selected' : '' ?>>Ahorros</option>
                        <option value="CORRIENTE" <?= ($input['tipo_cuenta'] ?? '') === 'CORRIENTE' ? 'selected' : '' ?>>Corriente</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cuenta Bancaria</label>
                    <input type="text" name="cuenta_bancaria" class="form-control" maxlength="30" value="<?= $old('cuenta_bancaria') ?>">
                    <div class="form-text">Se almacena cifrada (AES-256-GCM)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Instrucción -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Instrucción</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Primaria</label>
                    <input type="text" name="instruccion_primaria" class="form-control" maxlength="200"
                           placeholder="Nombre de la institución" value="<?= $old('instruccion_primaria') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Secundaria (Bachillerato)</label>
                    <input type="text" name="instruccion_secundaria" class="form-control" maxlength="200"
                           placeholder="Requisito: Bachiller" value="<?= $old('instruccion_secundaria') ?>">
                    <div class="form-text">Requisito básico para completar ficha</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Superior</label>
                    <input type="text" name="instruccion_superior" class="form-control" maxlength="200" value="<?= $old('instruccion_superior') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Último Reentrenamiento</label>
                    <input type="date" name="fecha_ultimo_reentrenamiento" class="form-control" value="<?= $old('fecha_ultimo_reentrenamiento') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Trabajos Anteriores (dinámico) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-briefcase me-2"></i>Trabajos Anteriores</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTrabajo()">
                <i class="bi bi-plus-lg me-1"></i>Agregar
            </button>
        </div>
        <div class="card-body" id="trabajos-container">
            <p class="text-muted small mb-0" id="trabajos-empty">
                <i class="bi bi-info-circle me-1"></i>Requisito básico: al menos 1 trabajo anterior.
                Haga clic en "Agregar" para añadir.
            </p>
        </div>
    </div>

    <!-- 7. Hijos Menores (dinámico) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people me-2"></i>Hijos Menores</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addHijo()">
                <i class="bi bi-plus-lg me-1"></i>Agregar
            </button>
        </div>
        <div class="card-body" id="hijos-container">
            <p class="text-muted small mb-0" id="hijos-empty">Sin hijos menores registrados.</p>
        </div>
    </div>

    <!-- 8. Referencias Personales (dinámico) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-lines-fill me-2"></i>Referencias Personales</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addReferencia()">
                <i class="bi bi-plus-lg me-1"></i>Agregar
            </button>
        </div>
        <div class="card-body" id="referencias-container">
            <p class="text-muted small mb-0" id="referencias-empty">Sin referencias registradas.</p>
        </div>
    </div>

    </div>

    <div class="ds-step-panel" data-step-panel="cursos">
    <!-- 9. Cursos de Seguridad -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-award me-2"></i>Cursos de Seguridad</div>
        <div class="card-body">
            <?php if ($cursos === []): ?>
            <p class="text-muted small">No hay cursos en el catálogo.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th style="width:30px"></th><th>Código</th><th>Curso</th><th>Fecha Obtención</th><th>Observación</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $i => $curso):
                            $asignado = $cursosAsignados[$curso['id']] ?? null;
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input curso-check"
                                       data-index="<?= $i ?>" id="curso_<?= $i ?>" <?= $asignado ? 'checked' : '' ?>>
                            </td>
                            <td><label for="curso_<?= $i ?>"><code><?= $e($curso['codigo']) ?></code></label></td>
                            <td><label for="curso_<?= $i ?>"><?= $e($curso['nombre']) ?></label></td>
                            <td>
                                <input type="date" class="form-control form-control-sm curso-fecha" data-index="<?= $i ?>"
                                       value="<?= $e((string)($asignado['fecha_obtencion'] ?? '')) ?>" <?= $asignado ? '' : 'disabled' ?>>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm curso-obs" data-index="<?= $i ?>"
                                       maxlength="200" <?= $asignado ? '' : 'disabled' ?> placeholder="Opcional"
                                       value="<?= $e((string)($asignado['observacion'] ?? '')) ?>">
                            </td>
                            <input type="hidden" class="curso-id" data-index="<?= $i ?>" value="<?= $e($curso['id']) ?>">
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </div>
    </div>

    </div>

    <!-- Botones -->
    <div class="ds-form-actions mb-5">
        <button type="button" class="btn btn-outline-secondary" data-step-prev disabled>
            <i class="bi bi-arrow-left me-1"></i>Anterior
        </button>
        <div class="d-flex flex-wrap justify-content-end gap-2">
            <a href="<?= $view->url('/guardias') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="button" class="btn btn-primary" data-step-next>
                Siguiente<i class="bi bi-arrow-right ms-1"></i>
            </button>
            <button type="submit" class="btn btn-primary d-none" data-step-submit>
                <i class="bi bi-save me-1"></i>Crear Ficha
            </button>
        </div>
    </div>
</form>
    </div>

    <div class="col-xl-4">
        <aside class="ds-step-nav">
            <div class="card ds-summary-card mb-4">
                <div class="card-body">
                    <div class="small text-uppercase fw-bold text-primary mb-2">Resumen de avance</div>
                    <h5 class="mb-3">Completa por bloques y valida en tiempo real.</h5>

                    <div class="ds-progress-label">
                        <span class="small text-muted">Campos obligatorios completos</span>
                        <strong id="requiredProgressText">0%</strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" id="requiredProgressBar" style="width:0%"></div>
                    </div>

                    <div class="ds-summary-list">
                        <div class="ds-summary-item active" data-summary-step="base">
                            <span>
                                <strong class="d-block">1. Base</strong>
                                <span class="small text-muted">Tipo y datos personales</span>
                            </span>
                            <span class="badge text-bg-light" data-summary-status="base">0/6</span>
                        </div>
                        <div class="ds-summary-item" data-summary-step="contacto">
                            <span>
                                <strong class="d-block">2. Contacto</strong>
                                <span class="small text-muted">Domicilio</span>
                            </span>
                            <span class="badge text-bg-light" data-summary-status="contacto">0/0</span>
                        </div>
                        <div class="ds-summary-item" data-summary-step="historial">
                            <span>
                                <strong class="d-block">3. Historial</strong>
                                <span class="small text-muted">Finanzas e historial</span>
                            </span>
                            <span class="badge text-bg-light" data-summary-status="historial">0/0</span>
                        </div>
                        <div class="ds-summary-item" data-summary-step="cursos">
                            <span>
                                <strong class="d-block">4. Cursos</strong>
                                <span class="small text-muted">Seleccion final</span>
                            </span>
                            <span class="badge text-bg-light" data-summary-status="cursos">0/0</span>
                        </div>
                    </div>

                    <div class="ds-summary-note">
                        <strong class="d-block mb-1">Validaciones clave</strong>
                        Cedula, genero y fecha de nacimiento son obligatorios para crear la ficha.
                    </div>
                    <div class="ds-summary-note">
                        <strong class="d-block mb-1">Dato sensible</strong>
                        La cuenta bancaria se guarda cifrada cuando se registra.
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
const STEP_ORDER = ['base', 'contacto', 'historial', 'cursos'];
const prefillTrabajos = <?= json_encode($prefillTrabajos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const prefillHijos = <?= json_encode($prefillHijos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const prefillReferencias = <?= json_encode($prefillReferencias, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let currentStepIndex = 0;

function esc(v) {
    if (v == null) return '';
    const d = document.createElement('div');
    d.textContent = String(v);
    return d.innerHTML;
}

function getPanel(step) {
    return document.querySelector(`[data-step-panel="${step}"]`);
}

function getStepFields(step) {
    const panel = getPanel(step);
    if (!panel) return [];
    return Array.from(panel.querySelectorAll('input, select, textarea')).filter(field => {
        return field.type !== 'hidden' && !field.disabled && field.willValidate;
    });
}

function updateFieldState(field, force = false) {
    if (!field || field.type === 'hidden' || !field.willValidate || field.disabled) return;
    const touched = field.dataset.touched === '1' || force;
    const hasValue = field.type === 'checkbox' ? field.checked : String(field.value || '').trim() !== '';
    if (!touched && !field.required && !hasValue) {
        field.classList.remove('is-valid', 'is-invalid');
        return;
    }
    field.classList.toggle('is-invalid', !field.checkValidity());
    field.classList.toggle('is-valid', field.checkValidity() && (hasValue || field.required));
}

function refreshSummary() {
    let requiredTotal = 0;
    let requiredValid = 0;
    STEP_ORDER.forEach(step => {
        const fields = getStepFields(step).filter(field => field.required);
        const validCount = fields.filter(field => field.checkValidity()).length;
        requiredTotal += fields.length;
        requiredValid += validCount;
        document.querySelectorAll(`[data-step-status="${step}"], [data-summary-status="${step}"]`).forEach(badge => {
            badge.textContent = `${validCount}/${fields.length}`;
            badge.className = `badge ${fields.length > 0 && validCount === fields.length ? 'text-bg-success' : 'text-bg-light'}`;
        });
    });
    const percent = requiredTotal > 0 ? Math.round((requiredValid / requiredTotal) * 100) : 0;
    const bar = document.getElementById('requiredProgressBar');
    const text = document.getElementById('requiredProgressText');
    if (bar) bar.style.width = `${percent}%`;
    if (text) text.textContent = `${percent}%`;
}

function showStep(index) {
    currentStepIndex = Math.max(0, Math.min(index, STEP_ORDER.length - 1));
    STEP_ORDER.forEach((step, idx) => {
        const active = idx === currentStepIndex;
        getPanel(step)?.classList.toggle('active', active);
        document.querySelector(`[data-step-target="${step}"]`)?.classList.toggle('active', active);
        document.querySelector(`[data-summary-step="${step}"]`)?.classList.toggle('active', active);
    });
    document.querySelector('[data-step-prev]')?.toggleAttribute('disabled', currentStepIndex === 0);
    document.querySelector('[data-step-next]')?.classList.toggle('d-none', currentStepIndex === STEP_ORDER.length - 1);
    document.querySelector('[data-step-submit]')?.classList.toggle('d-none', currentStepIndex !== STEP_ORDER.length - 1);
}

function validateStep(step) {
    let firstInvalid = null;
    getStepFields(step).forEach(field => {
        field.dataset.touched = '1';
        updateFieldState(field, true);
        if (!field.checkValidity() && firstInvalid === null) firstInvalid = field;
    });
    if (!firstInvalid) return true;
    firstInvalid.reportValidity();
    firstInvalid.focus({ preventScroll: false });
    return false;
}

// ---- Trabajos Anteriores ----
let trabajoIdx = 0;
function addTrabajo(data = {}) {
    document.getElementById('trabajos-empty').style.display = 'none';
    const c = document.getElementById('trabajos-container');
    const i = trabajoIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 align-items-end trabajo-row ds-repeat-card';
    div.innerHTML = `
        <div class="col-md-4">
            <label class="form-label small">Empresa</label>
            <input type="text" name="trabajos_anteriores[${i}][empresa]" class="form-control form-control-sm" required value="${esc(data.empresa)}">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Cargo</label>
            <input type="text" name="trabajos_anteriores[${i}][cargo]" class="form-control form-control-sm" value="${esc(data.cargo)}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Tiempo</label>
            <input type="text" name="trabajos_anteriores[${i}][tiempo]" class="form-control form-control-sm" placeholder="Ej: 2 años">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Dirección</label>
            <input type="text" name="trabajos_anteriores[${i}][direccion]" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Teléfonos</label>
            <input type="text" name="trabajos_anteriores[${i}][telefonos]" class="form-control form-control-sm">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.trabajo-row').remove();checkEmpty('trabajos');refreshSummary()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
    div.querySelector(`[name="trabajos_anteriores[${i}][empresa]"]`).value = data.empresa || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][cargo]"]`).value = data.cargo || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][tiempo]"]`).value = data.tiempo || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][direccion]"]`).value = data.direccion || '';
    div.querySelector(`[name="trabajos_anteriores[${i}][telefonos]"]`).value = data.telefonos || '';
    refreshSummary();
}

// ---- Hijos Menores ----
let hijoIdx = 0;
function addHijo(data = {}) {
    document.getElementById('hijos-empty').style.display = 'none';
    const c = document.getElementById('hijos-container');
    const i = hijoIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 align-items-end hijo-row ds-repeat-card';
    div.innerHTML = `
        <div class="col-md-5">
            <label class="form-label small">Nombre</label>
            <input type="text" name="hijos_menores[${i}][nombre]" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Edad</label>
            <input type="number" name="hijos_menores[${i}][edad]" class="form-control form-control-sm" min="0" max="17">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Justifica con cédula</label>
            <select name="hijos_menores[${i}][justifica_con_cedula]" class="form-select form-select-sm">
                <option value="0">No</option>
                <option value="1">Sí</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.hijo-row').remove();checkEmpty('hijos');refreshSummary()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
    div.querySelector(`[name="hijos_menores[${i}][nombre]"]`).value = data.nombre || '';
    div.querySelector(`[name="hijos_menores[${i}][edad]"]`).value = data.edad || '';
    div.querySelector(`[name="hijos_menores[${i}][justifica_con_cedula]"]`).value = String(data.justifica_con_cedula || '0');
    refreshSummary();
}

// ---- Referencias ----
let refIdx = 0;
function addReferencia(data = {}) {
    document.getElementById('referencias-empty').style.display = 'none';
    const c = document.getElementById('referencias-container');
    const i = refIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 align-items-end ref-row ds-repeat-card';
    div.innerHTML = `
        <div class="col-md-2">
            <label class="form-label small">Parentesco</label>
            <select name="referencias[${i}][parentesco]" class="form-select form-select-sm">
                <option value="PADRE">Padre</option>
                <option value="MADRE">Madre</option>
                <option value="HERMANO">Hermano/a</option>
                <option value="OTRO">Otro</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Nombre</label>
            <input type="text" name="referencias[${i}][nombre]" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Dirección</label>
            <input type="text" name="referencias[${i}][direccion]" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Teléfono</label>
            <input type="text" name="referencias[${i}][telefono]" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.ref-row').remove();checkEmpty('referencias');refreshSummary()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
    div.querySelector(`[name="referencias[${i}][parentesco]"]`).value = data.parentesco || 'OTRO';
    div.querySelector(`[name="referencias[${i}][nombre]"]`).value = data.nombre || '';
    div.querySelector(`[name="referencias[${i}][direccion]"]`).value = data.direccion || '';
    div.querySelector(`[name="referencias[${i}][telefono]"]`).value = data.telefono || '';
    refreshSummary();
}

function checkEmpty(section) {
    const container = document.getElementById(section + '-container');
    const empty = document.getElementById(section + '-empty');
    const selectors = {
        trabajos: '.trabajo-row',
        hijos: '.hijo-row',
        referencias: '.ref-row'
    };
    if (!container || !empty) return;
    empty.style.display = container.querySelector(selectors[section]) ? 'none' : '';
}

// ---- Cursos: habilitar campos al marcar checkbox ----
document.querySelectorAll('.curso-check').forEach(cb => {
    const row = cb.closest('tr');
    row?.classList.toggle('table-primary', cb.checked);
    cb.addEventListener('change', function() {
        const idx = this.dataset.index;
        const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`);
        const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`);
        fecha.disabled = !this.checked;
        obs.disabled = !this.checked;
        this.closest('tr')?.classList.toggle('table-primary', this.checked);
        refreshSummary();
    });
});

const fichaForm = document.getElementById('fichaForm');

if (fichaForm) {
    prefillTrabajos.forEach(row => addTrabajo(row));
    prefillHijos.forEach(row => addHijo(row));
    prefillReferencias.forEach(row => addReferencia(row));

    checkEmpty('trabajos');
    checkEmpty('hijos');
    checkEmpty('referencias');

    fichaForm.addEventListener('input', event => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        updateFieldState(field);
        refreshSummary();
    });

    fichaForm.addEventListener('change', event => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        field.dataset.touched = '1';
        updateFieldState(field, true);
        refreshSummary();
    });

    fichaForm.addEventListener('focusout', event => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        field.dataset.touched = '1';
        updateFieldState(field, true);
    });

    document.querySelectorAll('[data-step-target]').forEach(button => {
        button.addEventListener('click', () => {
            const index = STEP_ORDER.indexOf(button.dataset.stepTarget);
            if (index >= 0) showStep(index);
        });
    });

    document.querySelector('[data-step-prev]')?.addEventListener('click', () => showStep(currentStepIndex - 1));
    document.querySelector('[data-step-next]')?.addEventListener('click', () => {
        if (validateStep(STEP_ORDER[currentStepIndex])) showStep(currentStepIndex + 1);
    });

    fichaForm.addEventListener('submit', function(event) {
        this.querySelectorAll('.curso-hidden').forEach(el => el.remove());

        let ci = 0;
        document.querySelectorAll('.curso-check:checked').forEach(cb => {
            const idx = cb.dataset.index;
            const cursoId = document.querySelector(`.curso-id[data-index="${idx}"]`).value;
            const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`).value;
            const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`).value;

            const addHidden = (name, val) => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = name;
                inp.value = val;
                inp.className = 'curso-hidden';
                this.appendChild(inp);
            };

            addHidden(`cursos[${ci}][curso_id]`, cursoId);
            addHidden(`cursos[${ci}][fecha_obtencion]`, fecha);
            addHidden(`cursos[${ci}][observacion]`, obs);
            ci++;
        });

        if (this.checkValidity()) return;

        event.preventDefault();
        const firstInvalid = this.querySelector(':invalid');
        if (!firstInvalid) return;
        const targetPanel = firstInvalid.closest('[data-step-panel]');
        if (targetPanel) {
            const step = targetPanel.getAttribute('data-step-panel');
            const stepIndex = STEP_ORDER.indexOf(step);
            if (stepIndex >= 0) showStep(stepIndex);
        }
        firstInvalid.dataset.touched = '1';
        updateFieldState(firstInvalid, true);
        firstInvalid.reportValidity();
        refreshSummary();
    });

    showStep(0);
    refreshSummary();
}
</script>
<?php $view->endSection() ?>
