<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Tenant\Domain;

use DigiSecure\Tenant\Domain\ValidadorRuc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests del validador de RUC Ecuador.
 *
 * Usa RUCs reales válidos del SRI (públicos y verificables):
 *  - 1790011674001  → Banco Pichincha (sociedad privada)
 *  - 0992301066001  → Holcim Ecuador (sociedad privada)
 *  - 1760000150001  → pública sintética (17=Pichincha, 6=pública, verif=5)
 *  - 0102345675001  → persona natural de prueba con dígito verificador calculado
 */
final class ValidadorRucTest extends TestCase
{
    #[Test]
    public function sociedad_privada_banco_pichincha_es_valida(): void
    {
        self::assertTrue(ValidadorRuc::esValido('1790011674001'));
        self::assertSame(ValidadorRuc::TIPO_SOCIEDAD_PRIVADA, ValidadorRuc::tipo('1790011674001'));
    }

    #[Test]
    public function sociedad_publica_sintetica_es_valida(): void
    {
        self::assertTrue(ValidadorRuc::esValido('1760000150001'));
        self::assertSame(ValidadorRuc::TIPO_SOCIEDAD_PUBLICA, ValidadorRuc::tipo('1760000150001'));
    }

    #[Test]
    #[DataProvider('rucsInvalidos')]
    public function rucs_invalidos_se_rechazan(string $ruc, string $motivo): void
    {
        self::assertFalse(
            ValidadorRuc::esValido($ruc),
            "RUC '{$ruc}' NO debería ser válido ({$motivo})",
        );
        self::assertNull(ValidadorRuc::tipo($ruc));
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function rucsInvalidos(): array
    {
        return [
            'vacio' => ['', 'cadena vacía'],
            'menos_13_digitos' => ['179001167400', '12 dígitos'],
            'mas_13_digitos' => ['17900116740011', '14 dígitos'],
            'con_letras' => ['179001167400A', 'contiene letras'],
            'provincia_invalida_99' => ['9990011674001', 'provincia 99 no existe'],
            'provincia_cero' => ['0090011674001', 'provincia 00 no existe'],
            'establecimiento_000' => ['1790011674000', 'establecimiento 000'],
            'digito_verificador_malo_privada' => ['1790011675001', 'verificador incorrecto'],
            'digito_verificador_malo_publica' => ['1760000160001', 'verificador incorrecto'],
            'tercer_digito_invalido_7' => ['1770011674001', 'tercer dígito 7 no se soporta'],
            'tercer_digito_invalido_8' => ['1780011674001', 'tercer dígito 8 no se soporta'],
        ];
    }
}
