<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Domain;

/**
 * Validador de RUC (Registro Único de Contribuyentes) de Ecuador.
 *
 * Algoritmo según SRI Ecuador:
 *  - 13 dígitos numéricos
 *  - Posiciones 1-2: código de provincia (01-24, más 30 para extranjeros)
 *  - Posición 3: tipo de contribuyente
 *      0-5 → Persona natural
 *      6   → Sociedad pública
 *      9   → Sociedad privada
 *  - Posiciones 11-13: secuencia + "001" (establecimiento)
 *
 * Aplica dígito verificador Módulo 10 (personas naturales y sociedades públicas)
 * o Módulo 11 (sociedades privadas).
 *
 * Para Fase 12 aceptamos: persona natural (0-5), sociedad pública (6), privada (9).
 */
final class ValidadorRuc
{
    public const TIPO_PERSONA_NATURAL = 'persona_natural';
    public const TIPO_SOCIEDAD_PUBLICA = 'sociedad_publica';
    public const TIPO_SOCIEDAD_PRIVADA = 'sociedad_privada';

    /**
     * Valida y retorna el tipo de contribuyente, o null si es inválido.
     */
    public static function tipo(string $ruc): ?string
    {
        if (!self::formatoValido($ruc)) {
            return null;
        }

        $tercerDigito = (int) $ruc[2];

        return match (true) {
            $tercerDigito <= 5 => self::validarPersonaNatural($ruc) ? self::TIPO_PERSONA_NATURAL : null,
            $tercerDigito === 6 => self::validarSociedadPublica($ruc) ? self::TIPO_SOCIEDAD_PUBLICA : null,
            $tercerDigito === 9 => self::validarSociedadPrivada($ruc) ? self::TIPO_SOCIEDAD_PRIVADA : null,
            default => null,
        };
    }

    public static function esValido(string $ruc): bool
    {
        return self::tipo($ruc) !== null;
    }

    /**
     * Valida una cedula ecuatoriana (10 digitos) con el algoritmo Modulo 10
     * del Registro Civil. Aplica los mismos pasos que la persona natural
     * pero sin requerir el sufijo "001" del establecimiento.
     */
    public static function esCedulaValida(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }

        $provincia = (int) substr($cedula, 0, 2);
        if (($provincia < 1 || $provincia > 24) && $provincia !== 30) {
            return false;
        }

        $tercer = (int) $cedula[2];
        if ($tercer >= 6) {
            return false;
        }

        $coef = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $producto = (int) $cedula[$i] * $coef[$i];
            if ($producto >= 10) {
                $producto -= 9;
            }
            $suma += $producto;
        }

        $verificador = (10 - ($suma % 10)) % 10;
        return $verificador === (int) $cedula[9];
    }

    private static function formatoValido(string $ruc): bool
    {
        if (!preg_match('/^\d{13}$/', $ruc)) {
            return false;
        }

        $provincia = (int) substr($ruc, 0, 2);
        // 01-24 son provincias; 30 es para contribuyentes extranjeros.
        if (($provincia < 1 || $provincia > 24) && $provincia !== 30) {
            return false;
        }

        // Los últimos 3 dígitos deben ser > 0 (establecimiento)
        $establecimiento = (int) substr($ruc, 10, 3);
        if ($establecimiento <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Persona natural: aplica algoritmo Módulo 10 sobre los 9 primeros dígitos
     * (los 10 primeros = cédula; los 3 últimos = establecimiento "001").
     */
    private static function validarPersonaNatural(string $ruc): bool
    {
        $coef = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $producto = (int) $ruc[$i] * $coef[$i];
            if ($producto >= 10) {
                $producto -= 9;
            }
            $suma += $producto;
        }

        $verificador = (10 - ($suma % 10)) % 10;
        return $verificador === (int) $ruc[9];
    }

    /**
     * Sociedad pública: Módulo 11 sobre primeros 8 dígitos, verificador en pos 9.
     */
    private static function validarSociedadPublica(string $ruc): bool
    {
        $coef = [3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 8; $i++) {
            $suma += (int) $ruc[$i] * $coef[$i];
        }

        $modulo = $suma % 11;
        $verificador = $modulo === 0 ? 0 : 11 - $modulo;

        return $verificador === (int) $ruc[8];
    }

    /**
     * Sociedad privada: Módulo 11 sobre primeros 9 dígitos, verificador en pos 10.
     */
    private static function validarSociedadPrivada(string $ruc): bool
    {
        $coef = [4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $suma += (int) $ruc[$i] * $coef[$i];
        }

        $modulo = $suma % 11;
        $verificador = $modulo === 0 ? 0 : 11 - $modulo;

        return $verificador === (int) $ruc[9];
    }
}
