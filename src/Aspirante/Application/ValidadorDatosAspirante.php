<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DateTimeImmutable;
use DigiSecure\Aspirante\Domain\TipoDocumento;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\ValidadorRuc;

/**
 * Normaliza y valida los datos de identificacion y contacto de un aspirante.
 *
 * Lo comparten el registro publico (RegistrarAspiranteService) y la
 * correccion desde reclutamiento (ActualizarAspiranteService), para que un
 * dato corregido cumpla exactamente las mismas reglas que uno registrado.
 *
 * Cada DomainException lleva un texto que la vista muestra tal cual.
 */
final class ValidadorDatosAspirante
{
    private const ESTATURA_MIN_CM = 120;
    private const ESTATURA_MAX_CM = 250;
    private const EDAD_MIN = 18;
    private const EDAD_MAX = 70;
    private const CORREO_MAX = 190;

    /**
     * @param list<string> $emailBlacklist Dominios de email bloqueados (en minúsculas).
     */
    public function __construct(
        private readonly array $emailBlacklist = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{tipo_documento: TipoDocumento, documento: string, apellidos: string, nombres: string,
     *     celular: string, correo: string, correo_normalizado: string, estatura_cm: int, fecha_nacimiento: string}
     */
    public function normalizar(array $input): array
    {
        $tipoDoc = TipoDocumento::tryFrom(strtolower($this->texto($input['tipo_documento'] ?? null)));
        if ($tipoDoc === null) {
            throw new DomainException('Selecciona un tipo de documento válido.');
        }

        $correo = $this->texto($input['correo'] ?? null);

        return [
            'tipo_documento'     => $tipoDoc,
            'documento'          => $this->normalizarDocumento($tipoDoc, $this->texto($input['documento'] ?? null)),
            'apellidos'          => $this->limpiarNombre($this->texto($input['apellidos'] ?? null)),
            'nombres'            => $this->limpiarNombre($this->texto($input['nombres'] ?? null)),
            'celular'            => $this->normalizarCelular($this->texto($input['celular'] ?? null)),
            'correo'             => $correo,
            'correo_normalizado' => mb_strtolower($correo, 'UTF-8'),
            'estatura_cm'        => $this->normalizarEstatura($this->texto($input['estatura'] ?? null)),
            'fecha_nacimiento'   => $this->texto($input['fecha_nacimiento'] ?? null),
        ];
    }

    /**
     * @param array{tipo_documento: TipoDocumento, documento: string, apellidos: string, nombres: string,
     *     celular: string, correo: string, correo_normalizado: string, estatura_cm: int, fecha_nacimiento: string} $datos
     */
    public function validar(array $datos): void
    {
        $this->validarDocumento($datos['tipo_documento'], $datos['documento']);

        if (!$this->nombreValido($datos['apellidos'])) {
            throw new DomainException('Ingresa apellidos válidos (solo letras, entre 2 y 120 caracteres).');
        }
        if (!$this->nombreValido($datos['nombres'])) {
            throw new DomainException('Ingresa nombres válidos (solo letras, entre 2 y 120 caracteres).');
        }

        if (preg_match('/^09\d{8}$/', $datos['celular']) !== 1) {
            throw new DomainException('Ingresa un número de celular ecuatoriano válido (10 dígitos, ej. 0991234567).');
        }

        $correoNorm = $datos['correo_normalizado'];
        if ($correoNorm === '' || filter_var($correoNorm, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('El correo electrónico no tiene un formato válido.');
        }
        if (mb_strlen($correoNorm) > self::CORREO_MAX) {
            throw new DomainException('El correo no puede superar los 190 caracteres.');
        }

        if ($datos['estatura_cm'] < self::ESTATURA_MIN_CM || $datos['estatura_cm'] > self::ESTATURA_MAX_CM) {
            throw new DomainException('Ingresa una estatura válida en metros (entre 1.20 y 2.50).');
        }

        $this->validarFechaNacimiento($datos['fecha_nacimiento']);
    }

    /**
     * Rechaza correos cuyo dominio esté en la lista negra configurada.
     */
    public function validarDominioPermitido(string $correoNorm): void
    {
        if ($this->emailBlacklist === []) {
            return;
        }

        $at = strrpos($correoNorm, '@');
        $dominio = $at !== false ? substr($correoNorm, $at + 1) : '';

        if ($dominio !== '' && in_array($dominio, $this->emailBlacklist, true)) {
            throw new DomainException('No se permiten registros con este dominio de correo electrónico.');
        }
    }

    private function validarDocumento(TipoDocumento $tipoDoc, string $documento): void
    {
        $ok = match ($tipoDoc) {
            TipoDocumento::CEDULA    => ValidadorRuc::esCedulaValida($documento),
            TipoDocumento::RUC       => ValidadorRuc::esValido($documento),
            TipoDocumento::PASAPORTE => preg_match('/^[A-Z0-9]{5,20}$/', $documento) === 1,
        };

        if ($ok) {
            return;
        }

        throw new DomainException(match ($tipoDoc) {
            TipoDocumento::CEDULA    => 'La cédula ingresada no es válida (10 dígitos según el Registro Civil).',
            TipoDocumento::RUC       => 'El RUC ingresado no es válido (13 dígitos según el formato del SRI).',
            TipoDocumento::PASAPORTE => 'El pasaporte ingresado no es válido (entre 5 y 20 caracteres alfanuméricos).',
        });
    }

    private function validarFechaNacimiento(string $fechaNac): void
    {
        $nac = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaNac);
        if ($nac === false || $nac->format('Y-m-d') !== $fechaNac) {
            throw new DomainException('Ingresa una fecha de nacimiento válida.');
        }

        $hoy = Clock::now()->setTime(0, 0);
        if ($nac > $hoy) {
            throw new DomainException('La fecha de nacimiento no puede estar en el futuro.');
        }

        $edad = (int) $nac->diff($hoy)->y;
        if ($edad < self::EDAD_MIN) {
            throw new DomainException('Debes ser mayor de edad (18 años) para postular.');
        }
        if ($edad > self::EDAD_MAX) {
            throw new DomainException('La fecha de nacimiento ingresada no es válida para una postulación.');
        }
    }

    private function nombreValido(string $valor): bool
    {
        return preg_match("/^\p{L}[\p{L}\s'.\-]{1,119}$/u", $valor) === 1;
    }

    /**
     * Cedula/RUC: solo digitos. Pasaporte: alfanumerico en mayusculas.
     */
    private function normalizarDocumento(TipoDocumento $tipoDoc, string $valor): string
    {
        if ($tipoDoc === TipoDocumento::PASAPORTE) {
            return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $valor));
        }

        return (string) preg_replace('/\D/', '', $valor);
    }

    /**
     * Acepta 0991234567, +593991234567 o 593991234567 y normaliza a 09XXXXXXXX.
     */
    private function normalizarCelular(string $valor): string
    {
        $digitos = (string) preg_replace('/\D/', '', $valor);

        if (str_starts_with($digitos, '593')) {
            $digitos = '0' . substr($digitos, 3);
        }

        return $digitos;
    }

    /**
     * Acepta metros (1.75) o centimetros (175) y devuelve siempre centimetros.
     * Un valor no numerico devuelve 0, que la validacion rechaza despues.
     */
    private function normalizarEstatura(string $valor): int
    {
        $raw = str_replace(',', '.', $valor);
        if ($raw === '' || !is_numeric($raw)) {
            return 0;
        }

        $num = (float) $raw;

        return $num < 3.0 ? (int) round($num * 100) : (int) round($num);
    }

    private function limpiarNombre(string $valor): string
    {
        return (string) preg_replace('/\s+/u', ' ', $valor);
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }
}
