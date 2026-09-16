<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Ficha\Application;

use DigiSecure\Ficha\Application\NormalizarFamiliaresFicha;
use DigiSecure\Security\Crypto\BlindIndex;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Domain\DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NormalizarFamiliaresFichaTest extends TestCase
{
    private Encryption $encryption;
    private BlindIndex $blindIndex;
    private NormalizarFamiliaresFicha $normalizar;

    protected function setUp(): void
    {
        $this->encryption = new Encryption(random_bytes(32));
        $this->blindIndex = $this->encryption->blindIndexer();
        $this->normalizar = new NormalizarFamiliaresFicha($this->encryption, $this->blindIndex);
    }

    #[Test]
    public function conyuge_guarda_cedula_cifrada_e_indice_hmac(): void
    {
        $familiares = $this->normalizar->normalizar([
            'conyuge_nombre' => 'María López',
            'conyuge_cedula' => '171003406-5',
            'conyuge_edad' => '30',
        ], 'CASADO');

        self::assertCount(1, $familiares);
        $conyuge = $familiares[0];

        self::assertSame('CONYUGE', $conyuge['parentesco']);
        self::assertSame($this->blindIndex->cedula('1710034065'), $conyuge['cedula_hash']);
        self::assertNotSame(hash('sha256', '1710034065'), $conyuge['cedula_hash']);
        self::assertSame('1710034065', $this->encryption->decrypt((string) $conyuge['cedula_cifrada']));
        self::assertSame(30, $conyuge['edad']);
    }

    #[Test]
    public function padres_no_llevan_cedula(): void
    {
        $familiares = $this->normalizar->normalizar([
            'familiar_padre_nombre' => 'Luis Pérez',
            'familiar_padre_telefono' => '0991234567',
        ], 'SOLTERO');

        self::assertCount(1, $familiares);
        self::assertSame('PADRE', $familiares[0]['parentesco']);
        self::assertNull($familiares[0]['cedula_hash']);
        self::assertNull($familiares[0]['cedula_cifrada']);
    }

    #[Test]
    public function exige_conyuge_si_esta_casado(): void
    {
        $this->expectException(DomainException::class);
        $this->normalizar->normalizar([], 'CASADO');
    }

    #[Test]
    public function rechaza_cedula_de_conyuge_invalida(): void
    {
        $this->expectException(DomainException::class);
        $this->normalizar->normalizar([
            'conyuge_nombre' => 'María López',
            'conyuge_cedula' => '1234567890',
            'conyuge_edad' => '30',
        ], 'CASADO');
    }
}
