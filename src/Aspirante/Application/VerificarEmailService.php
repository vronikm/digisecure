<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DateTimeImmutable;
use DateTimeZone;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Verifica el email de un aspirante a partir del token del enlace magico
 * (/verificar?token=XXXX).
 *
 * Reglas:
 *  - El token debe existir (se compara por su hash SHA-256) y no haber expirado.
 *  - Es single-use: al verificar se consume (el repo borra el hash), por lo que
 *    un segundo clic devolvera "enlace invalido o ya utilizado".
 *  - Verificar el email NO habilita al aspirante para operar; solo confirma que
 *    el correo es real (sigue 'pendiente' de revision de reclutamiento).
 */
final class VerificarEmailService
{
    public function __construct(
        private readonly AspiranteRepository $repo,
    ) {
    }

    /**
     * @return array{status: 'verificado'|'ya_verificado', nombre: string}
     */
    public function execute(string $token): array
    {
        $token = trim($token);
        if ($token === '' || ctype_xdigit($token) === false) {
            throw new DomainException('El enlace de verificación no es válido.');
        }

        $row = $this->repo->findByTokenVerificacion(hash('sha256', $token));
        if ($row === null) {
            throw new DomainException('El enlace de verificación no es válido o ya fue utilizado.');
        }

        $nombre = trim($row['nombres'] . ' ' . $row['apellidos']);

        if ($row['email_verificado'] === 1) {
            return ['status' => 'ya_verificado', 'nombre' => $nombre];
        }

        if ($row['expira_at'] !== null) {
            $expira = new DateTimeImmutable($row['expira_at'], new DateTimeZone('UTC'));
            if (Clock::now() > $expira) {
                throw new DomainException('El enlace de verificación expiró. Vuelve a registrarte para recibir uno nuevo.');
            }
        }

        $this->repo->marcarEmailVerificado($row['id'], Clock::nowString());

        return ['status' => 'verificado', 'nombre' => $nombre];
    }
}
