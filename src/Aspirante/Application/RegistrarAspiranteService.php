<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;

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

    private const PASSWORD_MIN = 8;
    private const TOKEN_BYTES = 32;
    private const TOKEN_EXPIRACION_HORAS = 48;

    private readonly ValidadorDatosAspirante $validador;

    /**
     * @param list<string> $emailBlacklist Dominios de email bloqueados (en minúsculas).
     */
    public function __construct(
        private readonly AspiranteRepository $repo,
        private readonly Hasher $hasher,
        array $emailBlacklist = [],
    ) {
        $this->validador = new ValidadorDatosAspirante($emailBlacklist);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: string, nombre_completo: string, correo: string, verificacion_token: string, verificacion_expira_at: string}
     */
    public function execute(array $input, string $ip, string $userAgent): array
    {
        // 1. Rate limit por IP (antes de tocar la base con lecturas costosas).
        if ($this->repo->contarIntentosRecientes($ip, self::RATE_LIMIT_VENTANA_MIN) >= self::RATE_LIMIT_MAX_POR_IP) {
            throw new DomainException('Has realizado demasiados registros desde esta conexión. Intenta nuevamente en una hora.');
        }

        // 2. Normalizar y validar reglas de negocio (compartidas con la
        //    corrección desde reclutamiento).
        $datos = $this->validador->normalizar($input);
        $this->validador->validar($datos);
        if (empty($input['acepta_politicas'])) {
            throw new DomainException('Debes aceptar las Políticas de Privacidad y Tratamiento de Datos Personales para registrarte.');
        }

        $password        = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirmacion'] ?? '');
        $this->validarPassword($password, $passwordConfirm);
        $this->validador->validarDominioPermitido($datos['correo_normalizado']);

        $tipoDoc    = $datos['tipo_documento'];
        $documento  = $datos['documento'];
        $apellidos  = $datos['apellidos'];
        $nombres    = $datos['nombres'];
        $celular    = $datos['celular'];
        $correo     = $datos['correo'];
        $correoNorm = $datos['correo_normalizado'];
        $estaturaCm = $datos['estatura_cm'];
        $fechaNac   = $datos['fecha_nacimiento'];

        // 3. Unicidad: documento y correo (en aspirantes y en usuarios del sistema).
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

        // 4. Token de verificación de email (single-use, solo el hash se persiste).
        $id         = IdGenerator::ulid();
        $now        = Clock::now();
        $nowStr     = $now->format('Y-m-d H:i:s.u');
        $tokenPlano = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash  = hash('sha256', $tokenPlano);
        $expira     = $now->modify('+' . self::TOKEN_EXPIRACION_HORAS . ' hours');
        $expiraStr  = $expira->format('Y-m-d H:i:s.u');

        // 5. Persistir en estado pendiente (email_verificado = 0 por defecto).
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
}
