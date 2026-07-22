<?php
declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Convierte los campos familiares del formulario en registros persistibles.
 */
final class NormalizarFamiliaresFicha
{
    public function __construct(
        private readonly Encryption $encryption,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function contieneCamposFamiliares(array $input): bool
    {
        foreach ([
            'familiar_padre_nombre', 'familiar_padre_direccion', 'familiar_padre_telefono',
            'familiar_madre_nombre', 'familiar_madre_direccion', 'familiar_madre_telefono',
            'familiar_hermano_nombre', 'familiar_hermano_direccion', 'familiar_hermano_telefono',
            'conyuge_nombre', 'conyuge_cedula', 'conyuge_edad',
        ] as $field) {
            if (array_key_exists($field, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{parentesco:string,nombre:string,direccion:?string,telefono:?string,cedula_hash:?string,cedula_cifrada:?string,edad:?int}>
     */
    public function normalizar(array $input, string $estadoCivil): array
    {
        $familiares = [];

        foreach ([
            'PADRE' => ['nombre' => 'familiar_padre_nombre', 'direccion' => 'familiar_padre_direccion', 'telefono' => 'familiar_padre_telefono', 'label' => 'del padre'],
            'MADRE' => ['nombre' => 'familiar_madre_nombre', 'direccion' => 'familiar_madre_direccion', 'telefono' => 'familiar_madre_telefono', 'label' => 'de la madre'],
            'HERMANO' => ['nombre' => 'familiar_hermano_nombre', 'direccion' => 'familiar_hermano_direccion', 'telefono' => 'familiar_hermano_telefono', 'label' => 'del hermano/a'],
        ] as $parentesco => $fields) {
            $nombre = $this->texto($input[$fields['nombre']] ?? null);
            $direccion = $this->texto($input[$fields['direccion']] ?? null);
            $telefono = $this->texto($input[$fields['telefono']] ?? null);

            if ($nombre === '' && $direccion === '' && $telefono === '') {
                continue;
            }
            if ($nombre === '') {
                throw new DomainException("Ingresa el nombre {$fields['label']} para guardar sus datos.");
            }

            $familiares[] = [
                'parentesco' => $parentesco,
                'nombre' => $nombre,
                'direccion' => $direccion !== '' ? $direccion : null,
                'telefono' => $telefono !== '' ? $telefono : null,
                'cedula_hash' => null,
                'cedula_cifrada' => null,
                'edad' => null,
            ];
        }

        $nombreConyuge = $this->texto($input['conyuge_nombre'] ?? null);
        $cedulaConyuge = preg_replace('/\D+/', '', $this->texto($input['conyuge_cedula'] ?? null)) ?? '';
        $edadConyuge = $this->texto($input['conyuge_edad'] ?? null);
        $conyugeObligatorio = in_array($estadoCivil, ['CASADO', 'UNION_LIBRE'], true);
        $hayDatosConyuge = $nombreConyuge !== '' || $cedulaConyuge !== '' || $edadConyuge !== '';

        if ($conyugeObligatorio || $hayDatosConyuge) {
            if ($nombreConyuge === '' || $cedulaConyuge === '' || $edadConyuge === '') {
                throw new DomainException('Completa nombre, cédula y edad del cónyuge.');
            }

            $cedula = Cedula::fromString($cedulaConyuge);
            if (filter_var($edadConyuge, FILTER_VALIDATE_INT) === false || (int) $edadConyuge < 18 || (int) $edadConyuge > 120) {
                throw new DomainException('La edad del cónyuge debe estar entre 18 y 120 años.');
            }

            $familiares[] = [
                'parentesco' => 'CONYUGE',
                'nombre' => $nombreConyuge,
                'direccion' => null,
                'telefono' => null,
                'cedula_hash' => $cedula->hash(),
                'cedula_cifrada' => $this->encryption->encrypt($cedula->valor),
                'edad' => (int) $edadConyuge,
            ];
        }

        return $familiares;
    }

    private function texto(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
