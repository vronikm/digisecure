<?php

declare(strict_types=1);

namespace DigiSecure\Security\Url;

use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Catálogo central de rutas con parámetros sensibles.
 *
 * Define:
 *  - Mapeo por nombre de ruta => parámetros esperados y su scope.
 *  - Reglas de tokenización por path para links/form actions.
 */
final class SecureRouteCatalog
{
    /**
     * @var array<string, string>
     */
    private const LEGACY_RESOURCE_ID_PATTERNS = [
        'roles.id' => '/^[A-Z0-9]{26}$/',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const ROUTE_PARAM_SCOPES = [
        // Guardias
        'fichas.historial' => ['id' => 'guardias.id'],
        'fichas.historial.export' => ['id' => 'guardias.id'],
        'fichas.show' => ['id' => 'guardias.id'],
        'fichas.edit' => ['id' => 'guardias.id'],
        'fichas.update' => ['id' => 'guardias.id'],
        'fichas.completar' => ['id' => 'guardias.id'],
        'fichas.destroy' => ['id' => 'guardias.id'],
        'fichas.recover' => ['id' => 'guardias.id'],
        'fichas.pdf.declaracion' => ['id' => 'guardias.id'],
        'fichas.pdf.huellas' => ['id' => 'guardias.id'],

        // Expedientes y documentos
        'expedientes.dashboard' => ['fichaId' => 'guardias.id'],
        // `tipoId` puede venir de seeds legacy (ej. SEEDDOC...) sin formato ULID;
        // se protege el expediente y se permite tipoId plano para compatibilidad.
        'expedientes.documento.upload.form' => ['id' => 'expedientes.id'],
        'expedientes.documento.upload' => ['id' => 'expedientes.id'],
        'expedientes.documento.show' => ['docId' => 'documentos.id'],
        'expedientes.documento.revisar' => ['docId' => 'documentos.id'],
        'expedientes.documento.descargar' => ['docId' => 'documentos.id'],

        // Notificaciones
        'notificaciones.show' => ['id' => 'notificaciones.id'],
        'notificaciones.retry' => ['id' => 'notificaciones.id'],

        // Usuarios
        'usuarios.edit' => ['id' => 'usuarios.id'],
        'usuarios.update' => ['id' => 'usuarios.id'],
        'usuarios.estado' => ['id' => 'usuarios.id'],
        'usuarios.password' => ['id' => 'usuarios.id'],

        // Roles
        'roles.show' => ['id' => 'roles.id'],
        'roles.edit' => ['id' => 'roles.id'],
        'roles.update' => ['id' => 'roles.id'],
        'roles.destroy' => ['id' => 'roles.id'],

        // LOPDP
        'lopdp.solicitudes.show' => ['id' => 'lopdp_solicitudes.id'],
        'lopdp.solicitudes.responder' => ['id' => 'lopdp_solicitudes.id'],
        'lopdp.consentimientos.show' => ['id' => 'consentimientos_lopdp.id'],
        'lopdp.consentimientos.revocar' => ['id' => 'consentimientos_lopdp.id'],

        // Clientes
        'clientes.show' => ['id' => 'clientes.id'],
        'clientes.edit' => ['id' => 'clientes.id'],
        'clientes.update' => ['id' => 'clientes.id'],
        'clientes.estado' => ['id' => 'clientes.id'],

        // Puestos
        'puestos.show' => ['id' => 'puestos.id'],
        'puestos.edit' => ['id' => 'puestos.id'],
        'puestos.update' => ['id' => 'puestos.id'],
        'puestos.estado' => ['id' => 'puestos.id'],

        // Asignaciones
        'asignaciones.show' => ['id' => 'asignaciones.id'],
        'asignaciones.edit' => ['id' => 'asignaciones.id'],
        'asignaciones.update' => ['id' => 'asignaciones.id'],
        'asignaciones.cerrar' => ['id' => 'asignaciones.id'],

        // Superadmin tenants
        'admin.tenants.detalle' => ['id' => 'tenants.id'],
        'admin.tenants.edit' => ['id' => 'tenants.id'],
        'admin.tenants.update' => ['id' => 'tenants.id'],
        'admin.tenants.estado' => ['id' => 'tenants.id'],
        'admin.tenants.destroy' => ['id' => 'tenants.id'],
        'admin.tenants.plan' => ['id' => 'tenants.id'],
        'admin.tenants.usuarios.asociar' => ['id' => 'tenants.id'],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const ROUTE_QUERY_SCOPES = [
        'puestos.index' => ['cliente_id' => 'clientes.id'],
        'puestos.create' => ['cliente_id' => 'clientes.id'],
        'asignaciones.index' => [
            'cliente_id' => 'clientes.id',
            'puesto_id' => 'puestos.id',
            'ficha_id' => 'guardias.id',
        ],
        'asignaciones.create' => [
            'puesto_id' => 'puestos.id',
            'ficha_id' => 'guardias.id',
        ],
        'auditoria.index' => [
            'usuario_id' => 'usuarios.id',
        ],
        'auditoria.exportar' => [
            'usuario_id' => 'usuarios.id',
        ],
    ];

    /**
     * @var list<array{
     *   segments: list<string>,
     *   scopes: array<string, string>
     * }>
     */
    private const PATH_RULES = [
        ['segments' => ['guardias', '{id}'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'fichas'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'fichas', 'exportar'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'editar'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'completar'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'eliminar'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'recuperar'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'declaracion-lealtad.pdf'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{id}', 'formato-huellas.pdf'], 'scopes' => ['id' => 'guardias.id']],
        ['segments' => ['guardias', '{fichaId}', 'expediente'], 'scopes' => ['fichaId' => 'guardias.id']],
        ['segments' => ['expedientes', '{id}', 'documento', '{tipoId}'], 'scopes' => ['id' => 'expedientes.id']],
        ['segments' => ['expedientes', 'documento', '{docId}'], 'scopes' => ['docId' => 'documentos.id']],
        ['segments' => ['expedientes', 'documento', '{docId}', 'descargar'], 'scopes' => ['docId' => 'documentos.id']],
        ['segments' => ['expedientes', 'documento', '{docId}', 'revisar'], 'scopes' => ['docId' => 'documentos.id']],
        ['segments' => ['notificaciones', '{id}'], 'scopes' => ['id' => 'notificaciones.id']],
        ['segments' => ['notificaciones', '{id}', 'retry'], 'scopes' => ['id' => 'notificaciones.id']],
        ['segments' => ['usuarios', '{id}', 'editar'], 'scopes' => ['id' => 'usuarios.id']],
        ['segments' => ['usuarios', '{id}', 'estado'], 'scopes' => ['id' => 'usuarios.id']],
        ['segments' => ['usuarios', '{id}', 'password'], 'scopes' => ['id' => 'usuarios.id']],
        ['segments' => ['roles', '{id}'], 'scopes' => ['id' => 'roles.id']],
        ['segments' => ['roles', '{id}', 'editar'], 'scopes' => ['id' => 'roles.id']],
        ['segments' => ['roles', '{id}', 'eliminar'], 'scopes' => ['id' => 'roles.id']],
        ['segments' => ['lopdp', 'solicitudes', '{id}'], 'scopes' => ['id' => 'lopdp_solicitudes.id']],
        ['segments' => ['lopdp', 'solicitudes', '{id}', 'responder'], 'scopes' => ['id' => 'lopdp_solicitudes.id']],
        ['segments' => ['lopdp', 'consentimientos', '{id}'], 'scopes' => ['id' => 'consentimientos_lopdp.id']],
        ['segments' => ['lopdp', 'consentimientos', '{id}', 'revocar'], 'scopes' => ['id' => 'consentimientos_lopdp.id']],
        ['segments' => ['clientes', '{id}'], 'scopes' => ['id' => 'clientes.id']],
        ['segments' => ['clientes', '{id}', 'editar'], 'scopes' => ['id' => 'clientes.id']],
        ['segments' => ['clientes', '{id}', 'estado'], 'scopes' => ['id' => 'clientes.id']],
        ['segments' => ['puestos', '{id}'], 'scopes' => ['id' => 'puestos.id']],
        ['segments' => ['puestos', '{id}', 'editar'], 'scopes' => ['id' => 'puestos.id']],
        ['segments' => ['puestos', '{id}', 'estado'], 'scopes' => ['id' => 'puestos.id']],
        ['segments' => ['asignaciones', '{id}'], 'scopes' => ['id' => 'asignaciones.id']],
        ['segments' => ['asignaciones', '{id}', 'editar'], 'scopes' => ['id' => 'asignaciones.id']],
        ['segments' => ['asignaciones', '{id}', 'cerrar'], 'scopes' => ['id' => 'asignaciones.id']],
        ['segments' => ['admin', 'tenants', '{id}'], 'scopes' => ['id' => 'tenants.id']],
        ['segments' => ['admin', 'tenants', '{id}', 'editar'], 'scopes' => ['id' => 'tenants.id']],
        ['segments' => ['admin', 'tenants', '{id}', 'estado'], 'scopes' => ['id' => 'tenants.id']],
        ['segments' => ['admin', 'tenants', '{id}', 'eliminar'], 'scopes' => ['id' => 'tenants.id']],
        ['segments' => ['admin', 'tenants', '{id}', 'plan'], 'scopes' => ['id' => 'tenants.id']],
        ['segments' => ['admin', 'tenants', '{id}', 'usuarios', 'asociar'], 'scopes' => ['id' => 'tenants.id']],
    ];

    /**
     * @var list<array{
     *   segments: list<string>,
     *   scopes: array<string, string>
     * }>
     */
    private const PATH_QUERY_RULES = [
        ['segments' => ['puestos'], 'scopes' => ['cliente_id' => 'clientes.id']],
        ['segments' => ['puestos', 'crear'], 'scopes' => ['cliente_id' => 'clientes.id']],
        [
            'segments' => ['asignaciones'],
            'scopes' => [
                'cliente_id' => 'clientes.id',
                'puesto_id' => 'puestos.id',
                'ficha_id' => 'guardias.id',
            ],
        ],
        [
            'segments' => ['asignaciones', 'crear'],
            'scopes' => [
                'puesto_id' => 'puestos.id',
                'ficha_id' => 'guardias.id',
            ],
        ],
        [
            'segments' => ['auditoria'],
            'scopes' => ['usuario_id' => 'usuarios.id'],
        ],
        [
            'segments' => ['auditoria', 'exportar'],
            'scopes' => ['usuario_id' => 'usuarios.id'],
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function routeParamScopes(?string $routeName): array
    {
        if ($routeName === null) {
            return [];
        }

        return self::ROUTE_PARAM_SCOPES[$routeName] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function routeQueryScopes(?string $routeName): array
    {
        if ($routeName === null) {
            return [];
        }

        return self::ROUTE_QUERY_SCOPES[$routeName] ?? [];
    }

    public static function isValidResourceId(string $scope, string $value): bool
    {
        if (IdGenerator::isValid($value)) {
            return true;
        }

        $legacyPattern = self::LEGACY_RESOURCE_ID_PATTERNS[$scope] ?? null;
        return $legacyPattern !== null && \preg_match($legacyPattern, $value) === 1;
    }

    /**
     * Tokeniza parámetros sensibles de una URL interna según PATH_RULES.
     *
     * @param callable(string, string, string): string $tokenize
     */
    public static function tokenizePath(string $path, callable $tokenize): string
    {
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $parts = \parse_url($path);
        if ($parts === false) {
            return $path;
        }

        $rawPath = $parts['path'] ?? $path;
        if ($rawPath === '' || !str_starts_with($rawPath, '/')) {
            return $path;
        }

        $trimmed = \trim($rawPath, '/');
        $segments = $trimmed === '' ? [] : \explode('/', $trimmed);

        foreach (self::PATH_RULES as $rule) {
            $rebuilt = self::applyRule($segments, $rule, $tokenize);
            if ($rebuilt === null) {
                continue;
            }

            $newPath = '/' . \implode('/', $rebuilt);
            if (isset($parts['query']) && $parts['query'] !== '') {
                $newPath .= '?' . $parts['query'];
            }
            if (isset($parts['fragment']) && $parts['fragment'] !== '') {
                $newPath .= '#' . $parts['fragment'];
            }
            return $newPath;
        }

        return self::tokenizePathQueryOnly($path, $segments, $tokenize);
    }

    /**
     * @param list<string> $segments
     * @param array{
     *   segments: list<string>,
     *   scopes: array<string, string>
     * } $rule
     * @param callable(string, string, string): string $tokenize
     * @return list<string>|null
     */
    private static function applyRule(array $segments, array $rule, callable $tokenize): ?array
    {
        $pattern = $rule['segments'];
        if (\count($segments) !== \count($pattern)) {
            return null;
        }

        $captured = [];
        foreach ($pattern as $idx => $piece) {
            if (self::isPlaceholder($piece)) {
                $captured[self::placeholderName($piece)] = $segments[$idx];
                continue;
            }

            if ($segments[$idx] !== $piece) {
                return null;
            }
        }

        foreach ($rule['scopes'] as $paramName => $scope) {
            if (!isset($captured[$paramName])) {
                continue;
            }

            $value = $captured[$paramName];
            if (!self::isValidResourceId($scope, $value)) {
                // Si el placeholder sensible no trae un ID valido para su scope, la regla no aplica.
                return null;
            }

            $captured[$paramName] = $tokenize($value, $paramName, $scope);
        }

        $rebuilt = [];
        foreach ($pattern as $piece) {
            if (self::isPlaceholder($piece)) {
                $name = self::placeholderName($piece);
                $rebuilt[] = $captured[$name] ?? '';
                continue;
            }
            $rebuilt[] = $piece;
        }

        return $rebuilt;
    }

    /**
     * @param list<string> $segments
     * @param callable(string, string, string): string $tokenize
     */
    private static function tokenizePathQueryOnly(string $path, array $segments, callable $tokenize): string
    {
        $parts = \parse_url($path);
        if ($parts === false) {
            return $path;
        }

        if (!isset($parts['query']) || $parts['query'] === '') {
            return $path;
        }

        $scopes = self::queryScopesForSegments($segments);
        if ($scopes === []) {
            return $path;
        }

        \parse_str($parts['query'], $queryData);
        if ($queryData === [] || !\is_array($queryData)) {
            return $path;
        }

        $changed = false;
        foreach ($scopes as $paramName => $scope) {
            if (!isset($queryData[$paramName]) || !\is_string($queryData[$paramName])) {
                continue;
            }

            $value = $queryData[$paramName];
            if (!self::isValidResourceId($scope, $value)) {
                continue;
            }

            $queryData[$paramName] = $tokenize($value, $paramName, $scope);
            $changed = true;
        }

        if (!$changed) {
            return $path;
        }

        $newQuery = \http_build_query($queryData, '', '&', PHP_QUERY_RFC3986);
        $rebuilt = $parts['path'] ?? '';
        if ($newQuery !== '') {
            $rebuilt .= '?' . $newQuery;
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }

    /**
     * @param list<string> $segments
     * @return array<string, string>
     */
    private static function queryScopesForSegments(array $segments): array
    {
        foreach (self::PATH_QUERY_RULES as $rule) {
            if (\count($segments) !== \count($rule['segments'])) {
                continue;
            }

            $matches = true;
            foreach ($rule['segments'] as $idx => $piece) {
                if ($segments[$idx] !== $piece) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $rule['scopes'];
            }
        }

        return [];
    }

    private static function isPlaceholder(string $piece): bool
    {
        return \str_starts_with($piece, '{') && \str_ends_with($piece, '}');
    }

    private static function placeholderName(string $piece): string
    {
        return \substr($piece, 1, -1);
    }
}
