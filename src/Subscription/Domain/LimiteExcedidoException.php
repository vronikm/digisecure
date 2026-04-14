<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Domain;

use DigiSecure\Shared\Domain\DomainException;

/**
 * Se lanza cuando una operación superaría los límites del plan contratado.
 */
final class LimiteExcedidoException extends DomainException
{
    public static function usuarios(int $actuales, int $max): self
    {
        return new self(
            "Tu plan permite un máximo de {$max} usuarios y ya tienes {$actuales}. " .
            "Actualiza tu suscripción para agregar más.",
        );
    }

    public static function guardias(int $actuales, int $max): self
    {
        return new self(
            "Tu plan permite un máximo de {$max} guardias y ya tienes {$actuales}. " .
            "Actualiza tu suscripción para registrar más fichas.",
        );
    }

    public static function storage(int $usadoMb, int $maxMb): self
    {
        return new self(
            "Tu plan incluye {$maxMb} MB de almacenamiento y llevas {$usadoMb} MB usados. " .
            "Libera espacio o actualiza tu suscripción.",
        );
    }
}
