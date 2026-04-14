<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Ficha\Domain\ValueObject;

use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Shared\Domain\DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests del Value Object Cedula (algoritmo módulo 10 Ecuador).
 */
final class CedulaTest extends TestCase
{
    // ----- Cédulas válidas conocidas -----

    #[Test]
    #[DataProvider('cedulasValidas')]
    public function acepta_cedulas_validas(string $cedula): void
    {
        $vo = Cedula::fromString($cedula);
        self::assertSame($cedula, $vo->valor);
        self::assertSame($cedula, (string) $vo);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cedulasValidas(): iterable
    {
        // Cédulas verificadas con el algoritmo módulo 10 ecuatoriano
        yield 'Pichincha'    => ['1710034065'];
        yield 'Guayas'       => ['0910000017'];
        yield 'Azuay'        => ['0102345675'];
        yield 'Manabí'       => ['1311814477'];
        yield 'Tungurahua'   => ['1803405677'];
    }

    // ----- Formato incorrecto -----

    #[Test]
    public function rechaza_menos_de_10_digitos(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('10 dígitos');
        Cedula::fromString('171003406');
    }

    #[Test]
    public function rechaza_mas_de_10_digitos(): void
    {
        $this->expectException(DomainException::class);
        Cedula::fromString('17100340651');
    }

    #[Test]
    public function rechaza_letras(): void
    {
        $this->expectException(DomainException::class);
        Cedula::fromString('17100340AB');
    }

    #[Test]
    public function rechaza_cadena_vacia(): void
    {
        $this->expectException(DomainException::class);
        Cedula::fromString('');
    }

    // ----- Provincia inválida -----

    #[Test]
    public function rechaza_provincia_00(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('provincia');
        Cedula::fromString('0010034065');
    }

    #[Test]
    public function rechaza_provincia_25(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('provincia');
        Cedula::fromString('2510034065');
    }

    // ----- Tercer dígito >= 6 (no persona natural) -----

    #[Test]
    public function rechaza_tercer_digito_6(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tercer dígito');
        Cedula::fromString('1760034065');
    }

    // ----- Dígito verificador incorrecto -----

    #[Test]
    public function rechaza_digito_verificador_incorrecto(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('dígito verificador');
        // 1710034065 es válida → 1710034069 es inválida
        Cedula::fromString('1710034069');
    }

    // ----- Comportamiento -----

    #[Test]
    public function trim_espacios(): void
    {
        $vo = Cedula::fromString('  1710034065  ');
        self::assertSame('1710034065', $vo->valor);
    }

    #[Test]
    public function hash_es_sha256(): void
    {
        $vo = Cedula::fromString('1710034065');
        self::assertSame(hash('sha256', '1710034065'), $vo->hash());
        self::assertSame(64, strlen($vo->hash()));
    }

    #[Test]
    public function masked_muestra_ultimos_4(): void
    {
        $vo = Cedula::fromString('1710034065');
        self::assertSame('******4065', $vo->masked());
    }

    #[Test]
    public function equals_compara_por_valor(): void
    {
        $a = Cedula::fromString('1710034065');
        $b = Cedula::fromString('1710034065');
        $c = Cedula::fromString('0910000017');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
