<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: Marcar una ficha como COMPLETA.
 *
 * Aplica las 4 reglas de requisitos básicos (bloqueo duro, sin excepción):
 *  1. Edad entre 20 y 45 años
 *  2. Estatura mínima según género (1.65m H, 1.50m M)
 *  3. Instrucción mínima: Bachiller (secundaria no vacía)
 *  4. Experiencia en seguridad ≥ 1 año (al menos 1 trabajo anterior)
 *
 * Si alguna regla no se cumple, se retorna la lista de incumplimientos
 * y la ficha NO cambia de estado.
 */
final class CompletarFichaService
{
    public function __construct(
        private readonly FichaRepository $fichaRepo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return list<string> Lista de incumplimientos (vacía si se completó)
     * @throws DomainException Si la ficha no existe o no puede completarse
     */
    public function execute(string $fichaId, string $usuarioId, string $ip): array
    {
        $ficha = $this->fichaRepo->findFichaById($fichaId);
        if ($ficha === null) {
            throw new DomainException('Ficha no encontrada.');
        }

        $estado = EstadoFicha::from($ficha['estado']);
        if ($estado !== EstadoFicha::EN_REVISION) {
            throw new DomainException("Solo se puede completar una ficha en estado 'En revisión'. Estado actual: '{$estado->label()}'.");
        }

        // Validar requisitos básicos
        $incumplimientos = $this->validarRequisitosBasicos($ficha, $fichaId);

        if ($incumplimientos !== []) {
            $this->audit->log(
                'ficha.completar_rechazada',
                $usuarioId,
                'ficha',
                $fichaId,
                detalles: ['incumplimientos' => $incumplimientos],
                nivel: 'warning',
                ip: $ip,
            );
            return $incumplimientos;
        }

        // Todo OK — marcar como completa
        $this->fichaRepo->update($fichaId, [
            'estado' => EstadoFicha::COMPLETA->value,
            'updated_by' => $usuarioId,
        ]);

        $this->audit->log(
            'ficha.completar',
            $usuarioId,
            'ficha',
            $fichaId,
            ip: $ip,
        );

        return [];
    }

    /**
     * @return list<string>
     */
    private function validarRequisitosBasicos(array $ficha, string $fichaId): array
    {
        $incumplimientos = [];

        // 1. Edad entre 20 y 45
        if (!empty($ficha['fecha_nacimiento'])) {
            $nacimiento = new \DateTimeImmutable($ficha['fecha_nacimiento']);
            $hoy = new \DateTimeImmutable('today');
            $edad = (int)$nacimiento->diff($hoy)->y;

            if ($edad < 20) {
                $incumplimientos[] = "Edad insuficiente: {$edad} años (mínimo 20).";
            } elseif ($edad > 45) {
                $incumplimientos[] = "Edad excedida: {$edad} años (máximo 45).";
            }
        } else {
            $incumplimientos[] = 'Fecha de nacimiento no registrada.';
        }

        // 2. Estatura mínima según género
        if (!empty($ficha['genero']) && !empty($ficha['estatura'])) {
            $genero = Genero::from($ficha['genero']);
            $estatura = (float)$ficha['estatura'];
            $minima = $genero->estaturaMinima();

            if ($minima !== null && $estatura < $minima) {
                $incumplimientos[] = sprintf(
                    'Estatura insuficiente: %.2f m (mínimo %.2f m para %s).',
                    $estatura,
                    $minima,
                    $genero->label(),
                );
            }
        } elseif (empty($ficha['estatura'])) {
            $incumplimientos[] = 'Estatura no registrada.';
        }

        // 3. Instrucción mínima: Bachiller
        if (empty($ficha['instruccion_secundaria'])) {
            $incumplimientos[] = 'Instrucción secundaria (bachillerato) no registrada.';
        }

        // 4. Experiencia ≥ 1 trabajo anterior
        $trabajos = $this->fichaRepo->getTrabajosAnteriores($fichaId);
        if ($trabajos === []) {
            $incumplimientos[] = 'No se registró ningún trabajo anterior (mínimo 1 año de experiencia).';
        }

        return $incumplimientos;
    }
}
