<?php

declare(strict_types=1);

namespace DigiSecure\Asignacion\Application;

use DigiSecure\Asignacion\Domain\Enum\EstadoAsignacion;
use DigiSecure\Asignacion\Infrastructure\AsignacionRepository;
use DigiSecure\Ficha\Infrastructure\FichaEmpleado;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Caso de uso: gestionar asignaciones guardia <-> puesto.
 *
 * Reglas:
 *  - Ficha debe existir, pertenecer al tenant y estar COMPLETA.
 *  - Puesto debe existir, pertenecer al tenant y estar activo.
 *  - fecha_inicio obligatoria, fecha_fin opcional (NULL = vigente).
 *  - fecha_fin >= fecha_inicio si se provee.
 *  - No puede solaparse con otra asignacion activa de la misma ficha.
 *  - El puesto no puede tener mas asignaciones activas que num_guardias_requeridos.
 *  - Editar permite cambiar fechas, rol_en_puesto y notas (no la ficha ni el puesto).
 *  - Finalizar/cancelar conserva historico, requiere motivo_fin.
 */
final class GestionarAsignacionesService
{
    public function __construct(
        private readonly AsignacionRepository $repo,
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function crear(array $input, string $usuarioId, string $ip): string
    {
        $fichaId = trim((string) ($input['ficha_id'] ?? ''));
        $puestoId = trim((string) ($input['puesto_id'] ?? ''));

        if ($fichaId === '' || $puestoId === '') {
            throw new DomainException('Debe seleccionar ficha y puesto.');
        }

        $this->validarFicha($fichaId);
        $this->validarPuesto($puestoId);

        [$fechaInicio, $fechaFin] = $this->validarPeriodo($input);
        $rolEnPuesto = $this->limit($input['rol_en_puesto'] ?? null, 80);
        $notas = $this->limit($input['notas'] ?? null, 5000);

        if ($this->repo->existeSolapeFicha($fichaId, $fechaInicio, $fechaFin)) {
            throw new DomainException('La ficha ya tiene una asignacion activa que se solapa con este periodo.');
        }

        $plazas = $this->plazasPuesto($puestoId);
        $ocupadas = $this->repo->contarActivasPorPuesto($puestoId);
        if ($ocupadas >= $plazas) {
            throw new DomainException("El puesto ya tiene cubiertas las {$plazas} plaza(s) requeridas.");
        }

        $id = IdGenerator::ulid();
        $now = Clock::nowString();

        $this->repo->crear([
            'id' => $id,
            'ficha_id' => $fichaId,
            'puesto_id' => $puestoId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'estado' => EstadoAsignacion::ACTIVA->value,
            'motivo_fin' => null,
            'finalizada_at' => null,
            'finalizada_por' => null,
            'rol_en_puesto' => $rolEnPuesto,
            'notas' => $notas,
            'created_by' => $usuarioId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit->log(
            evento: 'asignacion.creada',
            usuarioId: $usuarioId,
            entidadTipo: 'asignacion',
            entidadId: $id,
            detalles: [
                'ficha_id' => $fichaId,
                'puesto_id' => $puestoId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ],
            ip: $ip,
        );

        return $id;
    }

    /**
     * Editar campos no-criticos: fechas, rol_en_puesto, notas.
     * No permite cambiar ficha_id ni puesto_id (eso requeriria finalizar y crear nueva).
     *
     * @param array<string, mixed> $input
     */
    public function actualizar(string $id, array $input, string $usuarioId, string $ip): void
    {
        $asign = $this->repo->findById($id);
        if ($asign === null) {
            throw new DomainException('La asignacion no existe.');
        }
        if ($asign['estado'] !== EstadoAsignacion::ACTIVA->value) {
            throw new DomainException('Solo se pueden editar asignaciones activas.');
        }

        [$fechaInicio, $fechaFin] = $this->validarPeriodo($input);

        if ($this->repo->existeSolapeFicha((string) $asign['ficha_id'], $fechaInicio, $fechaFin, $id)) {
            throw new DomainException('Las nuevas fechas se solapan con otra asignacion activa de esta ficha.');
        }

        $data = [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'rol_en_puesto' => $this->limit($input['rol_en_puesto'] ?? null, 80),
            'notas' => $this->limit($input['notas'] ?? null, 5000),
            'updated_at' => Clock::nowString(),
        ];

        $this->repo->actualizar($id, $data);

        $this->audit->log(
            evento: 'asignacion.actualizada',
            usuarioId: $usuarioId,
            entidadTipo: 'asignacion',
            entidadId: $id,
            detalles: ['campos' => array_keys($data)],
            ip: $ip,
        );
    }

    /**
     * Finalizar (cierre normal) o cancelar (cierre anormal).
     */
    public function cerrar(string $id, string $accion, ?string $motivo, string $usuarioId, string $ip): void
    {
        if (!\in_array($accion, ['finalizar', 'cancelar'], true)) {
            throw new DomainException('Accion invalida.');
        }

        $asign = $this->repo->findById($id);
        if ($asign === null) {
            throw new DomainException('La asignacion no existe.');
        }
        if ($asign['estado'] !== EstadoAsignacion::ACTIVA->value) {
            throw new DomainException('Solo se pueden cerrar asignaciones activas.');
        }

        $motivoNorm = trim((string) ($motivo ?? ''));
        if ($motivoNorm === '') {
            throw new DomainException('Debe indicar un motivo del cierre.');
        }
        if (mb_strlen($motivoNorm) > 255) {
            $motivoNorm = mb_substr($motivoNorm, 0, 255);
        }

        $now = Clock::nowString();
        $estado = $accion === 'finalizar'
            ? EstadoAsignacion::FINALIZADA->value
            : EstadoAsignacion::CANCELADA->value;

        $data = [
            'estado' => $estado,
            'motivo_fin' => $motivoNorm,
            'finalizada_at' => $now,
            'finalizada_por' => $usuarioId,
            'updated_at' => $now,
        ];
        if ($accion === 'finalizar' && empty($asign['fecha_fin'])) {
            $data['fecha_fin'] = date('Y-m-d');
        }

        $this->repo->actualizar($id, $data);

        $this->audit->log(
            evento: 'asignacion.' . ($accion === 'finalizar' ? 'finalizada' : 'cancelada'),
            usuarioId: $usuarioId,
            entidadTipo: 'asignacion',
            entidadId: $id,
            detalles: [
                'motivo' => $motivoNorm,
                'ficha_id' => $asign['ficha_id'],
                'puesto_id' => $asign['puesto_id'],
            ],
            nivel: 'warning',
            ip: $ip,
        );
    }

    private function validarFicha(string $fichaId): void
    {
        $tid = $this->tenantContext->requireTenantId();
        $row = $this->db->fetchOne(
            'SELECT f.estado, ' . FichaEmpleado::esDeAspirante('f') . ' AS es_aspirante
               FROM fichas f
              WHERE f.id = :id AND f.tenant_id = :tid',
            ['id' => $fichaId, 'tid' => $tid],
        );
        if ($row === null) {
            throw new DomainException('La ficha seleccionada no existe.');
        }
        if ((int) $row['es_aspirante'] === 1) {
            throw new DomainException('Esta persona aún es aspirante: contrátala desde Reclutamiento antes de asignarla a un puesto.');
        }
        if ($row['estado'] !== 'COMPLETA') {
            throw new DomainException('Solo se pueden asignar fichas en estado COMPLETA. Completa primero el expediente del guardia.');
        }
    }

    private function validarPuesto(string $puestoId): void
    {
        $tid = $this->tenantContext->requireTenantId();
        $row = $this->db->fetchOne(
            'SELECT estado FROM puestos WHERE id = :id AND tenant_id = :tid',
            ['id' => $puestoId, 'tid' => $tid],
        );
        if ($row === null) {
            throw new DomainException('El puesto seleccionado no existe.');
        }
        if ($row['estado'] !== 'activo') {
            throw new DomainException('Solo se pueden asignar guardias a puestos activos.');
        }
    }

    private function plazasPuesto(string $puestoId): int
    {
        $tid = $this->tenantContext->requireTenantId();
        return (int) $this->db->fetchScalar(
            'SELECT num_guardias_requeridos FROM puestos WHERE id = :id AND tenant_id = :tid',
            ['id' => $puestoId, 'tid' => $tid],
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: string, 1: ?string} [fecha_inicio, fecha_fin]
     */
    private function validarPeriodo(array $input): array
    {
        $inicio = trim((string) ($input['fecha_inicio'] ?? ''));
        $fin = trim((string) ($input['fecha_fin'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
            throw new DomainException('Fecha de inicio invalida (formato YYYY-MM-DD).');
        }
        if ($fin !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
            throw new DomainException('Fecha de fin invalida (formato YYYY-MM-DD).');
        }
        if ($fin !== '' && $fin < $inicio) {
            throw new DomainException('La fecha de fin no puede ser anterior a la fecha de inicio.');
        }

        return [$inicio, $fin !== '' ? $fin : null];
    }

    private function limit(mixed $raw, int $max): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') return null;
        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }
}
