<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Security\Auth;

use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Auth\LoginRedirectGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoginRedirectGuardTest extends TestCase
{
    #[Test]
    public function devuelve_dashboard_cuando_la_ruta_es_vacia_o_invalida(): void
    {
        $guard = new LoginRedirectGuard();
        $user = $this->user();

        self::assertSame('/dashboard', $guard->resolve('', $user));
        self::assertSame('/dashboard', $guard->resolve('https://evil.test', $user));
        self::assertSame('/dashboard', $guard->resolve('//evil.test', $user));
        self::assertSame('/dashboard', $guard->resolve('/../secret', $user));
        self::assertSame('/dashboard', $guard->resolve("/dashboard\0", $user));
    }

    #[Test]
    public function conserva_rutas_internas_relativas_validas(): void
    {
        $guard = new LoginRedirectGuard();
        $user = $this->user();

        self::assertSame('/dashboard', $guard->resolve('/dashboard', $user));
        self::assertSame('/expedientes/123', $guard->resolve('/expedientes/123', $user));
    }

    private function user(): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: 'user-1',
            tenantId: 'tenant-1',
            email: 'ops@gemeseg.com',
            nombres: 'Ana',
            apellidos: 'Pérez',
            cargo: 'Operador',
            estado: 'activo',
            tenantNombre: 'GEMESEG',
            tenantEstado: 'activo',
            permisos: ['usuarios.read'],
            roles: [['codigo' => 'operador', 'nombre' => 'Operador']],
        );
    }
}
