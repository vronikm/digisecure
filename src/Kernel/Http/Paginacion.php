<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Http;

/**
 * Paginacion de los listados: pagina actual y registros por pagina elegidos
 * por el usuario (10, 25, 50, 100 o "todos").
 *
 * Uso en un controlador:
 *   $pag = Paginacion::desdeRequest($request)->conTotal($repo->contar($filtros));
 *   $filas = $repo->listar($filtros, $pag->limite(), $pag->offset());
 *
 * "Todos" tiene un tope de seguridad (MAX_TODOS): si hay mas registros, el
 * rango lo dice ("Mostrando 1-5000 de 7000") y aparece una segunda pagina,
 * en vez de cargar sin limite.
 */
final class Paginacion
{
    public const OPCIONES = [10, 25, 50, 100];
    public const TODOS = 'todos';
    public const MAX_TODOS = 5000;

    private function __construct(
        private readonly int $pagina,
        private readonly bool $todos,
        private readonly int $porPagina,
        private readonly int $porPaginaDefecto,
        private readonly int $total,
    ) {
    }

    public static function desdeRequest(Request $request, int $porPaginaDefecto = 25): self
    {
        return self::desdeValores($request->query('page'), $request->query('por_pagina'), $porPaginaDefecto);
    }

    public static function desdeValores(mixed $pagina, mixed $porPagina, int $porPaginaDefecto = 25): self
    {
        $todos = is_string($porPagina) && strtolower(trim($porPagina)) === self::TODOS;
        $n = (int) $porPagina;
        $tamano = $todos ? self::MAX_TODOS : (in_array($n, self::OPCIONES, true) ? $n : $porPaginaDefecto);

        return new self(max(1, (int) $pagina), $todos, $tamano, $porPaginaDefecto, 0);
    }

    /** Fija el total de registros y ajusta la pagina si quedo fuera de rango. */
    public function conTotal(int $total): self
    {
        $total = max(0, $total);
        $paginas = max(1, (int) ceil($total / $this->porPagina));

        return new self(min($this->pagina, $paginas), $this->todos, $this->porPagina, $this->porPaginaDefecto, $total);
    }

    public function pagina(): int
    {
        return $this->pagina;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function limite(): int
    {
        return $this->porPagina;
    }

    public function offset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    public function totalPaginas(): int
    {
        return max(1, (int) ceil($this->total / $this->porPagina));
    }

    public function esTodos(): bool
    {
        return $this->todos;
    }

    /** Primer registro mostrado (1-based); 0 si no hay registros. */
    public function desde(): int
    {
        return $this->total > 0 ? $this->offset() + 1 : 0;
    }

    public function hasta(): int
    {
        return min($this->total, $this->pagina * $this->porPagina);
    }

    /** Valor del selector: "10", "25"... o "todos". */
    public function valor(): string
    {
        return $this->todos ? self::TODOS : (string) $this->porPagina;
    }

    public function valorDefecto(): string
    {
        return (string) $this->porPaginaDefecto;
    }

    /** Parametro por_pagina para las URL; null si es el valor por defecto. */
    public function parametro(): ?string
    {
        return $this->valor() === $this->valorDefecto() ? null : $this->valor();
    }

    /**
     * Opciones del selector "Mostrar".
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    public function opciones(): array
    {
        $numeros = self::OPCIONES;
        if (!in_array($this->porPaginaDefecto, $numeros, true)) {
            $numeros[] = $this->porPaginaDefecto;
            sort($numeros);
        }
        $opciones = array_map(static fn(int $n): array => ['valor' => (string) $n, 'etiqueta' => (string) $n], $numeros);
        $opciones[] = ['valor' => self::TODOS, 'etiqueta' => 'Todos'];

        return $opciones;
    }

    /**
     * Paginas a mostrar con ventana compacta: 1 ... (actual-1) actual (actual+1) ... ultima.
     * null marca un salto (puntos suspensivos).
     *
     * @return list<int|null>
     */
    public function ventana(): array
    {
        $ultima = $this->totalPaginas();
        if ($ultima <= 1) {
            return [];
        }
        $candidatas = array_unique(array_filter(
            [1, $this->pagina - 1, $this->pagina, $this->pagina + 1, $ultima],
            static fn(int $p): bool => $p >= 1 && $p <= $ultima,
        ));
        sort($candidatas);

        $ventana = [];
        $anterior = 0;
        foreach ($candidatas as $p) {
            if ($p - $anterior > 1) {
                $ventana[] = null;
            }
            $ventana[] = $p;
            $anterior = $p;
        }

        return $ventana;
    }
}
