<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Application;

use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Tenant\Infrastructure\TenantRepository;

/**
 * Permite al admin del tenant editar los datos propios de su empresa.
 *
 * Campos editables: razón social, nombre comercial, email de contacto,
 * teléfono, dirección, ciudad, provincia. NO se permite cambiar el RUC,
 * estado, plan ni límites (eso es operación de superadmin).
 */
final class ActualizarTenantService
{
    private const CAMPOS_EDITABLES = [
        'razon_social', 'nombre_comercial', 'email_contacto',
        'telefono', 'direccion', 'ciudad', 'provincia',
    ];

    public function __construct(
        private readonly TenantRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input, string $usuarioId, string $ip): void
    {
        $razonSocial = trim((string)($input['razon_social'] ?? ''));
        if ($razonSocial === '') {
            throw new DomainException('La razón social es obligatoria.');
        }

        $email = trim((string)($input['email_contacto'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El email de contacto debe ser válido.');
        }

        $data = [];
        foreach (self::CAMPOS_EDITABLES as $campo) {
            if (array_key_exists($campo, $input)) {
                $val = trim((string)($input[$campo] ?? ''));
                $data[$campo] = $val !== '' ? $val : null;
            }
        }
        // Estos dos son obligatorios — siempre los incluimos
        $data['razon_social'] = $razonSocial;
        $data['email_contacto'] = $email;

        $this->repo->updateCurrent($data);

        $this->audit->log(
            'tenant.actualizado',
            $usuarioId,
            'tenant',
            null,
            ['campos' => array_keys($data)],
            ip: $ip,
        );
    }
}
