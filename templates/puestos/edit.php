<?php
/**
 * Formulario de edicion de puesto.
 *
 * Variables: $user, $csrfToken, $currentPath, $puesto, $clientes, $tiposPuesto, $modalidades, $input
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$valor = static fn(string $k, string $d = ''): string => (string) ($input[$k] ?? $puesto[$k] ?? $d);
?>

<?php $view->section('title') ?>Editar puesto — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/puestos') ?>">Puestos</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/puestos/' . $puesto['id']) ?>"><?= $e((string) $puesto['nombre']) ?></a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar puesto</h1>
            <small><?= $e((string) $puesto['nombre']) ?> &middot; <?= $e((string) ($puesto['cliente_razon_social'] ?? '')) ?></small>
        </div>
    </div>
    <a href="<?= $view->url('/puestos/' . $puesto['id']) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al detalle
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudieron guardar los cambios</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url('/puestos/' . $puesto['id'] . '/editar') ?>">
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
                            <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $e($cl['id']) ?>" <?= $valor('cliente_id') === $cl['id'] ? 'selected' : '' ?>>
                                <?= $e($cl['razon_social']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre del puesto <span class="ds-required">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="200"
                               value="<?= $e($valor('nombre')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Codigo interno</label>
                        <input type="text" name="codigo" class="form-control" maxlength="50"
                               value="<?= $e($valor('codigo')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <?php foreach ($tiposPuesto as $tp): ?>
                            <option value="<?= $tp->value ?>" <?= $valor('tipo') === $tp->value ? 'selected' : '' ?>>
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
                            <option value="<?= $mod->value ?>" <?= $valor('modalidad') === $mod->value ? 'selected' : '' ?>>
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
                               value="<?= $e($valor('num_guardias_requeridos', '1')) ?>">
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
                <div class="ds-card-subtitle">Direccion y coordenadas</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Direccion</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255"
                               value="<?= $e($valor('direccion')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" maxlength="100"
                               value="<?= $e($valor('ciudad')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-control" maxlength="100"
                               value="<?= $e($valor('provincia')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Pais</label>
                        <input type="text" name="pais" class="form-control" maxlength="2"
                               value="<?= $e($valor('pais', 'EC')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Latitud</label>
                        <input type="number" step="0.0000001" min="-90" max="90"
                               name="latitud" class="form-control"
                               value="<?= $e($valor('latitud')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Longitud</label>
                        <input type="number" step="0.0000001" min="-180" max="180"
                               name="longitud" class="form-control"
                               value="<?= $e($valor('longitud')) ?>">
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
                <div class="ds-card-subtitle">Horario, contacto y observaciones</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Horario / Cobertura</label>
                        <input type="text" name="horario_descripcion" class="form-control" maxlength="255"
                               value="<?= $e($valor('horario_descripcion')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Contacto en el sitio</label>
                        <input type="text" name="contacto_sitio" class="form-control" maxlength="200"
                               value="<?= $e($valor('contacto_sitio')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono del sitio</label>
                        <input type="text" name="telefono_sitio" class="form-control" maxlength="30"
                               value="<?= $e($valor('telefono_sitio')) ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3"><?= $e($valor('observaciones')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/puestos/' . $puesto['id']) ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>
<?php $view->endSection() ?>
