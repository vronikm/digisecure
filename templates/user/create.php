<?php
/**
 * Formulario de creacion de usuario.
 *
 * Variables: $user, $csrfToken, $currentPath, $roles, $input
 * Opcionales: $esSuperAdmin, $tenants, $systemTenantId
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$esSuperAdmin = (bool) ($esSuperAdmin ?? false);
$tenants = is_array($tenants ?? null) ? $tenants : [];
$systemTenantId = (string) ($systemTenantId ?? $user->tenantId);
$scopeSel = (string) ($input['user_scope'] ?? 'empresa');
$tenantSel = (string) ($input['tenant_id'] ?? '');
?>

<?php $view->section('title') ?>Nuevo usuario - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/usuarios') ?>">Usuarios</a></li>
<li class="breadcrumb-item active">Nuevo</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-plus-fill"></i></span>
        <div>
            <h1>Nuevo usuario</h1>
            <small>Crea una cuenta y asigna su rol dentro de la empresa o del sistema</small>
        </div>
    </div>
    <a href="<?= $view->url('/usuarios') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo crear el usuario</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="ds-form-wrap ds-user-form-wrap">
<form method="post" action="<?= $view->url('/usuarios/crear') ?>" class="ds-form-main">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-person"></i></span>
            <div>
                <div class="ds-card-title">Datos del usuario</div>
                <div class="ds-card-subtitle">Informacion personal y de contacto</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-user-grid">
                <?php if ($esSuperAdmin): ?>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ambito <span class="ds-required">*</span></label>
                        <select name="user_scope" id="user_scope" class="form-select" required>
                            <option value="empresa" <?= $scopeSel === 'empresa' ? 'selected' : '' ?>>Empresa (Tenant)</option>
                            <option value="sistema" <?= $scopeSel === 'sistema' ? 'selected' : '' ?>>Sistema</option>
                        </select>
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-8" id="tenant_scope_wrap">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tenant (Empresa) <span class="ds-required">*</span></label>
                        <select name="tenant_id" id="tenant_id" class="form-select">
                            <option value="">- Seleccione empresa -</option>
                            <?php foreach ($tenants as $t): ?>
                            <option value="<?= $e((string) $t['id']) ?>" <?= $tenantSel === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= $e((string) $t['razon_social']) ?> · RUC <?= $e((string) $t['ruc']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                        <span class="ds-form-help">Para "Sistema" se usa automaticamente la empresa de plataforma marcada en el sistema.</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombres <span class="ds-required">*</span></label>
                        <input type="text" name="nombres" class="form-control" required maxlength="120"
                               value="<?= $e($input['nombres'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Apellidos <span class="ds-required">*</span></label>
                        <input type="text" name="apellidos" class="form-control" required maxlength="120"
                               value="<?= $e($input['apellidos'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Cargo</label>
                        <input type="text" name="cargo" class="form-control" maxlength="120"
                               value="<?= $e($input['cargo'] ?? '') ?>">
                    </div>
                </div>

                <div class="ds-col-12 ds-col-md-6 ds-col-lg-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email <span class="ds-required">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="190"
                               value="<?= $e($input['email'] ?? '') ?>">
                        <span class="ds-form-help">Sera el identificador para iniciar sesion.</span>
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="30"
                               value="<?= $e($input['telefono'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado inicial</label>
                        <select name="estado" class="form-select">
                            <?php foreach (['activo','pendiente_verificacion','inactivo'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($input['estado'] ?? 'activo') === $est ? 'selected' : '' ?>>
                                <?= $e(ucfirst(str_replace('_', ' ', $est))) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-warning">
            <span class="ds-card-mark"><i class="bi bi-key"></i></span>
            <div>
                <div class="ds-card-title">Credenciales y rol</div>
                <div class="ds-card-subtitle">Acceso y permisos dentro del sistema</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-user-grid">
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-7">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Rol asignado <span class="ds-required">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">- Seleccione -</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $e($r['id']) ?>" <?= ($input['role_id'] ?? '') === $r['id'] ? 'selected' : '' ?>>
                                <?= $e($r['nombre']) ?>
                                <?php if ($r['descripcion']): ?> - <?= $e($r['descripcion']) ?><?php endif ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-5">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Contraseña inicial <span class="ds-required">*</span></label>
                        <div class="d-flex gap-2">
                            <input type="password" name="password" id="password-inicial" class="form-control" required minlength="8"
                                   autocomplete="new-password">
                            <button type="button" class="ds-btn ds-btn-ghost" data-toggle-password="password-inicial"
                                    aria-label="Mostrar contraseña" title="Mostrar u ocultar">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <span class="ds-form-help">Mínimo 8 caracteres. El usuario deberá cambiarla en su próximo inicio de sesión.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/usuarios') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Crear usuario
        </button>
    </div>
</form>
</div>

<?php if ($esSuperAdmin): ?>
<script>
(() => {
    const scope = document.getElementById('user_scope');
    const tenantWrap = document.getElementById('tenant_scope_wrap');
    const tenantInput = document.getElementById('tenant_id');
    if (!scope || !tenantWrap || !tenantInput) return;

    const refresh = () => {
        const isSistema = scope.value === 'sistema';
        tenantWrap.style.display = isSistema ? 'none' : '';
        tenantInput.required = !isSistema;
    };

    scope.addEventListener('change', refresh);
    refresh();
})();
</script>
<?php endif; ?>
<script>
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    const input = document.getElementById(btn.dataset.togglePassword);
    if (!input) return;
    btn.addEventListener('click', () => {
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        btn.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
        btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
    });
});
</script>
<?php $view->endSection() ?>
