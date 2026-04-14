<?php

declare(strict_types=1);

namespace DigiSecure\Shared\Infrastructure\Pdf;

/**
 * Generador de PDF 1.4 vanilla PHP — sin dependencias externas.
 *
 * Soporta A4 portrait, fuentes base (Helvetica / Helvetica-Bold), texto
 * multilínea, líneas, rectángulos, tablas, saltos de página automáticos
 * y codificación de caracteres WinAnsi (CP1252) para acentos del español.
 *
 * Uso típico:
 *   $pdf = new PdfBuilder();
 *   $pdf->addPage();
 *   $pdf->setFont('Helvetica-Bold', 14);
 *   $pdf->text(50, 800, 'Título');
 *   return $pdf->output();
 *
 * Limitaciones conscientes:
 *   - Sólo fuentes standard PDF (no TTF embebidas).
 *   - Sólo una imagen simple (JPEG) por si hace falta más adelante.
 *   - No soporta tablas anidadas ni flujo de texto con hyphenation.
 *
 * Es suficiente para la Declaración de Lealtad y el Formato Físico de
 * Huellas, que son documentos con estructura fija y controlada por el
 * sistema (no contenido arbitrario del usuario).
 */
final class PdfBuilder
{
    public const PAGE_WIDTH = 595.0;
    public const PAGE_HEIGHT = 842.0;

    public const MARGIN_LEFT = 50.0;
    public const MARGIN_RIGHT = 50.0;
    public const MARGIN_TOP = 50.0;
    public const MARGIN_BOTTOM = 50.0;

    /** @var list<string> Contenido de cada página (stream comprimido por página) */
    private array $pageContents = [];

    /** @var list<string> Objetos PDF crudos indexados por número de objeto (1-based) */
    private array $objects = [];

    /** @var array<string, int> Recursos fuente ya declarados: nombre -> número de objeto */
    private array $fonts = [];

    private int $currentPageIndex = -1;
    private string $currentContent = '';
    private float $cursorY;
    private float $cursorX;
    private string $currentFont = 'Helvetica';
    private float $currentFontSize = 10.0;

    /** @var array<string, array<string, int>> Tabla de anchos aproximados de las fuentes base (em × 1000) */
    private const FONT_WIDTHS = [
        'Helvetica' => [
            ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889,
            '&' => 667, "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584,
            ',' => 278, '-' => 333, '.' => 278, '/' => 278, '0' => 556, '1' => 556,
            '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
            '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584,
            '>' => 584, '?' => 556, '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722,
            'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278,
            'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
            'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722,
            'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278,
            '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, 'a' => 556,
            'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
            'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833,
            'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500,
            't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
            'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        ],
        // Helvetica-Bold usa aproximadamente los mismos anchos +10% que Helvetica.
        // Para el propósito del wrap estos valores son suficientes.
    ];

    public function __construct()
    {
        $this->declareFont('Helvetica');
        $this->declareFont('Helvetica-Bold');
    }

    // =========================================================
    // PÁGINAS
    // =========================================================

    /**
     * Añade una página nueva. Cierra la anterior si estaba abierta.
     */
    public function addPage(): void
    {
        if ($this->currentPageIndex >= 0) {
            $this->pageContents[$this->currentPageIndex] = $this->currentContent;
        }

        $this->currentPageIndex++;
        $this->currentContent = '';
        $this->cursorY = self::PAGE_HEIGHT - self::MARGIN_TOP;
        $this->cursorX = self::MARGIN_LEFT;
    }

    /**
     * Devuelve la coordenada Y actual (en puntos desde la base de la página).
     */
    public function y(): float
    {
        return $this->cursorY;
    }

    /**
     * Establece la coordenada Y del cursor.
     */
    public function setY(float $y): void
    {
        $this->cursorY = $y;
    }

    /**
     * Avanza el cursor Y (positivo = hacia abajo en coordenadas PDF invertidas).
     */
    public function advanceY(float $delta): void
    {
        $this->cursorY -= $delta;
        if ($this->cursorY < self::MARGIN_BOTTOM) {
            $this->addPage();
        }
    }

    // =========================================================
    // FUENTES
    // =========================================================

    public function setFont(string $name, float $size): void
    {
        if (!isset($this->fonts[$name])) {
            $this->declareFont($name);
        }
        $this->currentFont = $name;
        $this->currentFontSize = $size;
    }

    private function declareFont(string $name): void
    {
        $this->fonts[$name] = 0; // placeholder; el número de objeto se asigna al emitir.
    }

    // =========================================================
    // TEXTO
    // =========================================================

    /**
     * Escribe una línea de texto en coordenadas absolutas.
     */
    public function text(float $x, float $y, string $text): void
    {
        $this->currentContent .= sprintf(
            "BT /F%d %F Tf %F %F Td (%s) Tj ET\n",
            $this->fontAlias($this->currentFont),
            $this->currentFontSize,
            $x,
            $y,
            $this->escape($text),
        );
    }

    /**
     * Escribe texto en la posición actual del cursor y avanza Y.
     *
     * Si el texto excede el ancho disponible se divide en líneas.
     */
    public function writeLine(string $text, float $lineHeight = 14.0): void
    {
        $availableWidth = self::PAGE_WIDTH - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $lines = $this->wrap($text, $availableWidth);
        foreach ($lines as $line) {
            $this->text(self::MARGIN_LEFT, $this->cursorY, $line);
            $this->advanceY($lineHeight);
        }
    }

    /**
     * Escribe un párrafo (con justificación izquierda, múltiples líneas).
     */
    public function paragraph(string $text, float $lineHeight = 14.0, float $after = 6.0): void
    {
        $this->writeLine($text, $lineHeight);
        $this->advanceY($after);
    }

    /**
     * Escribe un título centrado horizontalmente.
     */
    public function titleCentered(string $text, float $after = 10.0): void
    {
        $width = $this->textWidth($text);
        $x = (self::PAGE_WIDTH - $width) / 2;
        $this->text($x, $this->cursorY, $text);
        $this->advanceY($this->currentFontSize + $after);
    }

    /**
     * Escribe un label alineado a la izquierda y un valor a su derecha.
     */
    public function labelValue(string $label, string $value, float $labelWidth = 160.0, float $lineHeight = 14.0): void
    {
        $prevFont = $this->currentFont;
        $this->setFont('Helvetica-Bold', $this->currentFontSize);
        $this->text(self::MARGIN_LEFT, $this->cursorY, $label);
        $this->setFont($prevFont, $this->currentFontSize);
        $this->text(self::MARGIN_LEFT + $labelWidth, $this->cursorY, $value);
        $this->advanceY($lineHeight);
    }

    // =========================================================
    // FIGURAS
    // =========================================================

    /**
     * Dibuja una línea horizontal a la Y actual.
     */
    public function horizontalRule(float $afterY = 8.0): void
    {
        $x1 = self::MARGIN_LEFT;
        $x2 = self::PAGE_WIDTH - self::MARGIN_RIGHT;
        $y = $this->cursorY;
        $this->currentContent .= sprintf("%F %F m %F %F l S\n", $x1, $y, $x2, $y);
        $this->advanceY($afterY);
    }

    /**
     * Dibuja un rectángulo con trazo (sin relleno).
     */
    public function rect(float $x, float $y, float $w, float $h, float $lineWidth = 0.5): void
    {
        $this->currentContent .= sprintf(
            "%F w %F %F %F %F re S\n",
            $lineWidth,
            $x,
            $y,
            $w,
            $h,
        );
    }

    /**
     * Dibuja un recuadro con título encima (útil para zonas de firma / huella).
     */
    public function labeledBox(string $label, float $x, float $y, float $w, float $h): void
    {
        $prevFont = $this->currentFont;
        $prevSize = $this->currentFontSize;
        $this->setFont('Helvetica', 8);
        $this->text($x, $y + $h + 4, $label);
        $this->rect($x, $y, $w, $h);
        $this->setFont($prevFont, $prevSize);
    }

    // =========================================================
    // MEDICIÓN Y WRAP
    // =========================================================

    public function textWidth(string $text): float
    {
        $width = 0.0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];
            $w = self::FONT_WIDTHS['Helvetica'][$char] ?? 500;
            $width += $w / 1000.0 * $this->currentFontSize;
        }
        // Las fuentes Bold son ~8% más anchas en promedio.
        if ($this->currentFont === 'Helvetica-Bold') {
            $width *= 1.08;
        }
        return $width;
    }

    /**
     * Divide un string en líneas que caben en $maxWidth.
     *
     * @return list<string>
     */
    private function wrap(string $text, float $maxWidth): array
    {
        $paragraphs = preg_split('/\r?\n/', $text) ?: [];
        $lines = [];
        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', $paragraph) ?: [];
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : ($current . ' ' . $word);
                if ($this->textWidth($candidate) <= $maxWidth) {
                    $current = $candidate;
                    continue;
                }
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
            $lines[] = $current;
        }
        return $lines;
    }

    // =========================================================
    // OUTPUT
    // =========================================================

    /**
     * Genera el PDF completo como string binario.
     */
    public function output(): string
    {
        // Cerrar la última página
        if ($this->currentPageIndex >= 0) {
            $this->pageContents[$this->currentPageIndex] = $this->currentContent;
        }

        // Asignar números de objeto determinísticamente:
        //   1: Catalog
        //   2: Pages
        //   3..3+N-1: Páginas (Page objects)
        //   3+N..3+2N-1: Content streams (uno por página)
        //   3+2N..3+2N+F-1: Fuentes
        $pageCount = count($this->pageContents);
        $fontNames = array_keys($this->fonts);
        $fontCount = count($fontNames);

        $pageObjStart = 3;
        $contentObjStart = $pageObjStart + $pageCount;
        $fontObjStart = $contentObjStart + $pageCount;

        // Asignar número a cada fuente
        foreach ($fontNames as $idx => $name) {
            $this->fonts[$name] = $fontObjStart + $idx;
        }

        // Construir objetos
        // 1: Catalog
        $this->objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        // 2: Pages (con /Kids apuntando a cada página)
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($pageObjStart + $i) . ' 0 R';
        }
        $this->objects[2] = sprintf(
            "<< /Type /Pages /Count %d /Kids [ %s ] >>",
            $pageCount,
            implode(' ', $kids),
        );

        // Páginas
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObj = $pageObjStart + $i;
            $contentObj = $contentObjStart + $i;

            $fontResources = [];
            foreach ($fontNames as $name) {
                $alias = $this->fontAlias($name);
                $fontResources[] = "/F{$alias} {$this->fonts[$name]} 0 R";
            }
            $fontsDict = '<< ' . implode(' ', $fontResources) . ' >>';

            $this->objects[$pageObj] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %F %F] "
                . "/Resources << /Font %s /ProcSet [ /PDF /Text ] >> "
                . "/Contents %d 0 R >>",
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $fontsDict,
                $contentObj,
            );
        }

        // Content streams
        for ($i = 0; $i < $pageCount; $i++) {
            $contentObj = $contentObjStart + $i;
            $stream = $this->pageContents[$i];
            $length = strlen($stream);
            $this->objects[$contentObj] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                $length,
                $stream,
            );
        }

        // Fuentes
        foreach ($fontNames as $name) {
            $num = $this->fonts[$name];
            $this->objects[$num] = sprintf(
                "<< /Type /Font /Subtype /Type1 /BaseFont /%s /Encoding /WinAnsiEncoding >>",
                $name,
            );
        }

        return $this->assemble();
    }

    /**
     * Devuelve el identificador corto de la fuente en los content streams (/F1, /F2...).
     */
    private function fontAlias(string $name): int
    {
        $names = array_keys($this->fonts);
        $idx = array_search($name, $names, true);
        if ($idx === false) {
            return 1;
        }
        return $idx + 1;
    }

    /**
     * Ensambla los objetos en un stream PDF válido con xref y trailer.
     */
    private function assemble(): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        $offsets = [];
        foreach ($this->objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $maxNum = max(array_keys($this->objects));
        $pdf .= "xref\n0 " . ($maxNum + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxNum; $i++) {
            if (isset($offsets[$i])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                $pdf .= "0000000000 65535 f \n";
            }
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
            $maxNum + 1,
            $xrefPos,
        );

        return $pdf;
    }

    /**
     * Escapa y convierte un string UTF-8 a la codificación WinAnsi (CP1252)
     * para que las fuentes base del PDF puedan representarlo.
     *
     * Caracteres no soportables se reemplazan por '?' antes que romper el PDF.
     */
    private function escape(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        } elseif (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        }

        return strtr($text, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
            "\r" => '\\r',
            "\n" => '\\n',
        ]);
    }
}
