<?php

declare(strict_types=1);

namespace DigiSecure\Cliente\Domain;

/**
 * Catálogos del módulo de clientes.
 */
final class CatalogoCliente
{
    /** Sectores económicos disponibles para clasificar a un cliente. */
    public const SECTORES = [
        'Banca',
        'Industria',
        'Retail',
        'Deportivo',
        'Financiera',
        'Educación',
    ];
}
