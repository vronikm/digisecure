<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Domain\ValueObject;

use DigiSecure\Shared\Domain\DomainException;

/**
 * Value Object: Cédula de identidad ecuatoriana.
 *
 * Validación completa según algoritmo oficial:
 *   1. Exactamente 10 dígitos numéricos
 *   2. Provincia (dígitos 1-2): entre 01 y 24
 *   3. Tercer dígito: < 6 (persona natural)
 *   4. Dígito verificador (posición 10): módulo 10 con coeficientes [2,1,2,1,2,1,2,1,2]
 *
 * Inmutable. Se crea validada o falla.
 */
final readonly class Cedula
{
    private function __construct(
        public string $valor,
    ) {
    }

    public static function fromString(string $cedula): self
    {
        $cedula = trim($cedula);

        if (!preg_match('/^\d{10}$/', $cedula)) {
            throw new DomainException('La cédula debe tener exactamente 10 dígitos numéricos.');
        }

        $provincia = (int)substr($cedula, 0, 2);
        if ($provincia < 1 || $provincia > 24) {
            throw new DomainException("Cédula inválida: código de provincia '{$provincia}' fuera de rango (01-24).");
        }

        $tercerDigito = (int)$cedula[2];
        if ($tercerDigito >= 6) {
            throw new DomainException('Cédula inválida: el tercer dígito debe ser menor a 6 para personas naturales.');
        }

        if (!self::validarDigitoVerificador($cedula)) {
            throw new DomainException('Cédula inválida: el dígito verificador no coincide.');
        }

        return new self($cedula);
    }

    /**
     * Algoritmo módulo 10 con coeficientes para cédula ecuatoriana.
     *
     * Coeficientes: [2, 1, 2, 1, 2, 1, 2, 1, 2]
     * Se aplican a los primeros 9 dígitos. Si el producto > 9, se resta 9.
     * La suma de los productos debe completar a la decena superior
     * y coincidir con el dígito 10.
     */
    private static function validarDigitoVerificador(string $cedula): bool
    {
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $producto = (int)$cedula[$i] * $coeficientes[$i];
            if ($producto > 9) {
                $producto -= 9;
            }
            $suma += $producto;
        }

        $residuo = $suma % 10;
        $digitoEsperado = $residuo === 0 ? 0 : 10 - $residuo;

        return $digitoEsperado === (int)$cedula[9];
    }

    public function __toString(): string
    {
        return $this->valor;
    }

    public function equals(self $other): bool
    {
        return $this->valor === $other->valor;
    }

    /**
     * Representación enmascarada para display (ej: ****1234).
     */
    public function masked(): string
    {
        return '******' . substr($this->valor, -4);
    }
}
