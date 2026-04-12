<?php

declare(strict_types=1);

namespace DigiSecure\Reporting\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Servicio de consultas para KPIs del dashboard y reportes.
 *
 * Queries optimizadas de solo lectura (CQRS read-side).
 * Todas las queries filtran por tenant_id.
 */
final class DashboardStatsService
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Estadísticas principales para el dashboard.
     *
     * @return array<string, mixed>
     */
    public function getDashboardStats(): array
    {
        $tid = $this->tenantContext->requireTenantId();

        return [
            'guardias_activos' => $this->countFichasByEstado($tid, 'COMPLETA'),
            'fichas_borrador' => $this->countFichasByEstado($tid, 'BORRADOR'),
            'fichas_revision' => $this->countFichasByEstado($tid, 'EN_REVISION'),
            'docs_por_vencer' => $this->countDocsPorVencer($tid, 30),
            'docs_vencidos' => $this->countDocsVencidos($tid),
            'expedientes_completos' => $this->countExpedientesByEstado($tid, 'COMPLETO'),
            'expedientes_incompletos' => $this->countExpedientesByEstado($tid, 'INCOMPLETO'),
            'actividad_reciente' => $this->getActividadReciente($tid, 10),
        ];
    }

    /**
     * Datos para gráficos de KPIs.
     *
     * @return array<string, mixed>
     */
    public function getKpiData(): array
    {
        $tid = $this->tenantContext->requireTenantId();

        return [
            'fichas_por_estado' => $this->fichasPorEstado($tid),
            'fichas_por_tipo' => $this->fichasPorTipo($tid),
            'expedientes_por_estado' => $this->expedientesPorEstado($tid),
            'docs_por_estado' => $this->docsPorEstado($tid),
            'fichas_por_mes' => $this->fichasPorMes($tid, 12),
            'docs_vencimiento_proximos' => $this->docsVencimientoProximos($tid, 90),
            'totales' => [
                'fichas' => $this->countTotalFichas($tid),
                'expedientes' => $this->countTotalExpedientes($tid),
                'docs_subidos' => $this->countTotalDocumentos($tid),
                'docs_aceptados' => $this->countDocsByEstado($tid, 'ACEPTADO'),
            ],
        ];
    }

    // ==================== QUERIES PRIVADAS ====================

    private function countFichasByEstado(string $tid, string $estado): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM fichas WHERE tenant_id = :tid AND estado = :estado',
            ['tid' => $tid, 'estado' => $estado],
        );
    }

    private function countTotalFichas(string $tid): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM fichas WHERE tenant_id = :tid',
            ['tid' => $tid],
        );
    }

    private function countDocsPorVencer(string $tid, int $dias): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expediente_documentos
             WHERE tenant_id = :tid AND estado = 'ACEPTADO'
               AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)",
            ['tid' => $tid, 'dias' => $dias],
        );
    }

    private function countDocsVencidos(string $tid): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expediente_documentos
             WHERE tenant_id = :tid AND estado = 'VENCIDO'",
            ['tid' => $tid],
        );
    }

    private function countExpedientesByEstado(string $tid, string $estado): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM expedientes WHERE tenant_id = :tid AND estado = :estado',
            ['tid' => $tid, 'estado' => $estado],
        );
    }

    private function countTotalExpedientes(string $tid): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM expedientes WHERE tenant_id = :tid',
            ['tid' => $tid],
        );
    }

    private function countTotalDocumentos(string $tid): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expediente_documentos WHERE tenant_id = :tid AND estado != 'PENDIENTE'",
            ['tid' => $tid],
        );
    }

    private function countDocsByEstado(string $tid, string $estado): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM expediente_documentos WHERE tenant_id = :tid AND estado = :estado',
            ['tid' => $tid, 'estado' => $estado],
        );
    }

    /**
     * @return list<array{fecha: string, evento: string, usuario: string}>
     */
    private function getActividadReciente(string $tid, int $limit): array
    {
        $rows = $this->db->fetchAll(
            "SELECT a.evento, a.created_at, u.nombres, u.apellidos
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.tenant_id = :tid
             ORDER BY a.created_at DESC
             LIMIT :lim",
            ['tid' => $tid, 'lim' => $limit],
        );

        return array_map(fn(array $r) => [
            'fecha' => date('d/m H:i', strtotime($r['created_at'])),
            'evento' => $this->humanizeEvento($r['evento']),
            'usuario' => trim(($r['nombres'] ?? '') . ' ' . ($r['apellidos'] ?? '')) ?: 'Sistema',
        ], $rows);
    }

    /**
     * @return array<string, int>
     */
    private function fichasPorEstado(string $tid): array
    {
        return $this->groupCount(
            'SELECT estado, COUNT(*) AS total FROM fichas WHERE tenant_id = :tid GROUP BY estado',
            $tid,
        );
    }

    /**
     * @return array<string, int>
     */
    private function fichasPorTipo(string $tid): array
    {
        return $this->groupCount(
            'SELECT tipo_ficha AS estado, COUNT(*) AS total FROM fichas WHERE tenant_id = :tid GROUP BY tipo_ficha',
            $tid,
        );
    }

    /**
     * @return array<string, int>
     */
    private function expedientesPorEstado(string $tid): array
    {
        return $this->groupCount(
            'SELECT estado, COUNT(*) AS total FROM expedientes WHERE tenant_id = :tid GROUP BY estado',
            $tid,
        );
    }

    /**
     * @return array<string, int>
     */
    private function docsPorEstado(string $tid): array
    {
        return $this->groupCount(
            'SELECT estado, COUNT(*) AS total FROM expediente_documentos WHERE tenant_id = :tid GROUP BY estado',
            $tid,
        );
    }

    /**
     * Fichas creadas por mes (últimos N meses).
     *
     * @return list<array{mes: string, total: int}>
     */
    private function fichasPorMes(string $tid, int $meses): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes, COUNT(*) AS total
             FROM fichas
             WHERE tenant_id = :tid AND created_at >= DATE_SUB(CURDATE(), INTERVAL :meses MONTH)
             GROUP BY mes ORDER BY mes ASC",
            ['tid' => $tid, 'meses' => $meses],
        );

        return array_map(fn(array $r) => [
            'mes' => $r['mes'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * Documentos que vencen en los próximos N días, agrupados por semana.
     *
     * @return list<array{semana: string, total: int}>
     */
    private function docsVencimientoProximos(string $tid, int $dias): array
    {
        $rows = $this->db->fetchAll(
            "SELECT YEARWEEK(fecha_vencimiento, 1) AS semana,
                    MIN(fecha_vencimiento) AS inicio_semana,
                    COUNT(*) AS total
             FROM expediente_documentos
             WHERE tenant_id = :tid AND estado = 'ACEPTADO'
               AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
             GROUP BY semana ORDER BY semana ASC",
            ['tid' => $tid, 'dias' => $dias],
        );

        return array_map(fn(array $r) => [
            'semana' => date('d/m', strtotime($r['inicio_semana'])),
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * @return array<string, int>
     */
    private function groupCount(string $sql, string $tid): array
    {
        $rows = $this->db->fetchAll($sql, ['tid' => $tid]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['estado']] = (int) $row['total'];
        }
        return $result;
    }

    private function humanizeEvento(string $evento): string
    {
        $map = [
            'auth.login_ok' => 'Inicio de sesión',
            'auth.logout' => 'Cierre de sesión',
            'auth.login_fallido' => 'Login fallido',
            'auth.acceso_prohibido' => 'Acceso denegado',
            'ficha.crear' => 'Ficha creada',
            'ficha.actualizar' => 'Ficha actualizada',
            'ficha.completar' => 'Ficha completada',
            'ficha.completar_rechazada' => 'Ficha rechazada (requisitos)',
            'expediente.documento.subir' => 'Documento subido',
            'expediente.documento.aceptar' => 'Documento aceptado',
            'expediente.documento.rechazar' => 'Documento rechazado',
            'expediente.documento.vencido_auto' => 'Documento vencido (auto)',
            'expediente.documento.validacion_externa' => 'Validación externa',
            'usuario.password.cambiar' => 'Contraseña cambiada',
        ];

        return $map[$evento] ?? str_replace('.', ' → ', $evento);
    }
}
