<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Ficha;

use DigiSecure\Ficha\Infrastructure\FichaEmpleado;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FichaEmpleadoTest extends TestCase
{
    #[Test]
    public function empleado_es_la_negacion_de_aspirante(): void
    {
        self::assertSame('NOT ' . FichaEmpleado::esDeAspirante('f'), FichaEmpleado::condicion('f'));
    }

    #[Test]
    public function aspirante_es_quien_no_fue_contratado_o_conserva_el_rol(): void
    {
        $sql = preg_replace('/\s+/', ' ', FichaEmpleado::esDeAspirante('f')) ?? '';

        // Registro de postulacion aun no contratado (incluye descartados).
        self::assertStringContainsString("asp_fe.usuario_id = f.created_by AND asp_fe.estado <> 'contratado'", $sql);
        // Cuenta restringida con rol 'aspirante' aunque falte el registro.
        self::assertStringContainsString("ur_fe.usuario_id = f.created_by AND r_fe.codigo = 'aspirante'", $sql);
        // Las dos condiciones se combinan con OR dentro de un mismo parentesis.
        self::assertStringStartsWith('(EXISTS', $sql);
        self::assertStringContainsString(') OR EXISTS (', $sql);
    }

    #[Test]
    public function usa_el_alias_indicado(): void
    {
        self::assertStringContainsString('fichas.created_by', FichaEmpleado::condicion('fichas'));
        self::assertStringNotContainsString('f.created_by', FichaEmpleado::condicion('fichas'));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function aliasInvalidos(): iterable
    {
        yield 'vacio' => [''];
        yield 'inyeccion' => ['f WHERE 1=1 --'];
        yield 'con punto' => ['db.f'];
        yield 'empieza con digito' => ['1f'];
    }

    #[Test]
    #[DataProvider('aliasInvalidos')]
    public function rechaza_alias_que_no_sean_identificadores(string $alias): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FichaEmpleado::condicion($alias);
    }
}
