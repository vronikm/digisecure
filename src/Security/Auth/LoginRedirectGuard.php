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
        $default = $user->esAspiranteGuardia() ? '/guardias' : '/dashboard';

        if ($candidate === '') {
            return $default;
        }

        if ($candidate === '/' || $candidate === '\\') {
            return $default;
        }

        if ($candidate === null || $candidate === '0') {
            return $default;
        }

        if (str_contains($candidate, "\0")) {
            return $default;
        }

        if (!str_starts_with($candidate, '/')) {
            return $default;
        }

        if (str_starts_with($candidate, '//')) {
            return $default;
        }

        if (preg_match('#^[a-z]+://#i', $candidate) === 1) {
            return $default;
        }

        if (str_contains($candidate, '../')) {
            return $default;
        }

        if ($user->esAspiranteGuardia() && !str_starts_with($candidate, '/guardias') && !str_starts_with($candidate, '/expedientes/documento/')) {
            return $default;
        }

        return $candidate;
    }
}
