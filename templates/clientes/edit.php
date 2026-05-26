<?php
/**
 * Formulario de edicion de cliente.
 *
 * Variables: $user, $csrfToken, $currentPath, $cliente, $input
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$valor = static fn(string $k, string $d = ''): string => (string) ($input[$k] ?? $cliente[$k] ?? $d);
?>

<?php $view->section('title') ?>Editar cliente - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/clientes') ?>">Clientes</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/clientes/' . $cliente['id']) ?>"><?= $e((string) $cliente['razon_social']) ?></a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar cliente</h1>
            <small><?= $e((string) $cliente['razon_social']) ?> &middot; <span class="ds-cell-mono"><?= $e((string) $cliente['documento']) ?></span></small>
        </div>
    </div>
    <a href="<?= $view->url('/clientes/' . $cliente['id']) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
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

<form class="ds-form-main" method="post" action="<?= $view->url('/clientes/' . $cliente['id'] . '/editar') ?>">
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
                <div class="col-md-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Razon social <span class="ds-required">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required maxlength="255"
                               value="<?= $e($valor('razon_social')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control" maxlength="255"
                               value="<?= $e($valor('nombre_comercial')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo documento <span class="ds-required">*</span></label>
                        <select name="tipo_documento" class="form-select" required>
                            <option value="RUC" <?= $valor('tipo_documento', 'RUC') === 'RUC' ? 'selected' : '' ?>>RUC (13 digitos)</option>
                            <option value="CEDULA" <?= $valor('tipo_documento') === 'CEDULA' ? 'selected' : '' ?>>Cedula (10 digitos)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Documento <span class="ds-required">*</span></label>
                        <input type="text" name="documento" class="form-control" required
                               inputmode="numeric" pattern="\d{10,13}" maxlength="13"
                               value="<?= $e($valor('documento')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Sector</label>
                        <input type="text" name="sector" class="form-control" maxlength="80"
                               value="<?= $e($valor('sector')) ?>">
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
                               value="<?= $e($valor('contacto_nombre')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Cargo</label>
                        <input type="text" name="contacto_cargo" class="form-control" maxlength="120"
                               value="<?= $e($valor('contacto_cargo')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email</label>
                        <input type="email" name="contacto_email" class="form-control" maxlength="190"
                               value="<?= $e($valor('contacto_email')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="contacto_telefono" class="form-control" maxlength="30"
                               value="<?= $e($valor('contacto_telefono')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
            <div>
                <div class="ds-card-title">Domicilio</div>
                <div class="ds-card-subtitle">Direccion del cliente</div>
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
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Notas internas</label>
                        <textarea name="notas" class="form-control" rows="3"><?= $e($valor('notas')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/clientes/' . $cliente['id']) ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>
<?php $view->endSection() ?>
