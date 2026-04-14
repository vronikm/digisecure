<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Application;

use DigiSecure\Expediente\Infrastructure\DocumentoTipoRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Permite al admin del tenant personalizar los tipos de documento:
 *   - Activar/desactivar tipos
 *   - Cambiar si es obligatorio
 *   - Ajustar vigencia (tiene_vencimiento, dias_vigencia)
 *   - Cambiar orden de presentación
 *
 * NO permite cambiar el código, nombre ni bloque (esos los define el sistema).
 */
final class ConfigurarDocumentoTipoService
{
    public function __construct(
        private readonly DocumentoTipoRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(string $codigo, array $input, string $usuarioId, string $ip): string
    {
        $tipo = $this->repo->findByCodigo($codigo);
        if ($tipo === null) {
            throw new DomainException("Tipo de documento '{$codigo}' no encontrado.");
        }

        $data = [];

        // Activo (toggle)
        if (array_key_exists('activo', $input)) {
            $data['activo'] = (int)(bool)$input['activo'];
        }

        // Obligatorio
        if (array_key_exists('obligatorio', $input)) {
            $data['obligatorio'] = (int)(bool)$input['obligatorio'];
        }

        // Vigencia
        if (array_key_exists('tiene_vencimiento', $input)) {
            $data['tiene_vencimiento'] = (int)(bool)$input['tiene_vencimiento'];
        }
        if (array_key_exists('dias_vigencia', $input)) {
            $dias = $input['dias_vigencia'];
            if ($dias !== null && $dias !== '') {
                $dias = (int)$dias;
                if ($dias < 0) {
                    throw new DomainException('Los días de vigencia no pueden ser negativos.');
                }
                $data['dias_vigencia'] = $dias === 0 ? null : $dias;
            } else {
                $data['dias_vigencia'] = null;
            }
        }

        // Orden
        if (array_key_exists('orden', $input)) {
            $data['orden'] = max(0, (int)$input['orden']);
        }

        if ($data === []) {
            throw new DomainException('No hay cambios para aplicar.');
        }

        $id = $this->repo->upsertOverride($codigo, $data);

        $this->audit->log(
            'documento_tipo.configurado',
            $usuarioId,
            'documento_tipo',
            $id,
            ['codigo' => $codigo, 'cambios' => array_keys($data)],
            ip: $ip,
        );

        return $id;
    }
}
