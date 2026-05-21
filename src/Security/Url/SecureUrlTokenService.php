<?php

declare(strict_types=1);

namespace DigiSecure\Security\Url;

use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Emite y valida tokens de URL cifrados con AES-256-GCM.
 *
 * Objetivos:
 *  - Ocultar IDs internos en enlaces públicos del front.
 *  - Ligar cada token al contexto: usuario + tenant + sesión.
 *  - Permitir rollout gradual (aceptar ULID plano en paralelo).
 */
final class SecureUrlTokenService
{
    private const TOKEN_PREFIX = 'sut1_';
    private const TOKEN_VERSION = 1;

    public function __construct(
        private readonly Encryption $encryption,
        private readonly int $ttlSeconds = 900,
    ) {
        if ($this->ttlSeconds < 60) {
            throw new \InvalidArgumentException('secure_url_ttl debe ser >= 60 segundos.');
        }
    }

    public function isToken(string $value): bool
    {
        return \str_starts_with($value, self::TOKEN_PREFIX);
    }

    public function tokenizePath(string $path, AuthenticatedUser $user, string $sessionId): string
    {
        return SecureRouteCatalog::tokenizePath(
            $path,
            fn(string $resourceId, string $paramName, string $scope): string => $this->issueToken(
                $resourceId,
                $scope,
                $paramName,
                $user,
                $sessionId,
            ),
        );
    }

    public function issueToken(
        string $resourceId,
        string $scope,
        string $paramName,
        AuthenticatedUser $user,
        string $sessionId,
    ): string {
        if (!IdGenerator::isValid($resourceId)) {
            throw new SecureUrlTokenException("ID invalido para token seguro ({$paramName}).");
        }
        if ($sessionId === '') {
            throw new SecureUrlTokenException('Sesion invalida para token seguro.');
        }

        try {
            $now = \time();
            $payload = [
                'v' => self::TOKEN_VERSION,
                'rid' => $resourceId,
                'scp' => $scope,
                'prm' => $paramName,
                'uid' => $user->id,
                'tid' => $user->tenantId,
                'sid' => $this->sessionHash($sessionId),
                'iat' => $now,
                'exp' => $now + $this->ttlSeconds,
                'nonce' => \bin2hex(\random_bytes(8)),
            ];
            $json = \json_encode($payload, JSON_THROW_ON_ERROR);
            $ciphertext = $this->encryption->encrypt(
                $json,
                $this->aad($user->id, $user->tenantId, $sessionId),
            );
        } catch (\Throwable $e) {
            throw new SecureUrlTokenException('No se pudo emitir token seguro.', 0, $e);
        }

        return self::TOKEN_PREFIX . $ciphertext;
    }

    /**
     * Devuelve el resource ID real desde un token válido.
     *
     * @throws SecureUrlTokenException
     */
    public function decodeResourceId(
        string $token,
        string $expectedScope,
        string $expectedParamName,
        AuthenticatedUser $user,
        string $sessionId,
    ): string {
        if (!$this->isToken($token)) {
            throw new SecureUrlTokenException('Formato de token seguro invalido.');
        }
        if ($sessionId === '') {
            throw new SecureUrlTokenException('Sesion invalida para token seguro.');
        }

        $encoded = \substr($token, \strlen(self::TOKEN_PREFIX));

        try {
            $json = $this->encryption->decrypt(
                $encoded,
                $this->aad($user->id, $user->tenantId, $sessionId),
            );
            $payload = \json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new SecureUrlTokenException('Token seguro invalido o manipulado.', 0, $e);
        }

        if (!\is_array($payload)) {
            throw new SecureUrlTokenException('Payload de token invalido.');
        }

        $rid = (string)($payload['rid'] ?? '');
        $scope = (string)($payload['scp'] ?? '');
        $paramName = (string)($payload['prm'] ?? '');
        $uid = (string)($payload['uid'] ?? '');
        $tid = (string)($payload['tid'] ?? '');
        $sidHash = (string)($payload['sid'] ?? '');
        $version = (int)($payload['v'] ?? 0);
        $iat = (int)($payload['iat'] ?? 0);
        $exp = (int)($payload['exp'] ?? 0);

        if ($version !== self::TOKEN_VERSION) {
            throw new SecureUrlTokenException('Version de token no soportada.');
        }
        if (!IdGenerator::isValid($rid)) {
            throw new SecureUrlTokenException('Resource ID invalido en token.');
        }
        if ($scope === '' || !\hash_equals($expectedScope, $scope)) {
            throw new SecureUrlTokenException('Scope de token no coincide.');
        }
        if ($paramName === '' || !\hash_equals($expectedParamName, $paramName)) {
            throw new SecureUrlTokenException('Parametro de token no coincide.');
        }
        if (!\hash_equals($user->id, $uid) || !\hash_equals($user->tenantId, $tid)) {
            throw new SecureUrlTokenException('Token emitido para otro usuario o tenant.');
        }
        if (!\hash_equals($this->sessionHash($sessionId), $sidHash)) {
            throw new SecureUrlTokenException('Token emitido para otra sesion.');
        }

        $now = \time();
        if ($iat <= 0 || $exp <= 0 || $exp <= $now || $iat > ($now + 60)) {
            throw new SecureUrlTokenException('Token expirado o con tiempos invalidos.');
        }
        if (($exp - $iat) > ($this->ttlSeconds + 60)) {
            throw new SecureUrlTokenException('TTL de token fuera de politica.');
        }

        return $rid;
    }

    private function aad(string $userId, string $tenantId, string $sessionId): string
    {
        return 'secure-url:v1'
            . '|u:' . $userId
            . '|t:' . $tenantId
            . '|s:' . $this->sessionHash($sessionId);
    }

    private function sessionHash(string $sessionId): string
    {
        return \hash('sha256', $sessionId);
    }
}
