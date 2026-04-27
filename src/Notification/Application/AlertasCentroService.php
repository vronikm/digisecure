<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Auth\AuthenticatedUser;

/**
 * Agrega alertas accionables del usuario actual para el centro de notificaciones
 * del topbar. No persiste estado de lectura: es una vista de situación en vivo.
 *
 * Cada item tiene: severidad (critica|alta|media|info), icono, titulo,
 * detalle, url destino y cantidad.
 */
final class AlertasCentroService
{
    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, criticas: int}
     */
    public function listarPara(AuthenticatedUser $user): array
    {
        if ($user->esSuperAdmin()) {
            return $this->alertasSuperadmin();
        }

        return $this->alertasTenant($user);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, criticas: int}
     */
    private function alertasTenant(AuthenticatedUser $user): array
    {
        $tid = $user->tenantId;
        $items = [];

        if ($user->tienePermiso('expedientes.ver')) {
            $vencidos = $this->contarDocsVencidos($tid);
            if ($vencidos > 0) {
                $items[] = [
                    'severidad' => 'critica',
                    'icon' => 'bi-exclamation-octagon-fill',
                    'color' => 'danger',
                    'titulo' => 'Documentos vencidos',
                    'detalle' => "{$vencidos} documento(s) requieren reemplazo inmediato",
                    'cantidad' => $vencidos,
                    'url' => '/expedientes',
                ];
            }

            $porVencer = $this->contarDocsPorVencer($tid, 30);
            if ($porVencer > 0) {
                $items[] = [
                    'severidad' => 'alta',
                    'icon' => 'bi-clock-history',
                    'color' => 'warning',
                    'titulo' => 'Documentos por vencer',
                    'detalle' => "{$porVencer} documento(s) vencen en los proximos 30 dias",
                    'cantidad' => $porVencer,
                    'url' => '/expedientes',
                ];
            }
        }

        if ($user->tienePermiso('expedientes.validar')) {
            $porRevisar = $this->contarDocsPorRevisar($tid);
            if ($porRevisar > 0) {
                $items[] = [
                    'severidad' => 'alta',
                    'icon' => 'bi-clipboard-check',
                    'color' => 'info',
                    'titulo' => 'Documentos por validar',
                    'detalle' => "{$porRevisar} carga(s) esperan revision",
                    'cantidad' => $porRevisar,
                    'url' => '/expedientes/pendientes',
                ];
            }
        }

        if ($user->tienePermiso('guardias.ver')) {
            $fichasBorrador = $this->contarFichasPorEstado($tid, 'BORRADOR');
            if ($fichasBorrador > 0) {
                $items[] = [
                    'severidad' => 'info',
                    'icon' => 'bi-pencil-square',
                    'color' => 'secondary',
                    'titulo' => 'Fichas en borrador',
                    'detalle' => "{$fichasBorrador} ficha(s) sin completar",
                    'cantidad' => $fichasBorrador,
                    'url' => '/guardias',
                ];
            }
        }

        if ($user->tienePermiso('lopdp.gestionar')) {
            $solicitudes = $this->contarSolicitudesLopdpPendientes($tid);
            if ($solicitudes > 0) {
                $items[] = [
                    'severidad' => 'alta',
                    'icon' => 'bi-file-earmark-lock',
                    'color' => 'primary',
                    'titulo' => 'Solicitudes ARCO-P',
                    'detalle' => "{$solicitudes} solicitud(es) LOPDP pendiente(s)",
                    'cantidad' => $solicitudes,
                    'url' => '/lopdp/solicitudes',
                ];
            }
        }

        if ($user->tienePermiso('suscripcion.ver')) {
            $diasRestantes = $this->diasParaVencimientoSuscripcion($tid);
            if ($diasRestantes !== null && $diasRestantes <= 15) {
                $sev = $diasRestantes <= 3 ? 'critica' : 'alta';
                $color = $diasRestantes <= 3 ? 'danger' : 'warning';
                $detalle = $diasRestantes <= 0
                    ? 'La suscripcion esta vencida'
                    : "La suscripcion vence en {$diasRestantes} dia(s)";
                $items[] = [
                    'severidad' => $sev,
                    'icon' => 'bi-gem',
                    'color' => $color,
                    'titulo' => 'Suscripcion por vencer',
                    'detalle' => $detalle,
                    'cantidad' => max(1, $diasRestantes),
                    'url' => '/suscripcion',
                ];
            }
        }

        return $this->empaquetar($items);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, criticas: int}
     */
    private function alertasSuperadmin(): array
    {
        $items = [];

        $pendientes = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM tenants WHERE estado = 'pendiente'",
        );
        if ($pendientes > 0) {
            $items[] = [
                'severidad' => 'alta',
                'icon' => 'bi-hourglass-split',
                'color' => 'warning',
                'titulo' => 'Tenants pendientes',
                'detalle' => "{$pendientes} empresa(s) sin activar",
                'cantidad' => $pendientes,
                'url' => '/admin/tenants?estado=pendiente',
            ];
        }

        $fallos = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM registros_empresa
             WHERE evento = 'fallo' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        );
        if ($fallos > 0) {
            $items[] = [
                'severidad' => 'media',
                'icon' => 'bi-bug',
                'color' => 'secondary',
                'titulo' => 'Registros fallidos',
                'detalle' => "{$fallos} intento(s) fallido(s) en los ultimos 7 dias",
                'cantidad' => $fallos,
                'url' => '/admin/registros',
            ];
        }

        $suspendidos = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM tenants WHERE estado = 'suspendido'",
        );
        if ($suspendidos > 0) {
            $items[] = [
                'severidad' => 'media',
                'icon' => 'bi-slash-circle',
                'color' => 'danger',
                'titulo' => 'Tenants suspendidos',
                'detalle' => "{$suspendidos} empresa(s) suspendida(s)",
                'cantidad' => $suspendidos,
                'url' => '/admin/tenants?estado=suspendido',
            ];
        }

        $venciendo = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM tenants
             WHERE estado = 'activo'
               AND plan_vencimiento IS NOT NULL
               AND plan_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)",
        );
        if ($venciendo > 0) {
            $items[] = [
                'severidad' => 'alta',
                'icon' => 'bi-gem',
                'color' => 'info',
                'titulo' => 'Suscripciones por vencer',
                'detalle' => "{$venciendo} tenant(s) vencen en 15 dias",
                'cantidad' => $venciendo,
                'url' => '/admin/tenants',
            ];
        }

        return $this->empaquetar($items);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{items: list<array<string, mixed>>, total: int, criticas: int}
     */
    private function empaquetar(array $items): array
    {
        $prioridad = ['critica' => 0, 'alta' => 1, 'media' => 2, 'info' => 3];
        usort($items, static function (array $a, array $b) use ($prioridad): int {
            $pa = $prioridad[$a['severidad']] ?? 9;
            $pb = $prioridad[$b['severidad']] ?? 9;
            return $pa <=> $pb;
        });

        $criticas = 0;
        foreach ($items as $it) {
            if (\in_array($it['severidad'], ['critica', 'alta'], true)) {
                $criticas++;
            }
        }

        return [
            'items' => $items,
            'total' => \count($items),
            'criticas' => $criticas,
        ];
    }

    private function contarDocsVencidos(string $tid): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expediente_documentos
             WHERE tenant_id = :tid AND estado = 'VENCIDO'",
            ['tid' => $tid],
        );
    }

    private function contarDocsPorVencer(string $tid, int $dias): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expediente_documentos
             WHERE tenant_id = :tid AND estado = 'ACEPTADO'
               AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)",
            ['tid' => $tid, 'dias' => $dias],
        );
    }

    private function contarDocsPorRevisar(string $tid): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expediente_documentos
             WHERE tenant_id = :tid AND estado = 'SUBIDO'",
            ['tid' => $tid],
        );
    }

    private function contarFichasPorEstado(string $tid, string $estado): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM fichas WHERE tenant_id = :tid AND estado = :estado',
            ['tid' => $tid, 'estado' => $estado],
        );
    }

    private function contarSolicitudesLopdpPendientes(string $tid): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM lopdp_solicitudes_arcop
             WHERE tenant_id = :tid AND estado IN ('RECIBIDA','EN_PROCESO')",
            ['tid' => $tid],
        );
    }

    private function diasParaVencimientoSuscripcion(string $tid): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT DATEDIFF(plan_vencimiento, CURDATE()) AS dias
             FROM tenants WHERE id = :tid AND plan_vencimiento IS NOT NULL',
            ['tid' => $tid],
        );
        if ($row === null || $row['dias'] === null) {
            return null;
        }
        return (int) $row['dias'];
    }
}
