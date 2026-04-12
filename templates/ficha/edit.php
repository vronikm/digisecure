<?php
/**
 * Formulario de edición de ficha de guardia.
 *
 * Variables: $user, $csrfToken, $currentPath, $ficha, $trabajos, $hijos, $referencias, $fichasCursos, $cursos
 * Opcionales: $error (string)
 */

use DigiSecure\Ficha\Domain\Enum\EstadoCivil;
use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Domain\Enum\Parentesco;
use DigiSecure\Ficha\Domain\Enum\TipoSangre;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$old = static fn(string $key, string $default = '') => $e((string)($ficha[$key] ?? $default));

$fichaId = $ficha['id'];
$estado = EstadoFicha::from($ficha['estado']);

// Mapa de cursos ya asignados a la ficha: curso_id => row
$cursosAsignados = [];
foreach ($fichasCursos as $fc) {
    $cursosAsignados[$fc['curso_id']] = $fc;
}
?>

<?php $view->section('title') ?>Editar <?= $e($ficha['correlativo']) ?> — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-pencil-square me-2"></i>Editar Ficha <?= $e($ficha['correlativo']) ?></h4>
        <span class="badge <?= $estado->badgeClass() ?>"><?= $e($estado->label()) ?></span>
    </div>
    <a href="<?= $view->url('/guardias/' . $fichaId) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver al detalle
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/guardias/' . $fichaId . '/editar') ?>" id="fichaForm">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <!-- Datos Personales -->
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
                    <label class="form-label">Cédula</label>
                    <input type="text" name="cedula" class="form-control" maxlength="10" minlength="10"
                           pattern="\d{10}" placeholder="10 dígitos" value="<?= $e($ficha['cedula_display'] ?? '') ?>">
                    <div class="form-text">Dejar vacío para mantener la cédula actual</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Género <span class="text-danger">*</span></label>
                    <select name="genero" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach (Genero::cases() as $g): ?>
                        <option value="<?= $g->value ?>" <?= ($ficha['genero'] ?? '') === $g->value ? 'selected' : '' ?>>
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
                        <option value="<?= $e($ts->value) ?>" <?= ($ficha['tipo_sangre'] ?? '') === $ts->value ? 'selected' : '' ?>>
                            <?= $e($ts->label()) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estatura (m)</label>
                    <input type="number" name="estatura" class="form-control" step="0.01" min="1.00" max="2.50"
                           value="<?= $old('estatura') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado Civil</label>
                    <select name="estado_civil" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach (EstadoCivil::cases() as $ec): ?>
                        <option value="<?= $ec->value ?>" <?= ($ficha['estado_civil'] ?? '') === $ec->value ? 'selected' : '' ?>>
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

    <!-- Domicilio -->
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
                        <option value="<?= $v ?>" <?= ($ficha['domicilio_tipo_vivienda'] ?? '') === $v ? 'selected' : '' ?>>
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

    <!-- Datos Financieros -->
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
                        <option value="AHORROS" <?= ($ficha['tipo_cuenta'] ?? '') === 'AHORROS' ? 'selected' : '' ?>>Ahorros</option>
                        <option value="CORRIENTE" <?= ($ficha['tipo_cuenta'] ?? '') === 'CORRIENTE' ? 'selected' : '' ?>>Corriente</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cuenta Bancaria</label>
                    <input type="text" name="cuenta_bancaria" class="form-control" maxlength="30"
                           value="<?= $e($ficha['cuenta_bancaria_display'] ?? '') ?>">
                    <div class="form-text">Dejar vacío para mantener la actual</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Instrucción -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Instrucción</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Primaria</label>
                    <input type="text" name="instruccion_primaria" class="form-control" maxlength="200" value="<?= $old('instruccion_primaria') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Secundaria (Bachillerato)</label>
                    <input type="text" name="instruccion_secundaria" class="form-control" maxlength="200" value="<?= $old('instruccion_secundaria') ?>">
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
                <div class="col-md-4">
                    <label class="form-label">Fecha Revisión</label>
                    <input type="date" name="fecha_revision" class="form-control" value="<?= $old('fecha_revision') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Trabajos Anteriores (dinámico, pre-cargados) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-briefcase me-2"></i>Trabajos Anteriores</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTrabajo()">
                <i class="bi bi-plus-lg me-1"></i>Agregar
            </button>
        </div>
        <div class="card-body" id="trabajos-container">
            <p class="text-muted small mb-0" id="trabajos-empty" <?= $trabajos !== [] ? 'style="display:none"' : '' ?>>
                <i class="bi bi-info-circle me-1"></i>Requisito básico: al menos 1 trabajo anterior.
            </p>
        </div>
    </div>

    <!-- Hijos Menores (dinámico, pre-cargados) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people me-2"></i>Hijos Menores</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addHijo()">
                <i class="bi bi-plus-lg me-1"></i>Agregar
            </button>
        </div>
        <div class="card-body" id="hijos-container">
            <p class="text-muted small mb-0" id="hijos-empty" <?= $hijos !== [] ? 'style="display:none"' : '' ?>>
                Sin hijos menores registrados.
            </p>
        </div>
    </div>

    <!-- Referencias Personales (dinámico, pre-cargadas) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-lines-fill me-2"></i>Referencias Personales</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addReferencia()">
                <i class="bi bi-plus-lg me-1"></i>Agregar
            </button>
        </div>
        <div class="card-body" id="referencias-container">
            <p class="text-muted small mb-0" id="referencias-empty" <?= $referencias !== [] ? 'style="display:none"' : '' ?>>
                Sin referencias registradas.
            </p>
        </div>
    </div>

    <!-- Cursos de Seguridad -->
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
                                       data-index="<?= $i ?>" id="curso_<?= $i ?>"
                                       <?= $asignado ? 'checked' : '' ?>>
                            </td>
                            <td><label for="curso_<?= $i ?>"><code><?= $e($curso['codigo']) ?></code></label></td>
                            <td><label for="curso_<?= $i ?>"><?= $e($curso['nombre']) ?></label></td>
                            <td>
                                <input type="date" class="form-control form-control-sm curso-fecha" data-index="<?= $i ?>"
                                       <?= $asignado ? '' : 'disabled' ?>
                                       value="<?= $e($asignado['fecha_obtencion'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm curso-obs" data-index="<?= $i ?>"
                                       maxlength="200" <?= $asignado ? '' : 'disabled' ?>
                                       value="<?= $e($asignado['observacion'] ?? '') ?>">
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
        <a href="<?= $view->url('/guardias/' . $fichaId) ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Guardar Cambios
        </button>
    </div>
</form>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
// ---- Trabajos Anteriores ----
let trabajoIdx = 0;
function addTrabajo(data) {
    document.getElementById('trabajos-empty').style.display = 'none';
    const c = document.getElementById('trabajos-container');
    const i = trabajoIdx++;
    const d = data || {};
    const div = document.createElement('div');
    div.className = 'row g-2 mb-3 align-items-end border-bottom pb-3 trabajo-row';
    div.innerHTML = `
        <div class="col-md-3">
            <label class="form-label small">Empresa</label>
            <input type="text" name="trabajos_anteriores[${i}][empresa]" class="form-control form-control-sm" required value="${esc(d.empresa)}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Cargo</label>
            <input type="text" name="trabajos_anteriores[${i}][cargo]" class="form-control form-control-sm" value="${esc(d.cargo)}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Tiempo</label>
            <input type="text" name="trabajos_anteriores[${i}][tiempo]" class="form-control form-control-sm" value="${esc(d.tiempo)}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Dirección</label>
            <input type="text" name="trabajos_anteriores[${i}][direccion]" class="form-control form-control-sm" value="${esc(d.direccion)}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Teléfonos</label>
            <input type="text" name="trabajos_anteriores[${i}][telefonos]" class="form-control form-control-sm" value="${esc(d.telefonos)}">
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
function addHijo(data) {
    document.getElementById('hijos-empty').style.display = 'none';
    const c = document.getElementById('hijos-container');
    const i = hijoIdx++;
    const d = data || {};
    const div = document.createElement('div');
    div.className = 'row g-2 mb-3 align-items-end border-bottom pb-3 hijo-row';
    div.innerHTML = `
        <div class="col-md-5">
            <label class="form-label small">Nombre</label>
            <input type="text" name="hijos_menores[${i}][nombre]" class="form-control form-control-sm" required value="${esc(d.nombre)}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Edad</label>
            <input type="number" name="hijos_menores[${i}][edad]" class="form-control form-control-sm" min="0" max="17" value="${esc(d.edad)}">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Justifica con cédula</label>
            <select name="hijos_menores[${i}][justifica_con_cedula]" class="form-select form-select-sm">
                <option value="0" ${d.justifica_con_cedula == 1 ? '' : 'selected'}>No</option>
                <option value="1" ${d.justifica_con_cedula == 1 ? 'selected' : ''}>Sí</option>
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
function addReferencia(data) {
    document.getElementById('referencias-empty').style.display = 'none';
    const c = document.getElementById('referencias-container');
    const i = refIdx++;
    const d = data || {};
    const div = document.createElement('div');
    div.className = 'row g-2 mb-3 align-items-end border-bottom pb-3 ref-row';
    const pOpts = ['PADRE','MADRE','HERMANO','OTRO'];
    const pLabels = {'PADRE':'Padre','MADRE':'Madre','HERMANO':'Hermano/a','OTRO':'Otro'};
    let selHtml = pOpts.map(p => `<option value="${p}" ${(d.parentesco||'OTRO')===p?'selected':''}>${pLabels[p]}</option>`).join('');
    div.innerHTML = `
        <div class="col-md-2">
            <label class="form-label small">Parentesco</label>
            <select name="referencias[${i}][parentesco]" class="form-select form-select-sm">${selHtml}</select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Nombre</label>
            <input type="text" name="referencias[${i}][nombre]" class="form-control form-control-sm" required value="${esc(d.nombre)}">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Dirección</label>
            <input type="text" name="referencias[${i}][direccion]" class="form-control form-control-sm" value="${esc(d.direccion)}">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Teléfono</label>
            <input type="text" name="referencias[${i}][telefono]" class="form-control form-control-sm" value="${esc(d.telefono)}">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.ref-row').remove();checkEmpty('referencias')">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    c.appendChild(div);
}

function esc(v) {
    if (v == null) return '';
    const d = document.createElement('div');
    d.textContent = String(v);
    return d.innerHTML;
}

function checkEmpty(section) {
    const container = document.getElementById(section + '-container');
    const rows = container.querySelectorAll('.trabajo-row, .hijo-row, .ref-row');
    const empty = document.getElementById(section + '-empty');
    if (rows.length === 0 && empty) empty.style.display = '';
}

// ---- Cursos: habilitar campos al marcar checkbox ----
document.querySelectorAll('.curso-check').forEach(cb => {
    cb.addEventListener('change', function() {
        const idx = this.dataset.index;
        document.querySelector(`.curso-fecha[data-index="${idx}"]`).disabled = !this.checked;
        document.querySelector(`.curso-obs[data-index="${idx}"]`).disabled = !this.checked;
    });
});

// ---- Inyectar cursos seleccionados antes de enviar ----
document.getElementById('fichaForm').addEventListener('submit', function() {
    this.querySelectorAll('.curso-hidden').forEach(el => el.remove());
    let ci = 0;
    document.querySelectorAll('.curso-check:checked').forEach(cb => {
        const idx = cb.dataset.index;
        const cursoId = document.querySelector(`.curso-id[data-index="${idx}"]`).value;
        const fecha = document.querySelector(`.curso-fecha[data-index="${idx}"]`).value;
        const obs = document.querySelector(`.curso-obs[data-index="${idx}"]`).value;
        const add = (n, v) => { const inp = document.createElement('input'); inp.type='hidden'; inp.name=n; inp.value=v; inp.className='curso-hidden'; this.appendChild(inp); };
        add(`cursos[${ci}][curso_id]`, cursoId);
        add(`cursos[${ci}][fecha_obtencion]`, fecha);
        add(`cursos[${ci}][observacion]`, obs);
        ci++;
    });
});

// ---- Pre-cargar datos existentes ----
(function() {
    const trabajos = <?= json_encode(array_values($trabajos), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const hijos = <?= json_encode(array_values($hijos), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const refs = <?= json_encode(array_values($referencias), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    trabajos.forEach(t => addTrabajo(t));
    hijos.forEach(h => addHijo(h));
    refs.forEach(r => addReferencia(r));
})();
</script>
<?php $view->endSection() ?>
