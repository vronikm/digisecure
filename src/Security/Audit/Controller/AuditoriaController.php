<?php

declare(strict_types=1);

namespace DigiSecure\Security\Audit\Controller;

use DigiSecure\Kernel\Http\Paginacion;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Audit\AuditoriaRepository;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Visor de auditoría: listado filtrable + exportación CSV.
 *
 * GET /auditoria          → tabla paginada con filtros (evento, nivel, fechas).
 * GET /auditoria/exportar → descarga CSV con los mismos filtros activos.
 */
final class AuditoriaController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AuditoriaRepository $repo,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $filters = $this->filtros($request);
        $paginacion = Paginacion::desdeRequest($request, 50)->conTotal($this->repo->countByTenant($filters));
        $data = [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'eventos' => $this->repo->listByTenant($filters, $paginacion->limite(), $paginacion->offset()),
            'paginacion' => $paginacion,
            'filters' => $filters,
        ];

        // Busqueda, filtros y paginacion en vivo (ds-tabla-remota.js): solo la tabla.
        if ($request->header('X-Partial') === 'auditoria') {
            return Response::html($this->view->render('auditoria/_listado', $data));
        }

        return $this->view->response('auditoria/index', $data + [
            'eventosDisponibles' => $this->repo->distinctEventos(),
        ]);
    }

    /**
     * GET /auditoria/exportar — descarga CSV con los filtros actuales.
     */
    public function exportar(Request $request): Response
    {
        $filters = $this->filtros($request);
        $rows = $this->repo->exportByTenant($filters);

        $csv = $this->buildCsv($rows);
        $filename = 'auditoria_' . date('Ymd_His') . '.csv';

        return new Response(
            body: $csv,
            status: 200,
            headers: [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-store',
            ],
        );
    }

    /**
     * Filtros comunes del listado y del CSV (asi la exportacion respeta la busqueda).
     *
     * @return array<string, string>
     */
    private function filtros(Request $request): array
    {
        return array_filter([
            'q' => trim((string) $request->query('q', '')),
            'evento' => (string) $request->query('evento', ''),
            'nivel' => (string) $request->query('nivel', ''),
            'usuario_id' => (string) $request->query('usuario_id', ''),
            'entidad_tipo' => (string) $request->query('entidad_tipo', ''),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ], static fn(string $v): bool => $v !== '');
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function buildCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        // BOM UTF-8 for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Header
        fputcsv($handle, [
            'Fecha', 'Nivel', 'Evento', 'Usuario', 'Email',
            'Entidad tipo', 'Entidad ID', 'IP', 'Detalles',
        ], escape: '');

        foreach ($rows as $row) {
            $usuario = trim(($row['usuario_apellidos'] ?? '') . ' ' . ($row['usuario_nombres'] ?? ''));
            fputcsv($handle, [
                $row['created_at'],
                $row['nivel'],
                $row['evento'],
                $usuario ?: '—',
                $row['usuario_email'] ?? '—',
                $row['entidad_tipo'] ?? '',
                $row['entidad_id'] ?? '',
                $row['ip_text'] ?? '',
                $row['detalles_json'] ?? '',
            ], escape: '');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content !== false ? $content : '';
    }
}
