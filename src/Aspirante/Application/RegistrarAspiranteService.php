<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DateTimeImmutable;
use DigiSecure\Aspirante\Domain\TipoDocumento;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\ValidadorRuc;

/**
 * Registra un aspirante (postulante a guardia) desde el modal publico de login.
 *
 * Responsabilidad: validar y normalizar la entrada del usuario, aplicar
 * rate-limit anti-spam, garantizar unicidad (documento + correo) y persistir.
 * Toda regla de negocio vive aqui; el controlador solo traduce HTTP.
 *
 * Las validaciones siguen el criterio "fail fast con mensaje accionable":
 * cada DomainException lleva un texto que la vista muestra tal cual al usuario.
 */
final class RegistrarAspiranteService
{
    private const RATE_LIMIT_VENTANA_MIN = 60;
    private const RATE_LIMIT_MAX_POR_IP = 8;

    /** Version del documento de politicas aceptado (trazabilidad LOPDP). */
    public const POLITICAS_VERSION = 'PoliticaPrivacidad_TDP-2026';

    private const ESTATURA_MIN_CM = 120;
    private const ESTATURA_MAX_CM = 250;
    private const EDAD_MIN = 18;
    private const EDAD_MAX = 70;
    private const PASSWORD_MIN = 8;
    private const TOKEN_BYTES = 32;
    private const TOKEN_EXPIRACION_HORAS = 48;

    /**
     * @param list<string> $emailBlacklist Dominios de email bloqueados (en minúsculas).
     */
    public function __construct(
        private readonly AspiranteRepository $repo,
        private readonly Hasher $hasher,
        private readonly array $emailBlacklist = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: string, nombre_completo: string, correo: string}
     */
    public function execute(array $input, string $ip, string $userAgent): array
    {
        // 1. Rate limit por IP (antes de tocar la base con lecturas costosas).
        if ($this->repo->contarIntentosRecientes($ip, self::RATE_LIMIT_VENTANA_MIN) >= self::RATE_LIMIT_MAX_POR_IP) {
            throw new DomainException('Has realizado demasiados registros desde esta conexión. Intenta nuevamente en una hora.');
        }

        // 2. Normalizar.
        $tipoDoc = TipoDocumento::tryFrom(strtolower(trim((string) ($input['tipo_documento'] ?? ''))));
        if ($tipoDoc === null) {
            throw new DomainException('Selecciona un tipo de documento válido.');
        }

        $documento  = $this->normalizarDocumento($tipoDoc, (string) ($input['documento'] ?? ''));
        $apellidos  = $this->limpiarTexto((string) ($input['apellidos'] ?? ''));
        $nombres    = $this->limpiarTexto((string) ($input['nombres'] ?? ''));
        $celular    = $this->normalizarCelular((string) ($input['celular'] ?? ''));
        $correo     = trim((string) ($input['correo'] ?? ''));
        $correoNorm = mb_strtolower($correo, 'UTF-8');
        $estaturaCm = $this->normalizarEstatura((string) ($input['estatura'] ?? ''));
        $fechaNac   = trim((string) ($input['fecha_nacimiento'] ?? ''));
        $password        = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirmacion'] ?? '');
        $acepta     = !empty($input['acepta_politicas']);

        // 3. Validar reglas de negocio.
        $this->validar($tipoDoc, $documento, $apellidos, $nombres, $celular, $correoNorm, $estaturaCm, $fechaNac, $acepta);
        $this->validarPassword($password, $passwordConfirm);
        $this->validarDominioPermitido($correoNorm);

        // 4. Unicidad: documento y correo (en aspirantes y en usuarios del sistema).
        if ($this->repo->existeDocumento($documento)) {
            throw new DomainException('Ya existe una postulación registrada con este documento.');
        }
        if ($this->repo->existeCorreo($correoNorm) || $this->repo->existeCorreoEnUsuarios($correoNorm)) {
            throw new DomainException('Este correo ya está registrado. Si ya tienes una cuenta, inicia sesión.');
        }

        $tenantId = $this->repo->tenantGemeseg();
        if ($tenantId === null) {
            throw new DomainException('No se pudo completar tu registro. El tenant GEMESEG no esta configurado.');
        }

        // 5. Token de verificación de email (single-use, solo el hash se persiste).
        $id         = IdGenerator::ulid();
        $now        = Clock::now();
        $nowStr     = $now->format('Y-m-d H:i:s.u');
        $tokenPlano = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash  = hash('sha256', $tokenPlano);
        $expira     = $now->modify('+' . self::TOKEN_EXPIRACION_HORAS . ' hours');
        $expiraStr  = $expira->format('Y-m-d H:i:s.u');

        // 6. Persistir en estado pendiente (email_verificado = 0 por defecto).
        $this->repo->crear([
            'id'                 => $id,
            'tenant_id'          => $tenantId,
            'tipo_documento'     => $tipoDoc->value,
            'documento'          => $documento,
            'apellidos'          => $apellidos,
            'nombres'            => $nombres,
            'celular'            => $celular,
            'correo'             => $correo,
            'correo_normalizado' => $correoNorm,
            'password_hash'      => $this->hasher->hash($password),
            'estatura_cm'        => $estaturaCm,
            'fecha_nacimiento'   => $fechaNac,
            'origen'             => 'login_publico',
            'acepta_politicas'   => true,
            'politicas_version'  => self::POLITICAS_VERSION,
            'politicas_aceptadas_at' => $nowStr,
            'politicas_aceptadas_desde' => 'modal_postula_gemeseg',
            'politicas_aceptadas_ruta' => '/login',
            'politicas_aceptadas_ip' => $ip !== '' ? $ip : null,
            'politicas_aceptadas_user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
            'verificacion_token_hash'      => $tokenHash,
            'verificacion_token_expira_at' => $expiraStr,
            'ip'                 => $ip !== '' ? $ip : null,
            'user_agent'         => $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
            'created_at'         => $nowStr,
        ]);

        return [
            'id'                     => $id,
            'nombre_completo'        => trim($nombres . ' ' . $apellidos),
            'correo'                 => $correo,
            'verificacion_token'     => $tokenPlano,
            'verificacion_expira_at' => $expiraStr,
        ];
    }

    /**
     * Rechaza correos cuyo dominio esté en la lista negra configurada.
     */
    private function validarDominioPermitido(string $correoNorm): void
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

    private function validar(
        TipoDocumento $tipoDoc,
        string $documento,
        string $apellidos,
        string $nombres,
        string $celular,
        string $correoNorm,
        int $estaturaCm,
        string $fechaNac,
        bool $acepta,
    ): void {
        $this->validarDocumento($tipoDoc, $documento);

        if (!$this->nombreValido($apellidos)) {
            throw new DomainException('Ingresa apellidos válidos (solo letras, entre 2 y 120 caracteres).');
        }
        if (!$this->nombreValido($nombres)) {
            throw new DomainException('Ingresa nombres válidos (solo letras, entre 2 y 120 caracteres).');
        }

        if (preg_match('/^09\d{8}$/', $celular) !== 1) {
            throw new DomainException('Ingresa un número de celular ecuatoriano válido (10 dígitos, ej. 0991234567).');
        }

        if ($correoNorm === '' || filter_var($correoNorm, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('El correo electrónico no tiene un formato válido.');
        }
        if (mb_strlen($correoNorm) > 190) {
            throw new DomainException('El correo no puede superar los 190 caracteres.');
        }

        if ($estaturaCm < self::ESTATURA_MIN_CM || $estaturaCm > self::ESTATURA_MAX_CM) {
            throw new DomainException('Ingresa una estatura válida en metros (entre 1.20 y 2.50).');
        }

        $this->validarFechaNacimiento($fechaNac);

        if (!$acepta) {
            throw new DomainException('Debes aceptar las Políticas de Privacidad y Tratamiento de Datos Personales para registrarte.');
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

    /**
     * Politica de contrasena: minimo 8 caracteres, con mayuscula, minuscula y numero.
     */
    private function validarPassword(string $password, string $confirmacion): void
    {
        if ($password === '') {
            throw new DomainException('Ingresa una contraseña.');
        }
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
        }
        if (preg_match('/[A-ZÁÉÍÓÚÑ]/u', $password) !== 1) {
            throw new DomainException('La contraseña debe incluir al menos una letra mayúscula.');
        }
        if (preg_match('/[a-záéíóúñ]/u', $password) !== 1) {
            throw new DomainException('La contraseña debe incluir al menos una letra minúscula.');
        }
        if (preg_match('/\d/', $password) !== 1) {
            throw new DomainException('La contraseña debe incluir al menos un número.');
        }
        if ($password !== $confirmacion) {
            throw new DomainException('Las contraseñas no coinciden.');
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
        $raw = str_replace(',', '.', trim($valor));
        if ($raw === '' || !is_numeric($raw)) {
            return 0;
        }

        $num = (float) $raw;

        return $num < 3.0 ? (int) round($num * 100) : (int) round($num);
    }

    private function limpiarTexto(string $valor): string
    {
        $valor = trim($valor);
        return (string) preg_replace('/\s+/u', ' ', $valor);
    }
}
