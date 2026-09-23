<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Infrastructure;

/**
 * Qué consume cupo del plan. Un solo lugar para el cálculo de límites
 * (CalcularUsoService) y las vistas del superadmin.
 *
 * - Usuario: activo y con al menos un rol operativo. Las cuentas restringidas
 *   de aspirantes (rol 'aspirante') y los empleados sin rol no ocupan cupo.
 * - Guardia: persona distinta (por cedula_hash) con ficha vigente: no anulada,
 *   sin baja y que no sea de un aspirante aún no contratado. Reingresos y
 *   versiones de ficha de la misma persona cuentan una sola vez.
 *
 * Los alias de subconsulta llevan sufijo propio para poder anidarse en otras
 * consultas sin colisionar.
 */
final class CriteriosCupo
{
    /**
     * Predicado SQL sobre la tabla `usuarios` con el alias dado.
     */
    public static function usuario(string $alias): string
    {
        return "{$alias}.estado = 'activo'
            AND EXISTS (
                SELECT 1 FROM usuarios_roles ur_cupo
                  JOIN roles r_cupo ON r_cupo.id = ur_cupo.role_id
                 WHERE ur_cupo.usuario_id = {$alias}.id
                   AND r_cupo.codigo <> 'aspirante'
            )";
    }

    /**
     * Predicado SQL sobre la tabla `fichas` con el alias dado.
     */
    public static function guardia(string $alias): string
    {
        return "{$alias}.estado <> 'ANULADA'
            AND {$alias}.fecha_baja IS NULL
            AND NOT EXISTS (
                SELECT 1 FROM usuarios_roles ur_asp
                  JOIN roles r_asp ON r_asp.id = ur_asp.role_id
                 WHERE ur_asp.usuario_id = {$alias}.created_by
                   AND r_asp.codigo = 'aspirante'
            )";
    }

    /**
     * Expresión para COUNT(DISTINCT ...): una persona por cédula.
     */
    public static function personaGuardia(string $alias): string
    {
        return "COALESCE(NULLIF({$alias}.cedula_hash, ''), {$alias}.id)";
    }
}
