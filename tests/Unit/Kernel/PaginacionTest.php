<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Kernel;

use DigiSecure\Kernel\Http\Paginacion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginacionTest extends TestCase
{
    #[Test]
    public function usa_el_valor_por_defecto_si_por_pagina_no_es_una_opcion(): void
    {
        $p = Paginacion::desdeValores(1, '9999', 25)->conTotal(37);

        self::assertSame(25, $p->limite());
        self::assertSame('25', $p->valor());
        self::assertNull($p->parametro(), 'el valor por defecto no se escribe en la URL');
        self::assertSame(2, $p->totalPaginas());
    }

    #[Test]
    public function calcula_rango_y_ajusta_la_pagina_fuera_de_rango(): void
    {
        $p = Paginacion::desdeValores(9, '10', 25)->conTotal(37);

        self::assertSame(4, $p->pagina());
        self::assertSame(30, $p->offset());
        self::assertSame(31, $p->desde());
        self::assertSame(37, $p->hasta());
        self::assertSame('10', $p->parametro());
    }

    #[Test]
    public function todos_muestra_todo_en_una_pagina(): void
    {
        $p = Paginacion::desdeValores(3, 'Todos', 25)->conTotal(37);

        self::assertTrue($p->esTodos());
        self::assertSame(1, $p->pagina());
        self::assertSame(1, $p->totalPaginas());
        self::assertSame(1, $p->desde());
        self::assertSame(37, $p->hasta());
        self::assertSame('todos', $p->parametro());
        self::assertSame([], $p->ventana());
    }

    #[Test]
    public function todos_respeta_el_tope_de_seguridad(): void
    {
        $p = Paginacion::desdeValores(1, 'todos', 25)->conTotal(Paginacion::MAX_TODOS + 200);

        self::assertSame(Paginacion::MAX_TODOS, $p->limite());
        self::assertSame(2, $p->totalPaginas());
    }

    #[Test]
    public function sin_registros_el_rango_es_cero(): void
    {
        $p = Paginacion::desdeValores(1, null, 25)->conTotal(0);

        self::assertSame(0, $p->desde());
        self::assertSame(0, $p->hasta());
        self::assertSame(1, $p->totalPaginas());
    }

    #[Test]
    public function ventana_compacta_con_saltos(): void
    {
        $p = Paginacion::desdeValores(6, '10', 25)->conTotal(120);

        self::assertSame([1, null, 5, 6, 7, null, 12], $p->ventana());
    }

    #[Test]
    public function el_defecto_que_no_esta_en_la_lista_se_agrega_a_las_opciones(): void
    {
        $valores = array_column(Paginacion::desdeValores(1, null, 30)->opciones(), 'valor');

        self::assertSame(['10', '25', '30', '50', '100', 'todos'], $valores);
    }
}
