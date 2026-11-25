<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Application;

use DigiSecure\Ficha\Infrastructure\FichaEmpleado;
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
     * Cada alerta depende del permiso del modulo que la origina; el
     * superadministrador tiene todos los permisos y por eso las ve todas.
     *
     * @return array{items: list<array<string, mixed>>, total: int, criticas: int}
     */
    public function listarPara(AuthenticatedUser $user): array
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
            // Solo empleados: los borradores de aspirantes los llena el propio
            // aspirante y no aparecen en Guardias.
            $fichasBorrador = $this->contarFichasPorEstado($tid, 'BORRADOR', aspirantes: false);
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

        if ($user->tienePermiso('aspirantes.gestionar')) {
            // Fichas que un aspirante envio a revision: el personal debe
            // revisarlas y completarlas desde Reclutamiento.
            $fichasAspirantes = $this->contarFichasPorEstado($tid, 'EN_REVISION', aspirantes: true);
            if ($fichasAspirantes > 0) {
                $items[] = [
                    'severidad' => 'media',
                    'icon' => 'bi-person-vcard',
                    'color' => 'info',
                    'titulo' => 'Fichas de aspirantes por revisar',
                    'detalle' => "{$fichasAspirantes} aspirante(s) esperan revisión de su ficha",
                    'cantidad' => $fichasAspirantes,
                    'url' => '/reclutamiento?avance=revision',
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

    /**
     * @param bool $aspirantes true = solo fichas de aspirantes sin contratar;
     *                         false = solo fichas de empleados (FichaEmpleado).
     */
    private function contarFichasPorEstado(string $tid, string $estado, bool $aspirantes): int
    {
        $criterio = $aspirantes ? FichaEmpleado::esDeAspirante('f') : FichaEmpleado::condicion('f');

        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM fichas f WHERE f.tenant_id = :tid AND f.estado = :estado AND {$criterio}",
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
}
