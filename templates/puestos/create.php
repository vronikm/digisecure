<?php
/**
 * Formulario de alta de puesto.
 *
 * Variables: $user, $csrfToken, $currentPath, $clientes, $tiposPuesto, $modalidades, $input
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$old = static fn(string $k, string $d = ''): string => (string) ($input[$k] ?? $d);
?>

<?php $view->section('title') ?>Nuevo puesto - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/puestos') ?>">Puestos</a></li>
<li class="breadcrumb-item active">Nuevo</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-geo-alt-fill"></i></span>
        <div>
            <h1>Registrar nuevo puesto</h1>
            <small>Sitio fisico donde se prestara el servicio de seguridad</small>
        </div>
    </div>
    <a href="<?= $view->url('/puestos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo registrar el puesto</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url('/puestos/crear') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
            <div>
                <div class="ds-card-title">Identidad del puesto</div>
                <div class="ds-card-subtitle">Cliente, nombre y clasificacion</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Cliente <span class="ds-required">*</span></label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">- Seleccione un cliente -</option>
                            <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $e($cl['id']) ?>" <?= ($input['cliente_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>>
                                <?= $e($cl['razon_social']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                        <span class="ds-form-help">Solo aparecen clientes activos. <a href="<?= $view->url('/clientes/crear') ?>">Registrar uno nuevo</a></span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre del puesto <span class="ds-required">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="200"
                               placeholder="Ej: Sucursal Norte, Bodega A, Edificio Central"
                               value="<?= $e($old('nombre')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Codigo interno</label>
                        <input type="text" name="codigo" class="form-control" maxlength="50"
                               placeholder="P-001"
                               value="<?= $e($old('codigo')) ?>">
                        <span class="ds-form-help">Opcional. Unico por empresa.</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <?php foreach ($tiposPuesto as $tp): ?>
                            <option value="<?= $tp->value ?>" <?= ($input['tipo'] ?? 'oficina') === $tp->value ? 'selected' : '' ?>>
                                <?= $e($tp->label()) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Modalidad</label>
                        <select name="modalidad" class="form-select">
                            <?php foreach ($modalidades as $mod): ?>
                            <option value="<?= $mod->value ?>" <?= ($input['modalidad'] ?? 'fijo') === $mod->value ? 'selected' : '' ?>>
                                <?= $e($mod->label()) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Plazas requeridas <span class="ds-required">*</span></label>
                        <input type="number" name="num_guardias_requeridos" class="form-control" required
                               min="1" max="999"
                               value="<?= $e($old('num_guardias_requeridos', '1')) ?>">
                        <span class="ds-form-help">Numero de guardias simultaneos.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
            <div>
                <div class="ds-card-title">Ubicacion</div>
                <div class="ds-card-subtitle">Direccion fisica y coordenadas opcionales</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Direccion</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255"
                               value="<?= $e($old('direccion')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" maxlength="100"
                               value="<?= $e($old('ciudad')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-control" maxlength="100"
                               value="<?= $e($old('provincia')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Pais</label>
                        <input type="text" name="pais" class="form-control" maxlength="2"
                               value="<?= $e($old('pais', 'EC')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Latitud</label>
                        <input type="number" step="0.0000001" min="-90" max="90"
                               name="latitud" class="form-control"
                               placeholder="Ej: -0.180653"
                               value="<?= $e($old('latitud')) ?>">
                        <span class="ds-form-help">Decimal entre -90 y 90.</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Longitud</label>
                        <input type="number" step="0.0000001" min="-180" max="180"
                               name="longitud" class="form-control"
                               placeholder="Ej: -78.467834"
                               value="<?= $e($old('longitud')) ?>">
                        <span class="ds-form-help">Decimal entre -180 y 180.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
            <div>
                <div class="ds-card-title">Operacion</div>
                <div class="ds-card-subtitle">Horario, contacto del sitio y observaciones</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Horario / Cobertura</label>
                        <input type="text" name="horario_descripcion" class="form-control" maxlength="255"
                               placeholder="Ej: 24/7, L-V 8:00-18:00, fines de semana 8:00-20:00"
                               value="<?= $e($old('horario_descripcion')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Contacto en el sitio</label>
                        <input type="text" name="contacto_sitio" class="form-control" maxlength="200"
                               value="<?= $e($old('contacto_sitio')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono del sitio</label>
                        <input type="text" name="telefono_sitio" class="form-control" maxlength="30"
                               value="<?= $e($old('telefono_sitio')) ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3"
                                  placeholder="Detalles operativos, accesos, riesgos, etc."><?= $e($old('observaciones')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/puestos') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Crear puesto
        </button>
    </div>
</form>
<?php $view->endSection() ?>
