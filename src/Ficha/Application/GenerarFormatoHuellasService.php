<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Pdf\PdfBuilder;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Genera el formato físico de huellas dactilares en PDF.
 *
 * Decisión de negocio vinculante #2: las huellas NO se capturan digitalmente.
 * El sistema imprime un formato donde las huellas se toman a mano con tinta.
 *
 * El PDF contiene:
 *   - Encabezado con datos de la empresa
 *   - Datos del guardia (pre-rellenados)
 *   - Cuadrícula con 10 celdas para cada dedo (mano derecha + mano izquierda)
 *   - Zona para firma manuscrita del titular y del funcionario
 *
 * Se regenera bajo demanda. No se persiste.
 */
final class GenerarFormatoHuellasService
{
    public function __construct(
        private readonly FichaRepository $fichas,
        private readonly PdoConnection $db,
        private readonly TenantContext $tenant,
        private readonly Encryption $encryption,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return array{pdf: string, filename: string}
     */
    public function execute(string $fichaId, string $usuarioId, string $ip): array
    {
        $ficha = $this->fichas->findFichaById($fichaId);
        if ($ficha === null) {
            throw new DomainException('Ficha no encontrada.');
        }

        $tenantId = $this->tenant->requireTenantId();
        $tenant = $this->db->fetchOne(
            'SELECT razon_social, ruc FROM tenants WHERE id = :id',
            ['id' => $tenantId],
        );
        if ($tenant === null) {
            throw new DomainException('Tenant no encontrado.');
        }

        $cedula = $this->decryptCedula((string)$ficha['cedula_cifrada']);
        $pdf = $this->buildPdf($ficha, $cedula, $tenant);

        $this->audit->log(
            evento: 'ficha.formato_huellas_descargado',
            usuarioId: $usuarioId,
            entidadTipo: 'ficha',
            entidadId: $fichaId,
            detalles: ['correlativo' => $ficha['correlativo']],
            nivel: 'info',
            ip: $ip,
        );

        $filename = sprintf(
            'formato-huellas-%s.pdf',
            preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$ficha['correlativo']),
        );

        return ['pdf' => $pdf, 'filename' => $filename];
    }

    private function decryptCedula(string $cipher): string
    {
        if ($cipher === '') {
            return '';
        }
        try {
            return $this->encryption->decrypt($cipher);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $ficha
     * @param array<string, mixed> $tenant
     */
    private function buildPdf(array $ficha, string $cedula, array $tenant): string
    {
        $pdf = new PdfBuilder();
        $pdf->addPage();

        // Encabezado
        $pdf->setFont('Helvetica-Bold', 12);
        $pdf->writeLine((string)$tenant['razon_social']);
        $pdf->setFont('Helvetica', 9);
        $pdf->writeLine(sprintf('RUC: %s', (string)$tenant['ruc']));
        $pdf->advanceY(8);
        $pdf->horizontalRule();

        // Título
        $pdf->setFont('Helvetica-Bold', 13);
        $pdf->titleCentered('FORMATO DE REGISTRO DACTILAR');
        $pdf->setFont('Helvetica', 9);
        $pdf->titleCentered('(Impresión manual con tinta negra)', 8);

        // Datos del guardia
        $pdf->setFont('Helvetica', 10);
        $pdf->labelValue('Nombre completo:', sprintf('%s %s', (string)$ficha['nombres'], (string)$ficha['apellidos']));
        $pdf->labelValue('Cédula:', $cedula !== '' ? $cedula : '__________________');
        $pdf->labelValue('Correlativo ficha:', (string)$ficha['correlativo']);
        $pdf->labelValue('Fecha de registro:', date('Y-m-d'));
        $pdf->advanceY(6);

        // Mano derecha
        $pdf->setFont('Helvetica-Bold', 11);
        $pdf->writeLine('MANO DERECHA');
        $pdf->setFont('Helvetica', 8);
        $this->drawDedos($pdf, [
            'Pulgar (D)', 'Índice (D)', 'Medio (D)', 'Anular (D)', 'Meñique (D)',
        ]);

        $pdf->advanceY(15);

        // Mano izquierda
        $pdf->setFont('Helvetica-Bold', 11);
        $pdf->writeLine('MANO IZQUIERDA');
        $pdf->setFont('Helvetica', 8);
        $this->drawDedos($pdf, [
            'Pulgar (I)', 'Índice (I)', 'Medio (I)', 'Anular (I)', 'Meñique (I)',
        ]);

        $pdf->advanceY(20);

        // Zona de firmas
        $pdf->setFont('Helvetica', 9);
        $firmaY = $pdf->y() - 50;
        $pdf->labeledBox('Firma del titular', 60, $firmaY, 220, 40);
        $pdf->labeledBox('Firma del funcionario', 320, $firmaY, 220, 40);
        $pdf->setY($firmaY - 10);

        // Pie
        $pdf->setFont('Helvetica', 7);
        $pdf->text(
            PdfBuilder::MARGIN_LEFT,
            PdfBuilder::MARGIN_BOTTOM,
            sprintf(
                'Ficha %s · Generado el %s · DigiSecure · Las huellas se toman a mano, no se almacenan digitalmente.',
                (string)$ficha['correlativo'],
                date('Y-m-d H:i'),
            ),
        );

        return $pdf->output();
    }

    /**
     * Dibuja una fila con 5 celdas etiquetadas para tomar las huellas.
     *
     * @param list<string> $labels
     */
    private function drawDedos(PdfBuilder $pdf, array $labels): void
    {
        $cellWidth = 95.0;
        $cellHeight = 110.0;
        $gap = 5.0;
        $startX = PdfBuilder::MARGIN_LEFT;

        // Las celdas se dibujan DEBAJO de la Y actual — reservamos el espacio
        $pdf->advanceY(4);
        $topY = $pdf->y();
        $boxTop = $topY - $cellHeight;

        foreach ($labels as $idx => $label) {
            $x = $startX + $idx * ($cellWidth + $gap);
            $pdf->rect($x, $boxTop, $cellWidth, $cellHeight);
            $pdf->text($x + 4, $topY + 4, $label);
        }

        $pdf->setY($boxTop - 6);
    }
}
