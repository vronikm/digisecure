<?php
/**
 * Vista de edicion de ficha de guardia.
 *
 * Reutiliza el formulario avanzado de templates/ficha/create.php
 * para mantener una sola fuente de verdad de UI y validaciones.
 *
 * Variables esperadas:
 * - $user, $csrfToken, $currentPath
 * - $ficha (array)
 * - $trabajos, $hijos, $referencias, $fichasCursos (arrays)
 * - $cursos (catalogo)
 * Opcional:
 * - $error (string)
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$fichaId = (string)($ficha['id'] ?? '');
$correlativo = trim((string)($ficha['correlativo'] ?? ''));

$input = [];
$scalarKeys = [
    'tipo_ficha',
    'nombres',
    'apellidos',
    'genero',
    'fecha_nacimiento',
    'lugar_nacimiento_ciudad',
    'lugar_nacimiento_provincia',
    'tipo_sangre',
    'estatura',
    'estado_civil',
    'celular',
    'email',
    'domicilio_ciudad',
    'domicilio_tipo_vivienda',
    'domicilio_telefono',
    'domicilio_calle',
    'domicilio_numero',
    'domicilio_barrio',
    'domicilio_referencia',
    'croquis_latitud',
    'croquis_longitud',
    'croquis_archivo_id',
    'contacto_emergencia_nombre',
    'contacto_emergencia_telefono',
    'banco',
    'tipo_cuenta',
    'instruccion_primaria',
    'instruccion_secundaria',
    'instruccion_superior',
    'fecha_ultimo_reentrenamiento',
    'fecha_revision',
];

foreach ($scalarKeys as $key) {
    if (array_key_exists($key, $ficha)) {
        $input[$key] = $ficha[$key];
    }
}

$input['tipo_ficha'] = (string)($input['tipo_ficha'] ?? $ficha['tipo_ficha'] ?? 'INGRESO');
$input['cedula'] = isset($ficha['cedula'])
    ? (string)$ficha['cedula']
    : (string)($ficha['cedula_display'] ?? '');
$input['cuenta_bancaria'] = isset($ficha['cuenta_bancaria'])
    ? (string)$ficha['cuenta_bancaria']
    : (string)($ficha['cuenta_bancaria_display'] ?? '');

$trabajosInput = $trabajos ?? ($ficha['trabajos_anteriores'] ?? []);
$hijosInput = $hijos ?? ($ficha['hijos_menores'] ?? []);
$referenciasInput = $referencias ?? ($ficha['referencias'] ?? []);
$cursosInput = $fichasCursos ?? ($ficha['cursos'] ?? []);

$input['trabajos_anteriores'] = array_values(is_array($trabajosInput) ? $trabajosInput : []);
$input['hijos_menores'] = array_values(is_array($hijosInput) ? $hijosInput : []);
$input['referencias'] = array_values(is_array($referenciasInput) ? $referenciasInput : []);
$input['cursos'] = array_values(is_array($cursosInput) ? $cursosInput : []);

$label = $correlativo !== '' ? "Editar Ficha {$correlativo}" : 'Editar Ficha';
$backUrl = $fichaId !== '' ? "/guardias/{$fichaId}" : '/guardias';
$actionUrl = $fichaId !== '' ? "/guardias/{$fichaId}/editar" : '/guardias/crear';

$formPageTitle = $label;
$formBreadcrumbParentLabel = 'Guardias';
$formBreadcrumbParentUrl = '/guardias';
$formBreadcrumbCurrent = $label;
$formKickerLabel = $label;
$formBackUrl = $backUrl;
$formBackLabel = 'Volver';
$formAction = $actionUrl;
$formCancelUrl = $backUrl;
$formCancelLabel = 'Cancelar';
$formSubmitLabel = 'Guardar Cambios';
$formSubmitIcon = 'bi bi-check2-circle';
$formShowCancel = false;
$formRequireConfirm = true;
$formConfirmTitle = 'Guardar cambios en la ficha?';
$formConfirmText = 'Se actualizaran los datos del guardia con la informacion actual del formulario.';
$formConfirmButton = 'Si, guardar cambios';
$formConfirmCancel = 'No, revisar';
$formEnableCedulaPrefill = false;

require __DIR__ . '/create.php';
