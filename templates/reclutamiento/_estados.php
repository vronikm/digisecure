<?php
/**
 * Catalogos de presentacion de aspirantes, compartidos por reclutamiento/index.php
 * (filtro de estado) y reclutamiento/_listado.php (tabla). Uso:
 *   ['tone' => $estadoTone, 'label' => $estadoLabel, 'tipo' => $tipoLabel] = require __DIR__ . '/_estados.php';
 *
 * @return array{tone: array<string, string>, label: array<string, string>, tipo: array<string, string>}
 */
return [
    'tone' => [
        'nuevo'       => 'tone-warning',
        'aspirante'   => 'tone-info',
        'en_revision' => 'tone-info',
        'contactado'  => 'tone-info',
        'aprobado'    => 'tone-info',
        'descartado'  => 'tone-muted',
        'contratado'  => 'tone-success',
    ],
    'label' => [
        'nuevo'       => 'Nuevo',
        'aspirante'   => 'Aspirante',
        'en_revision' => 'En revisión',
        'contactado'  => 'Contactado',
        'aprobado'    => 'Aprobado',
        'descartado'  => 'Descartado',
        'contratado'  => 'Contratado',
    ],
    'tipo' => ['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'],
];
