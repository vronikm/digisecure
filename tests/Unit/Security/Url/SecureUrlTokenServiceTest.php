<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Security\Url;

use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Security\Url\SecureUrlTokenException;
use DigiSecure\Security\Url\SecureUrlTokenService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecureUrlTokenServiceTest extends TestCase
{
    #[Test]
    public function emite_y_decodifica_token_valido(): void
    {
        $service = $this->service(900);
        $user = $this->user();
        $resourceId = '01HZX3M9K8P1Q2R3S4T5V6W7X8';
        $sessionId = 'sess_abc_123';

        $token = $service->issueToken($resourceId, 'guardias.id', 'id', $user, $sessionId);
        $decoded = $service->decodeResourceId($token, 'guardias.id', 'id', $user, $sessionId);

        self::assertTrue($service->isToken($token));
        self::assertSame($resourceId, $decoded);
        self::assertStringStartsWith('sut1_', $token);
        self::assertStringNotContainsString('/', $token);
    }

    #[Test]
    public function rechaza_token_con_scope_distinto(): void
    {
        $service = $this->service(900);
        $user = $this->user();
        $token = $service->issueToken(
            '01HZX3M9K8P1Q2R3S4T5V6W7X8',
            'guardias.id',
            'id',
            $user,
            'sess_abc_123',
        );

        $this->expectException(SecureUrlTokenException::class);
        $service->decodeResourceId($token, 'documentos.id', 'id', $user, 'sess_abc_123');
    }

    #[Test]
    public function rechaza_token_con_sesion_distinta(): void
    {
        $service = $this->service(900);
        $user = $this->user();
        $token = $service->issueToken(
            '01HZX3M9K8P1Q2R3S4T5V6W7X8',
            'guardias.id',
            'id',
            $user,
            'sess_abc_123',
        );

        $this->expectException(SecureUrlTokenException::class);
        $service->decodeResourceId($token, 'guardias.id', 'id', $user, 'sess_other');
    }

    #[Test]
    public function tokenize_path_aplica_catalogo_sensible(): void
    {
        $service = $this->service(900);
        $user = $this->user();
        $sessionId = 'sess_abc_123';
        $guardiaId = '01HZX3M9K8P1Q2R3S4T5V6W7X8';

        $path = "/guardias/{$guardiaId}/expediente";
        $tokenized = $service->tokenizePath($path, $user, $sessionId);

        self::assertNotSame($path, $tokenized);
        self::assertMatchesRegularExpression('#^/guardias/sut1_[A-Za-z0-9\-_]+/expediente$#', $tokenized);
    }

    #[Test]
    public function tokenize_path_cifra_query_ids_y_permite_decodificar(): void
    {
        $service = $this->service(900);
        $user = $this->user();
        $sessionId = 'sess_abc_123';
        $clienteId = '01HZX3M9K8P1Q2R3S4T5V6W7X8';

        $path = "/puestos/crear?cliente_id={$clienteId}&foo=bar";
        $tokenized = $service->tokenizePath($path, $user, $sessionId);

        self::assertStringStartsWith('/puestos/crear?', $tokenized);
        self::assertStringNotContainsString($clienteId, $tokenized);

        $parts = \parse_url($tokenized);
        self::assertIsArray($parts);
        self::assertArrayHasKey('query', $parts);

        \parse_str((string) $parts['query'], $query);
        self::assertArrayHasKey('cliente_id', $query);
        self::assertArrayHasKey('foo', $query);
        self::assertSame('bar', $query['foo']);
        self::assertIsString($query['cliente_id']);
        self::assertTrue($service->isToken($query['cliente_id']));

        $decoded = $service->decodeResourceId(
            $query['cliente_id'],
            'clientes.id',
            'cliente_id',
            $user,
            $sessionId,
        );

        self::assertSame($clienteId, $decoded);
    }

    private function service(int $ttlSeconds): SecureUrlTokenService
    {
        return new SecureUrlTokenService(
            new Encryption(\random_bytes(32)),
            $ttlSeconds,
        );
    }

    private function user(): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: 'USR_01HZX3M9K8P1Q2R3S4T5V6W7',
            tenantId: 'TEN_01HZX3M9K8P1Q2R3S4T5V6W7',
            email: 'qa@digisecure.test',
            nombres: 'QA',
            apellidos: 'Tester',
            cargo: 'QA',
            estado: 'activo',
            tenantNombre: 'Tenant QA',
            tenantEstado: 'activo',
            permisos: ['guardias.ver'],
            roles: [['codigo' => 'admin', 'nombre' => 'Administrador']],
        );
    }
}
