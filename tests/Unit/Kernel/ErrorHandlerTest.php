<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Kernel;

use DigiSecure\Kernel\Error\ErrorHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Una actualizacion de PHP en el hosting (8.3 -> 8.4) hizo que un E_DEPRECATED
 * se convirtiera en excepcion y tumbara todo el sitio. Estos tests fijan la regla:
 * las obsolescencias se registran y la peticion continua; el resto sigue fallando.
 */
final class ErrorHandlerTest extends TestCase
{
    private string $logPath;
    private int $nivelAnterior;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/ds-errorhandler-' . bin2hex(random_bytes(4)) . '.log';
        // register() fija E_ALL en produccion; el runner puede traer otro nivel.
        $this->nivelAnterior = error_reporting(E_ALL);
    }

    protected function tearDown(): void
    {
        error_reporting($this->nivelAnterior);
        @unlink($this->logPath);
    }

    #[Test]
    public function una_obsolescencia_se_registra_una_vez_y_no_lanza(): void
    {
        $handler = new ErrorHandler(false, $this->logPath);

        self::assertTrue($handler->handleError(E_DEPRECATED, 'ini_set(): session.sid_length INI setting is deprecated', 'x.php', 10));
        self::assertTrue($handler->handleError(E_DEPRECATED, 'ini_set(): session.sid_length INI setting is deprecated', 'x.php', 10));
        self::assertTrue($handler->handleError(E_USER_DEPRECATED, 'api vieja', 'y.php', 3));

        $log = (string) file_get_contents($this->logPath);
        self::assertSame(1, substr_count($log, 'session.sid_length'));
        self::assertStringContainsString('DEPRECATED: api vieja', $log);
        self::assertStringContainsString('request_id=' . $handler->requestId(), $log);
    }

    #[Test]
    public function un_warning_sigue_convirtiendose_en_excepcion(): void
    {
        $handler = new ErrorHandler(false, $this->logPath);

        $this->expectException(\ErrorException::class);
        $handler->handleError(E_WARNING, 'algo fallo', 'x.php', 1);
    }
}
