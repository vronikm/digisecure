<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Security\Crypto;

use DigiSecure\Security\Crypto\BlindIndex;
use DigiSecure\Security\Crypto\Encryption;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlindIndexTest extends TestCase
{
    private const CEDULA = '1710034065';
    private const KEY_HEX = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    #[Test]
    public function es_determinista_y_hex_de_64(): void
    {
        $index = Encryption::fromHexKey(self::KEY_HEX)->blindIndexer();

        $a = $index->cedula(self::CEDULA);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
        self::assertSame($a, $index->cedula(self::CEDULA));
        self::assertSame($a, Encryption::fromHexKey(self::KEY_HEX)->blindIndexer()->cedula(self::CEDULA));
    }

    #[Test]
    public function no_coincide_con_sha256_plano(): void
    {
        $index = Encryption::fromHexKey(self::KEY_HEX)->blindIndexer();

        self::assertNotSame(hash('sha256', self::CEDULA), $index->cedula(self::CEDULA));
    }

    #[Test]
    public function depende_de_la_clave(): void
    {
        $otraClave = str_repeat('ab', 32);

        self::assertNotSame(
            Encryption::fromHexKey(self::KEY_HEX)->blindIndexer()->cedula(self::CEDULA),
            Encryption::fromHexKey($otraClave)->blindIndexer()->cedula(self::CEDULA),
        );
    }

    #[Test]
    public function usa_subclave_derivada_y_no_la_clave_maestra(): void
    {
        $maestra = (string) hex2bin(self::KEY_HEX);
        $conMaestra = new BlindIndex($maestra);

        self::assertNotSame(
            $conMaestra->cedula(self::CEDULA),
            Encryption::fromHexKey(self::KEY_HEX)->blindIndexer()->cedula(self::CEDULA),
        );
    }

    #[Test]
    public function normaliza_separadores(): void
    {
        $index = new BlindIndex(random_bytes(32));

        self::assertSame($index->cedula(self::CEDULA), $index->cedula(' 171003406-5 '));
    }

    #[Test]
    public function distingue_cedulas_distintas(): void
    {
        $index = new BlindIndex(random_bytes(32));

        self::assertNotSame($index->cedula(self::CEDULA), $index->cedula('0102030405'));
    }

    #[Test]
    public function rechaza_cedula_vacia(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BlindIndex(random_bytes(32)))->cedula(' - ');
    }

    #[Test]
    public function rechaza_clave_de_longitud_incorrecta(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BlindIndex(random_bytes(16));
    }
}
