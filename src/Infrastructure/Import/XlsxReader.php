<?php
declare(strict_types=1);

namespace DigiSecure\Infrastructure\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal XLSX reader for import/audit CLIs.
 *
 * It reads workbook metadata, shared strings and worksheet cells directly from
 * the OpenXML zip package. It intentionally avoids heavy spreadsheet
 * dependencies so production Git deploys do not need extra Composer packages.
 */
final class XlsxReader
{
    /**
     * @return array{path: string, sheets: list<array{name: string, rows: list<array{number: int, cells: array<int, string>}>}>}
     */
    public function read(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extension PHP zip no esta habilitada. Activa ext-zip para leer archivos XLSX.');
        }
        if (!is_file($path)) {
            throw new RuntimeException("Archivo XLSX no encontrado: {$path}");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("No se pudo abrir el XLSX: {$path}");
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $workbook = $this->xmlFromZip($zip, 'xl/workbook.xml');
            $relationships = $this->readRelationships($zip);

            $sheets = [];
            foreach ($this->workbookSheets($workbook) as $sheet) {
                $target = $relationships[$sheet['rid']] ?? null;
                if ($target === null) {
                    continue;
                }

                $worksheetXml = $this->xmlFromZip($zip, $target);
                $sheets[] = [
                    'name' => $sheet['name'],
                    'rows' => $this->worksheetRows($worksheetXml, $sharedStrings),
                ];
            }

            return [
                'path' => $path,
                'sheets' => $sheets,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $this->xmlFromZip($zip, 'xl/sharedStrings.xml');
        $strings = [];

        foreach ($xml->si as $si) {
            $parts = $si->xpath('.//*[local-name()="t"]') ?: [];
            $value = '';
            foreach ($parts as $part) {
                $value .= (string) $part;
            }
            $strings[] = $this->clean($value);
        }

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    private function readRelationships(ZipArchive $zip): array
    {
        $xml = $this->xmlFromZip($zip, 'xl/_rels/workbook.xml.rels');
        $relationships = [];

        foreach ($xml->Relationship as $rel) {
            $attrs = $rel->attributes();
            $id = (string) ($attrs['Id'] ?? '');
            $target = (string) ($attrs['Target'] ?? '');
            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/' . ltrim($target, '/');
        }

        return $relationships;
    }

    /**
     * @return list<array{name: string, rid: string}>
     */
    private function workbookSheets(SimpleXMLElement $workbook): array
    {
        $namespaces = $workbook->getNamespaces(true);
        $relationshipNamespace = $namespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $result = [];

        foreach ($workbook->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes();
            $relAttrs = $sheet->attributes($relationshipNamespace);
            $name = (string) ($attrs['name'] ?? '');
            $rid = (string) ($relAttrs['id'] ?? '');
            if ($name !== '' && $rid !== '') {
                $result[] = ['name' => $name, 'rid' => $rid];
            }
        }

        return $result;
    }

    /**
     * @param list<string> $sharedStrings
     * @return list<array{number: int, cells: array<int, string>}>
     */
    private function worksheetRows(SimpleXMLElement $worksheet, array $sharedStrings): array
    {
        $rows = [];
        $sheetData = $worksheet->sheetData;
        if (!$sheetData instanceof SimpleXMLElement) {
            return [];
        }

        foreach ($sheetData->row as $row) {
            $rowAttrs = $row->attributes();
            $rowNumber = (int) ($rowAttrs['r'] ?? 0);
            if ($rowNumber <= 0) {
                $rowNumber = count($rows) + 1;
            }

            $cells = [];
            foreach ($row->c as $cell) {
                $cellAttrs = $cell->attributes();
                $ref = (string) ($cellAttrs['r'] ?? '');
                $columnIndex = $this->cellColumnIndex($ref);
                if ($columnIndex === null) {
                    continue;
                }

                $value = $this->cellValue($cell, $sharedStrings);
                if ($value !== '') {
                    $cells[$columnIndex] = $value;
                }
            }

            ksort($cells);
            $rows[] = ['number' => $rowNumber, 'cells' => $cells];
        }

        return $rows;
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $attrs = $cell->attributes();
        $type = (string) ($attrs['t'] ?? '');

        if ($type === 'inlineStr') {
            $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];
            $value = '';
            foreach ($parts as $part) {
                $value .= (string) $part;
            }
            return $this->clean($value);
        }

        $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
        if ($valueNodes === []) {
            return '';
        }

        $raw = (string) $valueNodes[0];
        if ($type === 's') {
            $index = (int) $raw;
            return $sharedStrings[$index] ?? '';
        }

        return $this->clean($raw);
    }

    private function cellColumnIndex(string $ref): ?int
    {
        if (!preg_match('/^([A-Z]+)\d+$/i', $ref, $matches)) {
            return null;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index;
    }

    private function xmlFromZip(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $contents = $zip->getFromName($path);
        if ($contents === false) {
            throw new RuntimeException("El XLSX no contiene {$path}.");
        }

        $xml = simplexml_load_string($contents);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException("No se pudo leer XML interno {$path}.");
        }

        return $xml;
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        return trim($value);
    }
}
