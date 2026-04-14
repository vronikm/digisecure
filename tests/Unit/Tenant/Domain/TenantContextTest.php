<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Tenant\Domain;

use DigiSecure\Tenant\Domain\TenantContext;
use DigiSecure\Tenant\Domain\TenantContextException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la invariante de TenantContext:
 *   - Una vez establecido, no puede cambiar (previene escalación horizontal).
 *   - requireTenantId lanza si no está establecido.
 */
final class TenantContextTest extends TestCase
{
    #[Test]
    public function set_y_lectura(): void
    {
        $ctx = new TenantContext();
        $ctx->set('TENANT_A', 'Empresa A', 'activo');

        self::assertSame('TENANT_A', $ctx->currentTenantId());
        self::assertSame('TENANT_A', $ctx->requireTenantId());
        self::assertSame('Empresa A', $ctx->tenantNombre());
        self::assertSame('activo', $ctx->tenantEstado());
        self::assertTrue($ctx->isSet());
        self::assertTrue($ctx->isActivo());
    }

    #[Test]
    public function no_set_retorna_null(): void
    {
        $ctx = new TenantContext();
        self::assertNull($ctx->currentTenantId());
        self::assertFalse($ctx->isSet());
    }

    #[Test]
    public function require_sin_set_lanza(): void
    {
        $ctx = new TenantContext();
        $this->expectException(TenantContextException::class);
        $ctx->requireTenantId();
    }

    #[Test]
    public function no_se_puede_cambiar_despues_de_set(): void
    {
        $ctx = new TenantContext();
        $ctx->set('TENANT_A', 'Empresa A', 'activo');

        $this->expectException(TenantContextException::class);
        $this->expectExceptionMessage('cambiar');
        $ctx->set('TENANT_B', 'Empresa B', 'activo');
    }

    #[Test]
    public function estado_suspendido_no_es_activo(): void
    {
        $ctx = new TenantContext();
        $ctx->set('T1', 'Test', 'suspendido');
        self::assertFalse($ctx->isActivo());
    }

    #[Test]
    public function superadmin_context_sin_tenant(): void
    {
        $ctx = new TenantContext();
        self::assertTrue($ctx->isSuperAdminContext());

        $ctx2 = new TenantContext();
        $ctx2->set('T1', 'Test', 'activo');
        self::assertFalse($ctx2->isSuperAdminContext());
    }
}
