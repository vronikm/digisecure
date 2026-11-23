<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Infrastructure\Persistence;

use DigiSecure\Infrastructure\Persistence\BusquedaTexto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BusquedaTextoTest extends TestCase
{
    #[Test]
    public function sin_texto_no_hay_condicion(): void
    {
        self::assertSame([null, []], BusquedaTexto::condicion('   ', ['a.nombre']));
        self::assertSame([null, []], BusquedaTexto::condicion(null, ['a.nombre']));
    }

    #[Test]
    public function cada_palabra_debe_aparecer_en_alguna_columna(): void
    {
        [$sql, $params] = BusquedaTexto::condicion('paladines  nixon', ['f.apellidos', 'f.nombres'], 'q');

        self::assertSame(
            '((f.apellidos LIKE :q0_0 OR f.nombres LIKE :q0_1) AND (f.apellidos LIKE :q1_0 OR f.nombres LIKE :q1_1))',
            $sql,
        );
        self::assertSame(['q0_0' => '%paladines%', 'q0_1' => '%paladines%', 'q1_0' => '%nixon%', 'q1_1' => '%nixon%'], $params);
    }

    #[Test]
    public function los_comodines_del_usuario_se_buscan_literalmente(): void
    {
        [, $params] = BusquedaTexto::condicion('50%_off', ['c.nombre'], 'q');

        self::assertSame('%50\\%\\_off%', $params['q0_0']);
    }

    #[Test]
    public function limita_palabras_y_longitud(): void
    {
        self::assertCount(BusquedaTexto::MAX_TERMINOS, BusquedaTexto::terminos('a b c d e f g h i'));
        self::assertSame([str_repeat('x', BusquedaTexto::MAX_LONGITUD)], BusquedaTexto::terminos(str_repeat('x', 500)));
    }
}
