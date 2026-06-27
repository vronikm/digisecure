<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DateTimeImmutable;
use DigiSecure\Aspirante\Infrastructure\AspiranteEventLogger;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Valida el correo del aspirante (enlace mágico /verificar?token=XXXX) y, si el
 * token es válido y vigente (<=48h), COMPLETA el registro:
 *
 *   - email_verificado = 1, estado 'nuevo' -> 'aspirante'.
 *   - Crea un usuario ACTIVO (reutilizando email + password_hash del aspirante)
 *     en el tenant de plataforma, con el rol 'aspirante' (acceso restringido a
 *     /guardias/crear; el confinamiento lo aplica AspiranteGateMiddleware).
 *   - Consume el token (single-use).
 *
 * Audita: link_abierto, correo_validado, registro_completado, token_expirado.
 * Seguridad: token comparado por hash SHA-256; nunca se guarda en claro.
 */
final class VerificarEmailService
{
    private const ROL_ASPIRANTE = 'aspirante';

    public function __construct(
        private readonly AspiranteRepository $repo,
        private readonly PdoConnection $db,
        private readonly AspiranteEventLogger $eventos,
    ) {
    }

    /**
     * @return array{status: 'verificado'|'ya_verificado', nombre: string}
     */
    public function execute(string $token, string $ip = '', string $userAgent = ''): array
    {
        $token = trim($token);
        if ($token === '' || ctype_xdigit($token) === false) {
            $this->eventos->registrar(AspiranteEventLogger::LINK_ABIERTO, null, null, $ip, $userAgent, ['resultado' => 'token_invalido']);
            throw new DomainException('El enlace de verificación no es válido.');
        }

        $ref = $this->repo->findByTokenVerificacion(hash('sha256', $token));
        if ($ref === null) {
            $this->eventos->registrar(AspiranteEventLogger::LINK_ABIERTO, null, null, $ip, $userAgent, ['resultado' => 'token_no_encontrado']);
            throw new DomainException('El enlace de verificación no es válido o ya fue utilizado.');
        }

        $a = $this->repo->findDetalle($ref['id']);
        if ($a === null) {
            throw new DomainException('El enlace de verificación no es válido o ya fue utilizado.');
        }

        $email = (string) $a['correo'];
        $nombre = trim(($a['nombres'] ?? '') . ' ' . ($a['apellidos'] ?? ''));

        // Link abierto (auditoría) — siempre que el token resuelva a un aspirante.
        $this->eventos->registrar(AspiranteEventLogger::LINK_ABIERTO, $email, $ref['id'], $ip, $userAgent);

        if ((int) $a['email_verificado'] === 1) {
            return ['status' => 'ya_verificado', 'nombre' => $nombre];
        }

        // Validar expiración (48h).
        if ($a['verificacion_token_expira_at'] !== null) {
            // El valor almacenado está en la zona local (APP_TIMEZONE); se
            // interpreta con la zona por defecto para comparar correctamente.
            $expira = new DateTimeImmutable((string) $a['verificacion_token_expira_at']);
            if (Clock::now() > $expira) {
                $this->eventos->registrar(AspiranteEventLogger::TOKEN_EXPIRADO, $email, $ref['id'], $ip, $userAgent);
                throw new DomainException('El enlace de verificación expiró. Solicita un nuevo enlace para continuar.');
            }
        }

        $tenantId = $this->repo->tenantPlataforma();
        if ($tenantId === null) {
            throw new DomainException('No se pudo completar el registro. Contacta a soporte.');
        }
        if ($this->repo->existeCorreoEnUsuarios((string) $a['correo_normalizado'])) {
            throw new DomainException('Ya existe una cuenta con este correo. Intenta iniciar sesión.');
        }

        $usuarioId = IdGenerator::ulid();
        $now = Clock::nowString();

        $this->db->transactional(function () use ($a, $usuarioId, $tenantId, $now): void {
            $this->repo->crearUsuario([
                'id'                => $usuarioId,
                'tenant_id'         => $tenantId,
                'email'             => (string) $a['correo'],
                'email_normalizado' => (string) $a['correo_normalizado'],
                'password_hash'     => (string) $a['password_hash'],
                'nombres'           => (string) $a['nombres'],
                'apellidos'         => (string) $a['apellidos'],
                'telefono'          => $a['celular'] !== null ? (string) $a['celular'] : null,
                'cargo'             => 'Aspirante (guardia)',
                'created_by'        => null,
                'cuando'            => $now,
            ]);

            $roleId = $this->repo->buscarRolId(self::ROL_ASPIRANTE, $tenantId);
            if ($roleId !== null) {
                $this->repo->asignarRol($usuarioId, $roleId, null, $now);
            }

            $this->repo->marcarAspirante((string) $a['id'], $usuarioId, $tenantId, $now);
        });

        $this->eventos->registrar(AspiranteEventLogger::CORREO_VALIDADO, $email, $ref['id'], $ip, $userAgent);
        $this->eventos->registrar(AspiranteEventLogger::REGISTRO_COMPLETADO, $email, $ref['id'], $ip, $userAgent, ['usuario_id' => $usuarioId]);

        return ['status' => 'verificado', 'nombre' => $nombre];
    }
}
