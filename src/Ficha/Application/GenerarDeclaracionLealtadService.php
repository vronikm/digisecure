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
 * Genera la Declaración de Lealtad en PDF para una ficha.
 *
 * Decisión de negocio vinculante #5: el sistema genera el documento con los
 * datos del guardia y la empresa rellenados para ser descargado como PDF.
 *
 * Este servicio:
 *   1. Carga la ficha (tenant-aware).
 *   2. Resuelve los datos del tenant (razón social, RUC, representante).
 *   3. Construye el PDF con plantilla fija.
 *   4. Registra en auditoría que se descargó la declaración.
 *
 * No se persiste el PDF — se regenera bajo demanda desde datos actuales.
 */
final class GenerarDeclaracionLealtadService
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
            'SELECT razon_social, ruc, nombre_comercial, direccion, ciudad, email_contacto, telefono
             FROM tenants WHERE id = :id',
            ['id' => $tenantId],
        );
        if ($tenant === null) {
            throw new DomainException('Tenant no encontrado.');
        }

        $cedula = $this->decryptCedula((string)$ficha['cedula_cifrada']);
        $pdf = $this->buildPdf($ficha, $cedula, $tenant);

        $this->audit->log(
            evento: 'ficha.declaracion_lealtad_descargada',
            usuarioId: $usuarioId,
            entidadTipo: 'ficha',
            entidadId: $fichaId,
            detalles: ['correlativo' => $ficha['correlativo']],
            nivel: 'info',
            ip: $ip,
        );

        $filename = sprintf(
            'declaracion-lealtad-%s.pdf',
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

        // Encabezado con datos de la empresa
        $pdf->setFont('Helvetica-Bold', 12);
        $pdf->writeLine((string)$tenant['razon_social']);
        $pdf->setFont('Helvetica', 9);
        $pdf->writeLine(sprintf('RUC: %s', (string)$tenant['ruc']));
        if (!empty($tenant['direccion'])) {
            $pdf->writeLine(sprintf(
                '%s%s',
                (string)$tenant['direccion'],
                !empty($tenant['ciudad']) ? ' - ' . (string)$tenant['ciudad'] : '',
            ));
        }
        $pdf->advanceY(12);
        $pdf->horizontalRule();

        // Título
        $pdf->setFont('Helvetica-Bold', 14);
        $pdf->titleCentered('DECLARACIÓN DE LEALTAD Y CONFIDENCIALIDAD');

        // Cuerpo
        $pdf->setFont('Helvetica', 10);
        $nombreCompleto = sprintf(
            '%s %s',
            (string)$ficha['nombres'],
            (string)$ficha['apellidos'],
        );

        $cedulaDisplay = $cedula !== '' ? $cedula : '________________';

        $pdf->paragraph(sprintf(
            'Yo, %s, portador de la cédula de ciudadanía N° %s, en mi calidad de guardia '
            . 'de seguridad privada vinculado a la empresa %s, declaro libre y voluntariamente '
            . 'bajo juramento lo siguiente:',
            $nombreCompleto,
            $cedulaDisplay,
            (string)$tenant['razon_social'],
        ));

        $clausulas = [
            'PRIMERA.- Que conozco y me comprometo a cumplir estrictamente los principios de '
            . 'lealtad, honradez, disciplina, confidencialidad y respeto que rigen el ejercicio '
            . 'de las actividades de seguridad privada en la República del Ecuador.',

            'SEGUNDA.- Que mantendré absoluta reserva sobre toda información, documento, '
            . 'operación, procedimiento o situación que conozca con ocasión del desempeño de '
            . 'mis funciones, incluso luego de terminada mi relación con la empresa.',

            'TERCERA.- Que no divulgaré, compartiré, reproduciré ni permitiré el acceso a '
            . 'información confidencial relacionada con los clientes, instalaciones, rutinas '
            . 'de vigilancia, sistemas de seguridad, claves de acceso, códigos o cualquier '
            . 'otro dato sensible al que tenga acceso.',

            'CUARTA.- Que me obligo a reportar de inmediato a mis superiores cualquier hecho, '
            . 'intento o sospecha de robo, infiltración, soborno, amenaza, extorsión o '
            . 'cualquier acto que atente contra la seguridad del puesto, la empresa o sus '
            . 'clientes.',

            'QUINTA.- Que reconozco que el incumplimiento de esta declaración constituirá '
            . 'falta grave, pudiendo ser causal de terminación inmediata del vínculo laboral, '
            . 'sin perjuicio de las acciones civiles y penales que correspondan conforme a la '
            . 'legislación ecuatoriana vigente.',

            'SEXTA.- Que autorizo expresamente, en los términos de la Ley Orgánica de '
            . 'Protección de Datos Personales del Ecuador, el tratamiento de mis datos '
            . 'personales exclusivamente para los fines de vinculación laboral, cumplimiento '
            . 'de obligaciones legales, y gestión de la relación de seguridad privada.',
        ];

        foreach ($clausulas as $clausula) {
            $pdf->paragraph($clausula, 13.0, 5.0);
        }

        // Fecha
        $pdf->advanceY(10);
        $pdf->setFont('Helvetica', 10);
        $ciudad = !empty($tenant['ciudad']) ? (string)$tenant['ciudad'] : '________';
        $fecha = date('d') . ' de ' . $this->mesNombre((int)date('m')) . ' de ' . date('Y');
        $pdf->writeLine(sprintf('Dado en %s, a %s.', $ciudad, $fecha));

        // Zona de firma
        $pdf->advanceY(40);
        $firmaY = $pdf->y();
        $pdf->text(120, $firmaY, '__________________________________');
        $pdf->advanceY(14);
        $pdf->setFont('Helvetica-Bold', 10);
        $pdf->text(130, $pdf->y(), $nombreCompleto);
        $pdf->advanceY(12);
        $pdf->setFont('Helvetica', 9);
        $pdf->text(130, $pdf->y(), sprintf('C.C. %s', $cedulaDisplay));

        // Pie
        $pdf->setFont('Helvetica', 7);
        $pdf->text(
            PdfBuilder::MARGIN_LEFT,
            PdfBuilder::MARGIN_BOTTOM,
            sprintf(
                'Ficha %s · Generado el %s · DigiSecure',
                (string)$ficha['correlativo'],
                date('Y-m-d H:i'),
            ),
        );

        return $pdf->output();
    }

    private function mesNombre(int $mes): string
    {
        return [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ][$mes] ?? '';
    }
}
