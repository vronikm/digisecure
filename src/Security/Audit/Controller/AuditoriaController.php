<?php

declare(strict_types=1);

namespace DigiSecure\Security\Audit\Controller;

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

        $page = max(1, (int) $request->query('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $filters = array_filter([
            'evento' => $request->query('evento'),
            'nivel' => $request->query('nivel'),
            'usuario_id' => $request->query('usuario_id'),
            'entidad_tipo' => $request->query('entidad_tipo'),
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
        ], static fn($v) => $v !== null && $v !== '');

        $eventos = $this->repo->listByTenant($filters, $limit, $offset);
        $total = $this->repo->countByTenant($filters);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->view->response('auditoria/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'eventos' => $eventos,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'eventosDisponibles' => $this->repo->distinctEventos(),
        ]);
    }

    /**
     * GET /auditoria/exportar — descarga CSV con los filtros actuales.
     */
    public function exportar(Request $request): Response
    {
        $filters = array_filter([
            'evento' => $request->query('evento'),
            'nivel' => $request->query('nivel'),
            'usuario_id' => $request->query('usuario_id'),
            'entidad_tipo' => $request->query('entidad_tipo'),
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
        ], static fn($v) => $v !== null && $v !== '');

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
        ]);

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
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content !== false ? $content : '';
    }
}
