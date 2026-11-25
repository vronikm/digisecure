<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Infrastructure;

/**
 * Condicion SQL unica para saber si una ficha es de un EMPLEADO (guardia) o de
 * un ASPIRANTE que sigue en reclutamiento.
 *
 * El aspirante crea su propia ficha (fichas.created_by = su usuario) en el
 * tenant de GEMESEG para que el personal la revise, pero no es guardia hasta
 * que Reclutamiento lo contrata (aspirantes.estado = 'contratado', se le quita
 * el rol 'aspirante'). Mientras tanto su ficha:
 *   - no aparece en Guardias (se gestiona desde Reclutamiento),
 *   - no se puede asignar a un puesto,
 *   - no cuenta como guardia en el Dashboard ni en los KPIs.
 *
 * Se considera ficha de aspirante si quien la creo:
 *   - tiene un registro en `aspirantes` que aun no esta 'contratado'
 *     (nuevo, aspirante, en_revision, contactado, aprobado o descartado), o
 *   - conserva el rol 'aspirante' (cuenta restringida aunque falte el registro).
 * Las fichas creadas por el personal o importadas (created_by de un usuario
 * interno o NULL) son siempre de empleados.
 *
 * Uso: "... WHERE f.tenant_id = :tid AND " . FichaEmpleado::condicion('f')
 */
final class FichaEmpleado
{
    /**
     * @param string $alias Alias de la tabla `fichas` en la consulta (definido en el codigo).
     */
    public static function condicion(string $alias = 'f'): string
    {
        return 'NOT ' . self::esDeAspirante($alias);
    }

    /**
     * Condicion inversa: la ficha es de un aspirante en proceso (o descartado).
     */
    public static function esDeAspirante(string $alias = 'f'): string
    {
        $a = self::alias($alias);

        return "(EXISTS (SELECT 1 FROM aspirantes asp_fe
                          WHERE asp_fe.usuario_id = {$a}.created_by
                            AND asp_fe.estado <> 'contratado')
                 OR EXISTS (SELECT 1 FROM usuarios_roles ur_fe
                              JOIN roles r_fe ON r_fe.id = ur_fe.role_id
                             WHERE ur_fe.usuario_id = {$a}.created_by
                               AND r_fe.codigo = 'aspirante'))";
    }

    private static function alias(string $alias): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
            throw new \InvalidArgumentException('Alias de tabla no valido.');
        }

        return $alias;
    }
}
