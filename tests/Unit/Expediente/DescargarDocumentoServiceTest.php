<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Expediente;

use DigiSecure\Expediente\Application\DescargarDocumentoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DescargarDocumentoServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ?string, 1: bool}>
     */
    public static function mimes(): iterable
    {
        yield 'pdf' => ['application/pdf', true];
        yield 'jpg' => ['image/jpeg', true];
        yield 'png' => ['image/png', true];
        yield 'webp' => ['image/webp', true];
        // SVG/HTML se podrian ejecutar en el navegador: nunca inline.
        yield 'svg' => ['image/svg+xml', false];
        yield 'html' => ['text/html', false];
        yield 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', false];
        yield 'vacio' => ['', false];
        yield 'nulo' => [null, false];
        yield 'mayusculas' => ['APPLICATION/PDF', false];
    }

    #[Test]
    #[DataProvider('mimes')]
    public function solo_pdf_e_imagenes_rasterizadas_tienen_vista_previa(?string $mime, bool $esperado): void
    {
        self::assertSame($esperado, DescargarDocumentoService::admiteVistaPrevia($mime));
    }
}
