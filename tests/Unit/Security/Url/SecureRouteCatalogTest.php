<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Security\Url;

use DigiSecure\Security\Url\SecureRouteCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecureRouteCatalogTest extends TestCase
{
    #[Test]
    public function route_param_scopes_por_nombre_de_ruta(): void
    {
        $scopes = SecureRouteCatalog::routeParamScopes('expedientes.documento.upload');

        self::assertSame([
            'id' => 'expedientes.id',
            'tipoId' => 'documento_tipos.id',
        ], $scopes);
    }

    #[Test]
    public function route_param_scopes_incluye_modulos_extendidos(): void
    {
        self::assertSame(
            ['id' => 'usuarios.id'],
            SecureRouteCatalog::routeParamScopes('usuarios.edit'),
        );
        self::assertSame(
            ['id' => 'tenants.id'],
            SecureRouteCatalog::routeParamScopes('admin.tenants.plan'),
        );
        self::assertSame(
            ['id' => 'tenants.id'],
            SecureRouteCatalog::routeParamScopes('admin.tenants.update'),
        );
        self::assertSame(
            ['id' => 'asignaciones.id'],
            SecureRouteCatalog::routeParamScopes('asignaciones.cerrar'),
        );
        self::assertSame(
            ['id' => 'guardias.id'],
            SecureRouteCatalog::routeParamScopes('fichas.destroy'),
        );
        self::assertSame(
            ['id' => 'guardias.id'],
            SecureRouteCatalog::routeParamScopes('fichas.recover'),
        );
    }

    #[Test]
    public function route_query_scopes_para_filtros_y_preseleccion(): void
    {
        self::assertSame(
            ['cliente_id' => 'clientes.id'],
            SecureRouteCatalog::routeQueryScopes('puestos.create'),
        );
        self::assertSame(
            [
                'cliente_id' => 'clientes.id',
                'puesto_id' => 'puestos.id',
                'ficha_id' => 'guardias.id',
            ],
            SecureRouteCatalog::routeQueryScopes('asignaciones.index'),
        );
        self::assertSame(
            ['usuario_id' => 'usuarios.id'],
            SecureRouteCatalog::routeQueryScopes('auditoria.index'),
        );
        self::assertSame(
            ['usuario_id' => 'usuarios.id'],
            SecureRouteCatalog::routeQueryScopes('auditoria.exportar'),
        );
    }

    #[Test]
    public function tokenize_path_reemplaza_ids_sensibles_preservando_query_y_fragment(): void
    {
        $expId = '01HZX3M9K8P1Q2R3S4T5V6W7X8';
        $tipoId = '01HZX3M9K8P1Q2R3S4T5V6W7X9';

        $calls = [];
        $tokenized = SecureRouteCatalog::tokenizePath(
            "/expedientes/{$expId}/documento/{$tipoId}?foo=bar#top",
            function (string $resourceId, string $paramName, string $scope) use (&$calls): string {
                $calls[] = [$resourceId, $paramName, $scope];
                return "tok_{$paramName}";
            },
        );

        self::assertSame('/expedientes/tok_id/documento/tok_tipoId?foo=bar#top', $tokenized);
        self::assertCount(2, $calls);
        self::assertSame([$expId, 'id', 'expedientes.id'], $calls[0]);
        self::assertSame([$tipoId, 'tipoId', 'documento_tipos.id'], $calls[1]);
    }

    #[Test]
    public function tokenize_path_no_toca_rutas_fuera_del_catalogo(): void
    {
        $path = '/dashboard?x=1';

        $result = SecureRouteCatalog::tokenizePath(
            $path,
            static fn(string $resourceId, string $paramName, string $scope): string => 'token',
        );

        self::assertSame($path, $result);
    }

    #[Test]
    public function tokenize_path_no_retokeniza_segmentos_que_no_son_ulid(): void
    {
        $tokenized = SecureRouteCatalog::tokenizePath(
            '/guardias/sut1_mockedToken/editar',
            static fn(string $resourceId, string $paramName, string $scope): string => 'should_not_happen',
        );

        self::assertSame('/guardias/sut1_mockedToken/editar', $tokenized);
    }

    #[Test]
    public function tokenize_path_cubre_patrones_de_asignaciones_y_admin_tenants(): void
    {
        $asignacionId = '01HZX3M9K8P1Q2R3S4T5V6W7Y1';
        $tenantId = '01HZX3M9K8P1Q2R3S4T5V6W7Y2';
        $guardiaId = '01HZX3M9K8P1Q2R3S4T5V6W7Y3';

        $a = SecureRouteCatalog::tokenizePath(
            "/asignaciones/{$asignacionId}/cerrar",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );
        $t = SecureRouteCatalog::tokenizePath(
            "/admin/tenants/{$tenantId}/plan",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );
        $te = SecureRouteCatalog::tokenizePath(
            "/admin/tenants/{$tenantId}/editar",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );
        $tu = SecureRouteCatalog::tokenizePath(
            "/admin/tenants/{$tenantId}/usuarios/asociar",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );
        $g = SecureRouteCatalog::tokenizePath(
            "/guardias/{$guardiaId}/eliminar",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );
        $gr = SecureRouteCatalog::tokenizePath(
            "/guardias/{$guardiaId}/recuperar",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );

        self::assertSame('/asignaciones/tok_id/cerrar', $a);
        self::assertSame('/admin/tenants/tok_id/plan', $t);
        self::assertSame('/admin/tenants/tok_id/editar', $te);
        self::assertSame('/admin/tenants/tok_id/usuarios/asociar', $tu);
        self::assertSame('/guardias/tok_id/eliminar', $g);
        self::assertSame('/guardias/tok_id/recuperar', $gr);
    }

    #[Test]
    public function tokenize_path_cifra_query_ids_en_links_con_preseleccion(): void
    {
        $clienteId = '01HZX3M9K8P1Q2R3S4T5V6W7Z1';
        $puestoId = '01HZX3M9K8P1Q2R3S4T5V6W7Z2';
        $usuarioId = '01HZX3M9K8P1Q2R3S4T5V6W7Z3';

        $a = SecureRouteCatalog::tokenizePath(
            "/puestos/crear?cliente_id={$clienteId}",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );

        $b = SecureRouteCatalog::tokenizePath(
            "/asignaciones/crear?puesto_id={$puestoId}&ficha_id=valor_no_ulid",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );
        $c = SecureRouteCatalog::tokenizePath(
            "/auditoria?usuario_id={$usuarioId}&evento=auth.login_ok",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );
        $d = SecureRouteCatalog::tokenizePath(
            "/auditoria/exportar?usuario_id={$usuarioId}&nivel=critical",
            static fn(string $resourceId, string $paramName, string $scope): string => "tok_{$paramName}",
        );

        self::assertSame('/puestos/crear?cliente_id=tok_cliente_id', $a);
        self::assertSame('/asignaciones/crear?puesto_id=tok_puesto_id&ficha_id=valor_no_ulid', $b);
        self::assertSame('/auditoria?usuario_id=tok_usuario_id&evento=auth.login_ok', $c);
        self::assertSame('/auditoria/exportar?usuario_id=tok_usuario_id&nivel=critical', $d);
    }
}
