<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Application;

use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: descargar un documento del expediente (Fase 10).
 *
 * Garantías:
 *  - El documento pertenece al tenant actual (findDocumentoById ya filtra)
 *  - El archivo físico existe en el storage
 *  - Se audita cada descarga con quién/cuándo/IP
 *
 * Devuelve un DTO con los metadatos necesarios para streamear al cliente;
 * el controller se encarga de construir la Response HTTP.
 */
final class DescargarDocumentoService
{
    public function __construct(
        private readonly ExpedienteRepository $repo,
        private readonly AuditLogger $audit,
        private readonly string $storagePath,
    ) {
    }

    /**
     * @return array{
     *     path: string,
     *     nombre_descarga: string,
     *     mime: string,
     *     tamano: int,
     *     etag: string,
     *     doc: array<string, mixed>,
     * }
     */
    public function execute(string $docId, string $usuarioId, string $ip): array
    {
        $doc = $this->repo->findDocumentoById($docId);
        if ($doc === null) {
            throw new DomainException('Documento no encontrado o no pertenece a esta empresa.');
        }

        $storageName = (string) ($doc['archivo_nombre_storage'] ?? '');
        if ($storageName === '') {
            throw new DomainException('Este documento no tiene un archivo adjunto.');
        }

        // Validar nombre del storage (solo alfanuméricos + extensión) para prevenir path traversal
        if (!preg_match('/^[a-f0-9]+\.[a-zA-Z0-9]{2,5}$/', $storageName)) {
            throw new DomainException('Nombre de archivo en storage inválido.');
        }

        $path = $this->storagePath . '/' . substr($storageName, 0, 2) . '/' . $storageName;

        // Defensa en profundidad: el path resuelto debe estar dentro del storagePath
        $realBase = realpath($this->storagePath);
        $realFile = realpath($path);
        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            throw new DomainException('El archivo no se encuentra disponible en el storage.');
        }
        if (!is_file($realFile) || !is_readable($realFile)) {
            throw new DomainException('El archivo no existe físicamente en el storage.');
        }

        $nombreOriginal = (string) ($doc['archivo_nombre_original'] ?? 'documento');
        $nombreDescarga = $this->buildDownloadName(
            (string) ($doc['tipo_codigo'] ?? 'documento'),
            $nombreOriginal,
        );

        $mime = (string) ($doc['archivo_mime'] ?? 'application/octet-stream');
        $tamano = (int) ($doc['archivo_tamano_bytes'] ?? filesize($realFile));

        // ETag débil basado en el storage name + mtime (archivos inmutables en storage)
        $etag = '"' . substr(sha1($storageName . filemtime($realFile)), 0, 24) . '"';

        $this->audit->log(
            'expediente.documento.descargar',
            $usuarioId,
            'expediente_documento',
            $docId,
            detalles: [
                'tipo_codigo' => $doc['tipo_codigo'] ?? null,
                'archivo' => $nombreOriginal,
                'tamano_bytes' => $tamano,
            ],
            nivel: 'info',
            ip: $ip,
        );

        return [
            'path' => $realFile,
            'nombre_descarga' => $nombreDescarga,
            'mime' => $mime,
            'tamano' => $tamano,
            'etag' => $etag,
            'doc' => $doc,
        ];
    }

    /**
     * Construye un nombre de descarga seguro: "{tipo_codigo}-{nombre_original}".
     * Quita caracteres peligrosos y limita longitud.
     */
    private function buildDownloadName(string $tipoCodigo, string $nombreOriginal): string
    {
        $base = pathinfo($nombreOriginal, PATHINFO_FILENAME);
        $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);

        $baseSlug = preg_replace('/[^A-Za-z0-9._\- ]+/', '', $base) ?? '';
        $baseSlug = trim(preg_replace('/\s+/', '_', $baseSlug) ?? '');
        $baseSlug = substr($baseSlug !== '' ? $baseSlug : 'documento', 0, 80);

        $tipoSlug = preg_replace('/[^A-Za-z0-9_]+/', '_', $tipoCodigo) ?? 'doc';
        $extSlug = preg_replace('/[^A-Za-z0-9]+/', '', $ext) ?? '';

        $name = $tipoSlug . '-' . $baseSlug;
        if ($extSlug !== '') {
            $name .= '.' . strtolower($extSlug);
        }
        return $name;
    }
}
