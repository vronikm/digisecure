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
?>

<?php $view->section('title') ?>Nueva Ficha — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item active">Nueva Ficha</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva Ficha de Guardia</h4>
    <a href="<?= $view->url('/guardias') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/guardias/crear') ?>" id="fichaForm">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

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
                        <?php foreach ($cursos as $i => $curso): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input curso-check"
                                       data-index="<?= $i ?>" id="curso_<?= $i ?>">
                            </td>
                            <td><label for="curso_<?= $i ?>"><code><?= $e($curso['codigo']) ?></code></label></td>
                            <td><label for="curso_<?= $i ?>"><?= $e($curso['nombre']) ?></label></td>
                            <td>
                                <input type="date" class="form-control form-control-sm curso-fecha" data-index="<?= $i ?>" disabled>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm curso-obs" data-index="<?= $i ?>"
                                       maxlength="200" disabled placeholder="Opcional">
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

    <!-- Botones -->
    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="<?= $view->url('/guardias') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Crear Ficha
        </button>
    </div>
</form>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
// ---- Trabajos Anteriores ----
let trabajoIdx = 0;
function addTrabajo() {
    document.getElementById('trabajos-empty').style.display = 'none';
    const c = document.getElementById('trabajos-container');
    const i = trabajoIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-3 align-items-end border-bottom pb-3 trabajo-row';
    div.innerHTML = `
        <div class="col-md-3">
            <label class="form-label small">Empresa</label>
            <input type="text" name="trabajos_anteriores[${i}][empresa]" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Cargo</label>
            <input type="text" name="trabajos_anteriores[${i}][cargo]" class="form-control form-control-sm">
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
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.trabajo-row').remove();checkEmpty('trabajos')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
}

// ---- Hijos Menores ----
let hijoIdx = 0;
function addHijo() {
    document.getElementById('hijos-empty').style.display = 'none';
    const c = document.getElementById('hijos-container');
    const i = hijoIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-3 align-items-end border-bottom pb-3 hijo-row';
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
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.hijo-row').remove();checkEmpty('hijos')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
}

// ---- Referencias ----
let refIdx = 0;
function addReferencia() {
    document.getElementById('referencias-empty').style.display = 'none';
    const c = document.getElementById('referencias-container');
    const i = refIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-3 align-items-end border-bottom pb-3 ref-row';
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
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.ref-row').remove();checkEmpty('referencias')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
}

function checkEmpty(section) {
    const container = document.getElementById(section + '-container');
    const rows = container.querySelectorAll('.' + section.slice(0, -1).replace('referencias','ref') + '-row, .trabajo-row, .hijo-row, .ref-row');
    const empty = document.getElementById(section + '-empty');
    if (rows.length === 0 && empty) empty.style.display = '';
}

// ---- Cursos: habilitar campos al marcar checkbox ----
document.querySelectorAll('.curso-check').forEach(cb => {
    cb.addEventListener('change', function() {
        const idx = this.dataset.index;
        const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`);
        const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`);
        fecha.disabled = !this.checked;
        obs.disabled = !this.checked;
    });
});

// ---- Antes de enviar: inyectar cursos seleccionados como hidden inputs ----
document.getElementById('fichaForm').addEventListener('submit', function() {
    // Remover cursos previos
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
});
</script>
<?php $view->endSection() ?>
