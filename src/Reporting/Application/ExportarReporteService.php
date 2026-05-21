<?php

declare(strict_types=1);

namespace DigiSecure\Reporting\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Genera reportes CSV del tenant para descarga (Fase 10).
 *
 * - Fichas: listado completo con datos principales
 * - Vencimientos: documentos que vencen en una ventana de días
 * - Expedientes: resumen de estado documental por guardia
 *
 * Todas las queries filtran por tenant_id. Cada exportación se audita.
 * BOM UTF-8 para compatibilidad con Excel.
 */
final class ExportarReporteService
{
    private const MAX_ROWS = 50000;

    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function exportarFichas(string $usuarioId, string $ip): array
    {
        $tid = $this->tenantContext->requireTenantId();

        $rows = $this->db->fetchAll(
            "SELECT f.correlativo, f.tipo_ficha, f.estado, f.fecha_emision,
                    f.nombres, f.apellidos, f.genero, f.fecha_nacimiento,
                    f.celular, f.email,
                    TRIM(CONCAT_WS(' ', f.domicilio_calle, f.domicilio_numero, f.domicilio_barrio)) AS direccion_domicilio,
                    f.domicilio_ciudad AS ciudad,
                    f.lugar_nacimiento_provincia AS provincia,
                    COALESCE(
                        NULLIF(f.instruccion_superior, ''),
                        NULLIF(f.instruccion_secundaria, ''),
                        NULLIF(f.instruccion_primaria, '')
                    ) AS nivel_instruccion,
                    f.estado_civil,
                    f.created_at, f.updated_at
             FROM fichas f
             WHERE f.tenant_id = :tid
             ORDER BY f.created_at DESC
             LIMIT :lim",
            ['tid' => $tid, 'lim' => self::MAX_ROWS],
        );

        $headers = [
            'Correlativo', 'Tipo ficha', 'Estado', 'Fecha emisión',
            'Nombres', 'Apellidos', 'Género', 'Fecha nacimiento',
            'Celular', 'Email', 'Dirección', 'Ciudad',
            'Provincia', 'Nivel instrucción', 'Estado civil',
            'Creada', 'Actualizada',
        ];

        $keys = [
            'correlativo', 'tipo_ficha', 'estado', 'fecha_emision',
            'nombres', 'apellidos', 'genero', 'fecha_nacimiento',
            'celular', 'email', 'direccion_domicilio', 'ciudad',
            'provincia', 'nivel_instruccion', 'estado_civil',
            'created_at', 'updated_at',
        ];

        $csv = $this->buildCsv($headers, $rows, $keys);
        $filename = 'digisecure_fichas_' . date('Ymd_His') . '.csv';

        $this->audit->log(
            'reporte.exportar.fichas',
            $usuarioId,
            'reporte',
            null,
            detalles: ['registros' => count($rows), 'formato' => 'csv'],
            nivel: 'info',
            ip: $ip,
        );

        return ['filename' => $filename, 'content' => $csv];
    }

    /**
     * @param int $dias Ventana hacia adelante en días (30/60/90)
     * @return array{filename: string, content: string}
     */
    public function exportarVencimientos(int $dias, string $usuarioId, string $ip): array
    {
        $tid = $this->tenantContext->requireTenantId();
        $diasClamp = max(1, min($dias, 365));

        $rows = $this->db->fetchAll(
            "SELECT f.correlativo, f.apellidos, f.nombres, f.celular, f.email,
                    dt.codigo AS tipo_codigo, dt.nombre AS tipo_nombre, dt.bloque,
                    ed.estado AS estado_documento,
                    ed.fecha_emision_documento, ed.fecha_vencimiento,
                    DATEDIFF(ed.fecha_vencimiento, CURDATE()) AS dias_restantes
             FROM expediente_documentos ed
             INNER JOIN documento_tipos dt ON dt.id = ed.documento_tipo_id
             INNER JOIN expedientes e ON e.id = ed.expediente_id
             INNER JOIN fichas f ON f.id = e.ficha_id
             WHERE ed.tenant_id = :tid
               AND ed.fecha_vencimiento IS NOT NULL
               AND ed.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL :dias DAY)
               AND ed.estado IN ('ACEPTADO','VENCIDO')
             ORDER BY ed.fecha_vencimiento ASC
             LIMIT :lim",
            ['tid' => $tid, 'dias' => $diasClamp, 'lim' => self::MAX_ROWS],
        );

        $headers = [
            'Correlativo', 'Apellidos', 'Nombres', 'Celular', 'Email',
            'Código documento', 'Nombre documento', 'Bloque',
            'Estado', 'Fecha emisión', 'Fecha vencimiento', 'Días restantes',
        ];

        $keys = [
            'correlativo', 'apellidos', 'nombres', 'celular', 'email',
            'tipo_codigo', 'tipo_nombre', 'bloque',
            'estado_documento', 'fecha_emision_documento', 'fecha_vencimiento', 'dias_restantes',
        ];

        $csv = $this->buildCsv($headers, $rows, $keys);
        $filename = 'digisecure_vencimientos_' . $diasClamp . 'd_' . date('Ymd_His') . '.csv';

        $this->audit->log(
            'reporte.exportar.vencimientos',
            $usuarioId,
            'reporte',
            null,
            detalles: ['dias' => $diasClamp, 'registros' => count($rows)],
            nivel: 'info',
            ip: $ip,
        );

        return ['filename' => $filename, 'content' => $csv];
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function exportarExpedientes(string $usuarioId, string $ip): array
    {
        $tid = $this->tenantContext->requireTenantId();

        $rows = $this->db->fetchAll(
            "SELECT f.correlativo, f.apellidos, f.nombres, f.estado AS ficha_estado,
                    e.estado AS expediente_estado,
                    CASE
                        WHEN e.bloque_a_total > 0 AND e.bloque_a_aceptados >= e.bloque_a_total THEN 1
                        ELSE 0
                    END AS bloque_a_completado,
                    e.bloque_b_desbloqueado,
                    CASE
                        WHEN e.bloque_b_total > 0 AND e.bloque_b_aceptados >= e.bloque_b_total THEN 1
                        ELSE 0
                    END AS bloque_b_completado,
                    (SELECT COUNT(*) FROM expediente_documentos ed
                        WHERE ed.expediente_id = e.id AND ed.tenant_id = :tid2 AND ed.estado = 'ACEPTADO') AS docs_aceptados,
                    (SELECT COUNT(*) FROM expediente_documentos ed
                        WHERE ed.expediente_id = e.id AND ed.tenant_id = :tid3 AND ed.estado = 'EN_REVISION') AS docs_en_revision,
                    (SELECT COUNT(*) FROM expediente_documentos ed
                        WHERE ed.expediente_id = e.id AND ed.tenant_id = :tid4 AND ed.estado = 'RECHAZADO') AS docs_rechazados,
                    (SELECT COUNT(*) FROM expediente_documentos ed
                        WHERE ed.expediente_id = e.id AND ed.tenant_id = :tid5 AND ed.estado = 'VENCIDO') AS docs_vencidos,
                    e.created_at, e.updated_at
             FROM expedientes e
             INNER JOIN fichas f ON f.id = e.ficha_id
             WHERE e.tenant_id = :tid
             ORDER BY f.apellidos, f.nombres
             LIMIT :lim",
            [
                'tid' => $tid,
                'tid2' => $tid, 'tid3' => $tid, 'tid4' => $tid, 'tid5' => $tid,
                'lim' => self::MAX_ROWS,
            ],
        );

        $headers = [
            'Correlativo', 'Apellidos', 'Nombres', 'Estado ficha',
            'Estado expediente', 'Bloque A completado', 'Bloque B desbloqueado', 'Bloque B completado',
            'Docs aceptados', 'Docs en revisión', 'Docs rechazados', 'Docs vencidos',
            'Creado', 'Actualizado',
        ];

        $keys = [
            'correlativo', 'apellidos', 'nombres', 'ficha_estado',
            'expediente_estado', 'bloque_a_completado', 'bloque_b_desbloqueado', 'bloque_b_completado',
            'docs_aceptados', 'docs_en_revision', 'docs_rechazados', 'docs_vencidos',
            'created_at', 'updated_at',
        ];

        $csv = $this->buildCsv($headers, $rows, $keys);
        $filename = 'digisecure_expedientes_' . date('Ymd_His') . '.csv';

        $this->audit->log(
            'reporte.exportar.expedientes',
            $usuarioId,
            'reporte',
            null,
            detalles: ['registros' => count($rows)],
            nivel: 'info',
            ip: $ip,
        );

        return ['filename' => $filename, 'content' => $csv];
    }

    /**
     * @param list<string>              $headers
     * @param list<array<string,mixed>> $rows
     * @param list<string>              $keys
     */
    private function buildCsv(array $headers, array $rows, array $keys): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        // BOM UTF-8 para Excel
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $k) {
                $value = $row[$k] ?? '';
                if (is_bool($value)) {
                    $value = $value ? 'Sí' : 'No';
                } elseif ($value === null) {
                    $value = '';
                }
                $line[] = (string) $value;
            }
            fputcsv($handle, $line, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content !== false ? $content : '';
    }
}
