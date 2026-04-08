<?php

declare(strict_types=1);

namespace DigiSecure\User\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Controller de perfil del usuario autenticado.
 *
 * GET  /perfil           → ver perfil
 * GET  /perfil/password  → formulario de cambio de contraseña
 * POST /perfil/password  → procesar cambio de contraseña
 */
final class ProfileController
{
    public function __construct(
        private readonly ViewRenderer $viewRenderer,
        private readonly PdoConnection $db,
        private readonly AuditLogger $audit,
    ) {
    }

    public function show(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->viewRenderer->response('user/profile', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
        ]);
    }

    public function showPasswordForm(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->viewRenderer->response('user/change-password', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
        ]);
    }

    public function changePassword(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $currentPassword = (string)$request->post('current_password', '');
        $newPassword = (string)$request->post('new_password', '');
        $confirmPassword = (string)$request->post('confirm_password', '');

        // Validaciones
        $error = $this->validatePasswordChange($user->id, $currentPassword, $newPassword, $confirmPassword);
        if ($error !== null) {
            return $this->viewRenderer->response('user/change-password', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'error' => $error,
            ]);
        }

        // Actualizar contraseña
        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ]);

        $this->db->execute(
            'UPDATE usuarios SET password_hash = :hash, password_changed_at = :now, debe_cambiar_password = 0, updated_at = :now2 WHERE id = :id',
            [
                'hash' => $newHash,
                'now' => Clock::nowString(),
                'now2' => Clock::nowString(),
                'id' => $user->id,
            ],
        );

        $this->audit->log(
            'usuario.password_changed',
            $user->id,
            'usuario',
            $user->id,
            ip: $request->clientIp(),
        );

        return $this->viewRenderer->response('user/change-password', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'success' => 'Contraseña actualizada correctamente.',
        ]);
    }

    private function validatePasswordChange(
        string $userId,
        string $currentPassword,
        string $newPassword,
        string $confirmPassword,
    ): ?string {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return 'Todos los campos son obligatorios.';
        }

        if ($newPassword !== $confirmPassword) {
            return 'La nueva contraseña y su confirmación no coinciden.';
        }

        if (mb_strlen($newPassword) < 8) {
            return 'La nueva contraseña debe tener al menos 8 caracteres.';
        }

        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return 'La contraseña debe incluir al menos una mayúscula, una minúscula y un número.';
        }

        // Verificar contraseña actual
        $row = $this->db->fetchOne(
            'SELECT password_hash FROM usuarios WHERE id = :id',
            ['id' => $userId],
        );

        if ($row === null || !password_verify($currentPassword, $row['password_hash'])) {
            return 'La contraseña actual es incorrecta.';
        }

        return null;
    }
}
