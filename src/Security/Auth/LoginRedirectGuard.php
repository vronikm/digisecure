<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth;

/**
 * Valida y normaliza la ruta de retorno tras un login exitoso.
 *
 * Evita open redirect y limita rutas administrativas a usuarios con permisos
 * de superadministración.
 */
final class LoginRedirectGuard
{
    public function resolve(string $returnTo, AuthenticatedUser $user): string
    {
        $candidate = trim($returnTo);

        if ($candidate === '') {
            return '/dashboard';
        }

        if ($candidate === '/' || $candidate === '\\') {
            return '/dashboard';
        }

        if ($candidate === null || $candidate === '0') {
            return '/dashboard';
        }

        if (str_contains($candidate, "\0")) {
            return '/dashboard';
        }

        if (!str_starts_with($candidate, '/')) {
            return '/dashboard';
        }

        if (str_starts_with($candidate, '//')) {
            return '/dashboard';
        }

        if (preg_match('#^[a-z]+://#i', $candidate) === 1) {
            return '/dashboard';
        }

        if (str_contains($candidate, '../')) {
            return '/dashboard';
        }

        if (str_starts_with($candidate, '/admin') && !$user->tienePermiso('superadmin.panel')) {
            return '/dashboard';
        }

        return $candidate;
    }
}
