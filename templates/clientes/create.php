<?php
/**
 * Formulario de alta de cliente.
 *
 * Variables: $user, $csrfToken, $currentPath, $input
 * Opcionales: $error
 */

use DigiSecure\Cliente\Domain\CatalogoCliente;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Shared\Domain\EcuadorGeo;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$old = static fn(string $k, string $d = ''): string => (string) ($input[$k] ?? $d);
?>

<?php $view->section('title') ?>Nuevo cliente - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/clientes') ?>">Clientes</a></li>
<li class="breadcrumb-item active" aria-current="page">Nuevo</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-briefcase"></i></span>
        <div>
            <h1>Registrar nuevo cliente</h1>
            <small>Empresa o persona a la que se prestará servicios de seguridad</small>
        </div>
    </div>
    <a href="<?= $view->url('/clientes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo registrar el cliente</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url('/clientes/crear') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-building"></i></span>
            <div>
                <div class="ds-card-title">Identidad del cliente</div>
                <div class="ds-card-subtitle">Datos legales y de identificacion</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo documento <span class="ds-required">*</span></label>
                        <select name="tipo_documento" class="form-select" required>
                            <option value="RUC" <?= ($input['tipo_documento'] ?? 'RUC') === 'RUC' ? 'selected' : '' ?>>RUC (13 digitos)</option>
                            <option value="CEDULA" <?= ($input['tipo_documento'] ?? '') === 'CEDULA' ? 'selected' : '' ?>>Cedula (10 digitos)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Documento <span class="ds-required">*</span></label>
                        <input type="text" name="documento" class="form-control" required
                               inputmode="numeric" pattern="\d{10,13}" maxlength="13"
                               value="<?= $e($old('documento')) ?>">
                        <span class="ds-form-help">Se valida con el algoritmo del SRI / Registro Civil.</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Sector</label>
                        <?php $sectorActual = $old('sector'); ?>
                        <select name="sector" id="cli-sector" class="form-select">
                            <option value="">— Selecciona —</option>
                            <?php foreach (CatalogoCliente::SECTORES as $s): ?>
                            <option value="<?= $e($s) ?>" <?= $sectorActual === $s ? 'selected' : '' ?>><?= $e($s) ?></option>
                            <?php endforeach ?>
                            <?php if ($sectorActual !== '' && !in_array($sectorActual, CatalogoCliente::SECTORES, true)): ?>
                            <option value="<?= $e($sectorActual) ?>" selected><?= $e($sectorActual) ?> (actual)</option>
                            <?php endif ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Razon social <span class="ds-required">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required maxlength="255"
                               value="<?= $e($old('razon_social')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control" maxlength="255"
                               value="<?= $e($old('nombre_comercial')) ?>">
                    </div>
                </div>                
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
            <div>
                <div class="ds-card-title">Contacto principal</div>
                <div class="ds-card-subtitle">Persona de referencia en el cliente</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre del contacto</label>
                        <input type="text" name="contacto_nombre" class="form-control" maxlength="200"
                               value="<?= $e($old('contacto_nombre')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Cargo</label>
                        <input type="text" name="contacto_cargo" class="form-control" maxlength="120"
                               value="<?= $e($old('contacto_cargo')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email</label>
                        <input type="email" name="contacto_email" class="form-control" maxlength="190"
                               value="<?= $e($old('contacto_email')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="contacto_telefono" class="form-control" maxlength="30"
                               value="<?= $e($old('contacto_telefono')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
            <div>
                <div class="ds-card-title">Ubicación</div>
                <div class="ds-card-subtitle">Direccin donde se localiza el cliente</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Pais</label>
                        <input type="text" name="pais" class="form-control" maxlength="2"
                               value="<?= $e($old('pais', 'EC')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Provincia</label>
                        <?php $provActual = $old('provincia'); ?>
                        <select name="provincia" id="cli-provincia" class="form-select">
                            <option value="">— Selecciona —</option>
                            <?php foreach (EcuadorGeo::provincias() as $p): ?>
                            <option value="<?= $e($p) ?>" <?= $provActual === $p ? 'selected' : '' ?>><?= $e($p) ?></option>
                            <?php endforeach ?>
                            <?php if ($provActual !== '' && !in_array($provActual, EcuadorGeo::provincias(), true)): ?>
                            <option value="<?= $e($provActual) ?>" selected><?= $e($provActual) ?> (actual)</option>
                            <?php endif ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ciudad</label>
                        <select name="ciudad" id="cli-ciudad" class="form-select" data-selected="<?= $e($old('ciudad')) ?>">
                            <option value="">— Selecciona provincia primero —</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Direccion</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255"
                               value="<?= $e($old('direccion')) ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Notas internas</label>
                        <textarea name="notas" class="form-control" rows="3"
                                  placeholder="Detalles del servicio, observaciones, etc."><?= $e($old('notas')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/clientes') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Crear cliente
        </button>
    </div>
</form>
<?= $view->include('clientes/_geo-script') ?>
<?php $view->endSection() ?>
