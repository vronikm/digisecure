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
    private const FORMATOS_PERMITIDOS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

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

        if (array_key_exists('numero_documentos', $input)) {
            $data['numero_documentos'] = $this->normalizarNumeroDocumentos($input['numero_documentos']);
        }

        if (array_key_exists('requiere_validacion_externa', $input)) {
            $data['requiere_validacion_externa'] = (int)(bool)$input['requiere_validacion_externa'];
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

    /**
     * @param array<string, mixed> $input
     */
    public function crear(array $input, string $usuarioId, string $ip): string
    {
        $codigo = $this->normalizarCodigo((string) ($input['codigo'] ?? ''));
        if ($codigo === '') {
            throw new DomainException('El codigo del tipo de documento es obligatorio.');
        }

        if ($this->repo->findByCodigo($codigo) !== null) {
            throw new DomainException("Ya existe un tipo de documento con el codigo '{$codigo}'.");
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new DomainException('El nombre del tipo de documento es obligatorio.');
        }

        $bloque = strtoupper(trim((string) ($input['bloque'] ?? 'A')));
        if (!in_array($bloque, ['A', 'B'], true)) {
            throw new DomainException('El bloque debe ser A o B.');
        }

        $tieneVencimiento = (int) (bool) ($input['tiene_vencimiento'] ?? false);
        $diasVigencia = null;
        if ($tieneVencimiento === 1) {
            $diasVigencia = (int) ($input['dias_vigencia'] ?? 0);
            if ($diasVigencia < 1) {
                throw new DomainException('Ingrese los dias de vigencia del documento.');
            }
        }

        $formatos = $this->normalizarFormatos((string) ($input['formatos_permitidos'] ?? 'pdf'));
        $tamanoMaximo = (float) ($input['tamano_maximo_mb'] ?? 10);
        if ($tamanoMaximo <= 0 || $tamanoMaximo > 50) {
            throw new DomainException('El tamano maximo debe estar entre 1 y 50 MB.');
        }

        $id = $this->repo->createTenantTipo([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')) ?: null,
            'bloque' => $bloque,
            'orden' => max(0, (int) ($input['orden'] ?? 0)),
            'obligatorio' => (int) (bool) ($input['obligatorio'] ?? false),
            'numero_documentos' => $this->normalizarNumeroDocumentos($input['numero_documentos'] ?? 1),
            'requiere_validacion_externa' => (int) (bool) ($input['requiere_validacion_externa'] ?? false),
            'tiene_vencimiento' => $tieneVencimiento,
            'dias_vigencia' => $diasVigencia,
            'formatos_permitidos' => implode(',', $formatos),
            'tamano_maximo_mb' => $tamanoMaximo,
            'activo' => (int) (bool) ($input['activo'] ?? true),
        ]);

        $this->audit->log(
            'documento_tipo.creado',
            $usuarioId,
            'documento_tipo',
            $id,
            ['codigo' => $codigo, 'bloque' => $bloque],
            ip: $ip,
        );

        return $id;
    }

    private function normalizarCodigo(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        $codigo = preg_replace('/[^A-Z0-9_]+/', '_', $codigo) ?? '';
        $codigo = trim($codigo, '_');
        return substr($codigo, 0, 50);
    }

    private function normalizarNumeroDocumentos(mixed $value): int
    {
        $numero = (int) $value;
        if ($numero < 1 || $numero > 20) {
            throw new DomainException('El numero de documentos debe estar entre 1 y 20.');
        }

        return $numero;
    }

    /**
     * @return list<string>
     */
    private function normalizarFormatos(string $formatos): array
    {
        $parts = preg_split('/[,\s]+/', strtolower($formatos)) ?: [];
        $parts = array_values(array_unique(array_filter(array_map('trim', $parts))));
        if ($parts === []) {
            throw new DomainException('Debe seleccionar al menos un formato permitido.');
        }

        foreach ($parts as $ext) {
            if (!in_array($ext, self::FORMATOS_PERMITIDOS, true)) {
                throw new DomainException("Formato no permitido: {$ext}.");
            }
        }

        return $parts;
    }
}
