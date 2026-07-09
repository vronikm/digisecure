<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Application;

use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: Subir un documento al expediente de un guardia.
 *
 * Responsabilidades:
 *  1. Validar que el expediente existe y el tipo de documento es válido
 *  2. Validar archivo (extensión, magic bytes, tamaño)
 *  3. Mover archivo a storage cifrado con nombre aleatorio
 *  4. Calcular fecha_vencimiento si el tipo tiene vigencia
 *  5. Crear o actualizar el registro en expediente_documentos
 *  6. Verificar si se debe desbloquear Bloque B
 */
final class SubirDocumentoService
{
    private const DIAS_ANTES_RENOVACION = 30;

    public function __construct(
        private readonly ExpedienteRepository $repo,
        private readonly AuditLogger $audit,
        private readonly string $storagePath,
    ) {
    }

    /**
     * @param array<string, mixed> $input  Datos del formulario
     * @param array<string, mixed> $file   Datos del archivo ($_FILES entry)
     * @return string ID del documento creado/actualizado
     * @throws DomainException
     */
    public function execute(
        string $expedienteId,
        string $documentoTipoId,
        array $input,
        array $file,
        string $usuarioId,
        string $ip,
    ): string {
        // 1. Validar expediente
        $expediente = $this->repo->findExpedienteById($expedienteId);
        if ($expediente === null) {
            throw new DomainException('Expediente no encontrado.');
        }

        // 2. Validar tipo de documento
        $tipo = $this->repo->findDocumentoTipoById($documentoTipoId);
        if ($tipo === null) {
            throw new DomainException('Tipo de documento no válido.');
        }

        // 3. Verificar bloque: si es Bloque B, debe estar desbloqueado
        if ($tipo['bloque'] === 'B' && !$this->bloqueAActivoCompleto($expedienteId)) {
            throw new DomainException('El Bloque B no está desbloqueado. Complete primero el Bloque A.');
        }

        // 4. Validar archivo
        $this->validateFile($file, $tipo);

        // 5. Mover archivo a storage
        $storageFilename = $this->storeFile($file);

        // 6. Calcular vencimiento
        $fechaEmision = !empty($input['fecha_emision_documento'])
            ? $this->normalizarFecha((string) $input['fecha_emision_documento'], 'Fecha de emision no valida.')
            : null;
        $fechaVencimiento = $this->calcularVencimiento($tipo, $fechaEmision, $input['fecha_vencimiento'] ?? null);
        $fechaObjetivoActualizacion = $this->calcularObjetivoActualizacion($fechaVencimiento, $fechaEmision);

        // 7. Crear o actualizar documento
        $existente = $this->repo->findDocumentoByTipo($expedienteId, $documentoTipoId);

        $data = [
            'expediente_id' => $expedienteId,
            'documento_tipo_id' => $documentoTipoId,
            'archivo_nombre_original' => $file['name'],
            'archivo_nombre_storage' => $storageFilename,
            'archivo_mime' => $file['type'],
            'archivo_tamano_bytes' => $file['size'],
            'estado' => EstadoDocumento::EN_REVISION->value,
            'fecha_emision_documento' => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_objetivo_actualizacion' => $fechaObjetivoActualizacion,
            'subido_por' => $usuarioId,
            'subido_at' => date('Y-m-d H:i:s.u'),
            'observacion_revisor' => null,
            'revisado_por' => null,
            'revisado_at' => null,
        ];

        // Screenshots de validación externa
        if (!empty($input['validacion_url'])) {
            $data['validacion_url'] = $input['validacion_url'];
        }

        if ($existente !== null) {
            $docId = $existente['id'];
            // Eliminar archivo anterior del storage
            $this->deleteStorageFile($existente['archivo_nombre_storage'] ?? '');
            $this->repo->updateDocumento($docId, $data);
        } else {
            $docId = $this->repo->createDocumento($data);
        }

        // 8. Auditoría
        $this->audit->log(
            'expediente.documento.subir',
            $usuarioId,
            'expediente_documento',
            $docId,
            detalles: [
                'expediente_id' => $expedienteId,
                'tipo_codigo' => $tipo['codigo'],
                'archivo' => $file['name'],
            ],
            ip: $ip,
        );

        return $docId;
    }

    private function validateFile(array $file, array $tipo): void
    {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new DomainException('Error al subir el archivo.');
        }

        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $permitidos = array_map('trim', explode(',', $tipo['formatos_permitidos']));
        if (!in_array($ext, $permitidos, true)) {
            throw new DomainException("Formato no permitido ({$ext}). Permitidos: {$tipo['formatos_permitidos']}.");
        }

        // Validar tamaño
        $maxBytes = (int) ($tipo['tamano_maximo_mb'] * 1024 * 1024);
        if ($file['size'] > $maxBytes) {
            throw new DomainException("El archivo excede el tamaño máximo de {$tipo['tamano_maximo_mb']} MB.");
        }

        // Validar magic bytes con finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($file['tmp_name']);
        $mimesPermitidos = $this->extensionesToMimes($permitidos);
        if (!in_array($mimeReal, $mimesPermitidos, true)) {
            throw new DomainException("El contenido del archivo no coincide con el tipo declarado.");
        }
    }

    private function bloqueAActivoCompleto(string $expedienteId): bool
    {
        $resumen = $this->repo->resumenActivosByBloque($expedienteId, 'A');
        return $resumen['total'] > 0 && $resumen['aceptados'] >= $resumen['total'];
    }

    private function storeFile(array $file): string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(20)) . '.' . $ext;
        $destDir = $this->storagePath . '/' . substr($filename, 0, 2);

        if (!is_dir($destDir)) {
            mkdir($destDir, 0750, true);
        }

        $dest = $destDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new DomainException('No se pudo guardar el archivo en el storage.');
        }

        return $filename;
    }

    private function deleteStorageFile(string $filename): void
    {
        if ($filename === '') {
            return;
        }
        $path = $this->storagePath . '/' . substr($filename, 0, 2) . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function calcularVencimiento(array $tipo, ?string $fechaEmision, ?string $fechaVencimientoManual): ?string
    {
        // Si el usuario proporcionó fecha manual, usar esa
        if (!empty($fechaVencimientoManual)) {
            return $this->normalizarFecha($fechaVencimientoManual, 'Fecha de vencimiento no valida.');
        }

        // Si el tipo no tiene vencimiento, null
        if (!$tipo['tiene_vencimiento'] || empty($tipo['dias_vigencia'])) {
            return null;
        }

        // Calcular desde fecha de emisión del documento
        $base = !empty($fechaEmision) ? $fechaEmision : date('Y-m-d');
        $dt = new \DateTimeImmutable($this->normalizarFecha($base, 'Fecha de emision no valida.'));
        return $dt->modify("+{$tipo['dias_vigencia']} days")->format('Y-m-d');
    }

    private function calcularObjetivoActualizacion(?string $fechaVencimiento, ?string $fechaEmision): ?string
    {
        if ($fechaVencimiento === null || $fechaVencimiento === '') {
            return null;
        }

        $vence = new \DateTimeImmutable($this->normalizarFecha($fechaVencimiento, 'Fecha de vencimiento no valida.'));
        $objetivo = $vence->modify('-' . self::DIAS_ANTES_RENOVACION . ' days');

        $minima = new \DateTimeImmutable('today');
        if ($fechaEmision !== null && $fechaEmision !== '') {
            $emision = new \DateTimeImmutable($this->normalizarFecha($fechaEmision, 'Fecha de emision no valida.'));
            if ($emision > $minima) {
                $minima = $emision;
            }
        }

        if ($objetivo < $minima) {
            $objetivo = $minima;
        }

        return $objetivo->format('Y-m-d');
    }

    private function normalizarFecha(string $fecha, string $mensajeError): string
    {
        $fecha = trim($fecha);
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $dt === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new DomainException($mensajeError);
        }

        return $dt->format('Y-m-d');
    }

    /**
     * @param list<string> $extensiones
     * @return list<string>
     */
    private function extensionesToMimes(array $extensiones): array
    {
        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        $mimes = [];
        foreach ($extensiones as $ext) {
            if (isset($map[$ext])) {
                $mimes[] = $map[$ext];
            }
        }
        return array_unique($mimes);
    }
}
