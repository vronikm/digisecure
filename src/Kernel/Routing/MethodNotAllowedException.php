<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Routing;

final class MethodNotAllowedException extends \RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(string $message, public readonly array $allowedMethods)
    {
        parent::__construct($message);
    }
}
