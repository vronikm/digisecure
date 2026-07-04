<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Infrastructure;

use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\IdGenerator;

final class CroquisStorage
{
    private const MAP_WIDTH = 860;
    private const MAP_HEIGHT = 380;
    private const TILE_SIZE = 256;

    /** @var array<string, string> */
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly string $storagePath,
    ) {
    }

    public function storeDataUrl(string $dataUrl, ?string $replaceId = null): string
    {
        $dataUrl = trim($dataUrl);
        if ($dataUrl === '') {
            throw new DomainException('No se recibio la imagen del croquis.');
        }

        if (!preg_match('#^data:image/[a-z0-9.+-]+;base64,([A-Za-z0-9+/=\s]+)$#i', $dataUrl, $match)) {
            throw new DomainException('La imagen del croquis no tiene un formato valido.');
        }

        $rawBase64 = preg_replace('/\s+/', '', $match[1]) ?? '';
        if (strlen($rawBase64) > 8 * 1024 * 1024) {
            throw new DomainException('La imagen del croquis es demasiado grande.');
        }

        $binary = base64_decode($rawBase64, true);
        if ($binary === false || $binary === '') {
            throw new DomainException('No se pudo decodificar la imagen del croquis.');
        }
        if (strlen($binary) > 5 * 1024 * 1024) {
            throw new DomainException('La imagen del croquis excede 5 MB.');
        }

        // El tipo declarado en el data URL lo pone el navegador (File.type) y a
        // veces etiqueta mal el archivo (p. ej. un PNG guardado con extension .jpg,
        // o un HEIC/screenshot recodificado). finfo es la autoridad: se valida y se
        // almacena segun el CONTENIDO real, no segun lo declarado por el cliente.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->buffer($binary) ?: '';
        if (!isset(self::MIME_EXT[$realMime])) {
            throw new DomainException('La imagen no es un archivo valido. Usa una imagen JPG, PNG o WEBP.');
        }

        $id = $this->storeBinary($binary, $realMime);

        if ($replaceId !== null && $replaceId !== '') {
            $this->delete($replaceId);
        }

        return $id;
    }

    public function storeStaticMap(float $lat, float $lng, int $zoom = 16): string
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new DomainException('Las coordenadas del croquis no son validas.');
        }

        $zoom = max(5, min(18, $zoom));
        $binary = $this->composeOpenStreetMapImage($lat, $lng, $zoom);
        if ($binary !== false && $binary !== '') {
            return $this->storeBinary($binary, 'image/png');
        }

        $query = http_build_query([
            'center' => sprintf('%.7F,%.7F', $lat, $lng),
            'zoom' => (string) $zoom,
            'size' => self::MAP_WIDTH . 'x' . self::MAP_HEIGHT,
            'markers' => sprintf('%.7F,%.7F,red-pushpin', $lat, $lng),
        ]);
        $url = 'https://staticmap.openstreetmap.de/staticmap.php?' . $query;
        $binary = $this->downloadImage($url);
        if ($binary === false || $binary === '') {
            throw new DomainException('No se pudo capturar el mapa. Use la opcion Cargar imagen.');
        }
        if (strlen($binary) > 5 * 1024 * 1024) {
            throw new DomainException('La captura del mapa excede 5 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary) ?: '';
        if (!isset(self::MIME_EXT[$mime])) {
            throw new DomainException('El servicio de mapa no devolvio una imagen valida.');
        }

        return $this->storeBinary($binary, $mime);
    }

    private function composeOpenStreetMapImage(float $lat, float $lng, int $zoom): string|false
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return false;
        }

        $canvas = imagecreatetruecolor(self::MAP_WIDTH, self::MAP_HEIGHT);
        if ($canvas === false) {
            return false;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $background = imagecolorallocate($canvas, 226, 232, 240);
        imagefilledrectangle($canvas, 0, 0, self::MAP_WIDTH, self::MAP_HEIGHT, $background);

        [$centerX, $centerY] = $this->latLngToPixel($lat, $lng, $zoom);
        $startX = $centerX - self::MAP_WIDTH / 2;
        $startY = $centerY - self::MAP_HEIGHT / 2;
        $minTileX = (int) floor($startX / self::TILE_SIZE);
        $maxTileX = (int) floor(($startX + self::MAP_WIDTH) / self::TILE_SIZE);
        $minTileY = (int) floor($startY / self::TILE_SIZE);
        $maxTileY = (int) floor(($startY + self::MAP_HEIGHT) / self::TILE_SIZE);
        $tileCount = 2 ** $zoom;
        $drawnTiles = 0;

        for ($tileY = $minTileY; $tileY <= $maxTileY; $tileY++) {
            if ($tileY < 0 || $tileY >= $tileCount) {
                continue;
            }

            for ($tileX = $minTileX; $tileX <= $maxTileX; $tileX++) {
                $wrappedX = (($tileX % $tileCount) + $tileCount) % $tileCount;
                $tileBinary = $this->downloadImage(sprintf(
                    'https://tile.openstreetmap.org/%d/%d/%d.png',
                    $zoom,
                    $wrappedX,
                    $tileY,
                ));

                if (!is_string($tileBinary) || $tileBinary === '') {
                    continue;
                }

                $tile = @imagecreatefromstring($tileBinary);
                if ($tile === false) {
                    continue;
                }

                imagecopy(
                    $canvas,
                    $tile,
                    (int) round($tileX * self::TILE_SIZE - $startX),
                    (int) round($tileY * self::TILE_SIZE - $startY),
                    0,
                    0,
                    self::TILE_SIZE,
                    self::TILE_SIZE,
                );
                imagedestroy($tile);
                $drawnTiles++;
            }
        }

        if ($drawnTiles === 0) {
            imagedestroy($canvas);
            return false;
        }

        $this->drawMarker($canvas, (int) round(self::MAP_WIDTH / 2), (int) round(self::MAP_HEIGHT / 2));
        $this->drawAttribution($canvas);

        ob_start();
        imagepng($canvas, null, 7);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        return is_string($binary) && $binary !== '' ? $binary : false;
    }

    /**
     * @return array{0:float,1:float}
     */
    private function latLngToPixel(float $lat, float $lng, int $zoom): array
    {
        $lat = max(-85.05112878, min(85.05112878, $lat));
        $lng = fmod($lng + 180.0, 360.0);
        if ($lng < 0) {
            $lng += 360.0;
        }
        $lng -= 180.0;

        $sinLat = sin($lat * M_PI / 180);
        $sinLat = max(-0.9999, min(0.9999, $sinLat));
        $scale = self::TILE_SIZE * (2 ** $zoom);

        return [
            ($lng + 180.0) / 360.0 * $scale,
            (0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * M_PI)) * $scale,
        ];
    }

    /**
     * @param resource|\GdImage $canvas
     */
    private function drawMarker($canvas, int $x, int $y): void
    {
        $red = imagecolorallocate($canvas, 220, 38, 38);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        imagefilledellipse($canvas, $x, $y - 18, 30, 30, $red);
        imagefilledpolygon($canvas, [
            $x, $y + 13,
            $x - 12, $y - 6,
            $x + 12, $y - 6,
        ], 3, $red);
        imagefilledellipse($canvas, $x, $y - 18, 10, 10, $white);
    }

    /**
     * @param resource|\GdImage $canvas
     */
    private function drawAttribution($canvas): void
    {
        $text = 'OpenStreetMap contributors';
        $font = 2;
        $width = imagefontwidth($font) * strlen($text);
        $height = imagefontheight($font);
        $x = max(6, self::MAP_WIDTH - $width - 12);
        $y = self::MAP_HEIGHT - $height - 6;
        $background = imagecolorallocatealpha($canvas, 255, 255, 255, 25);
        $color = imagecolorallocate($canvas, 51, 65, 85);

        imagefilledrectangle($canvas, $x - 5, $y - 3, $x + $width + 5, $y + $height + 3, $background);
        imagestring($canvas, $font, $x, $y, $text, $color);
    }

    private function downloadImage(string $url): string|false
    {
        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 12,
                    'header' => "User-Agent: DigiSecure/1.0\r\nAccept: image/png,image/jpeg,image/webp\r\n",
                ],
            ]);

            return @file_get_contents($url, false, $context, 0, 5 * 1024 * 1024 + 1);
        }

        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'DigiSecure/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/png,image/jpeg,image/webp'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($body) || $status < 200 || $status >= 300 || strlen($body) > 5 * 1024 * 1024) {
            return false;
        }

        return $body;
    }

    public function delete(?string $id): void
    {
        if ($id === null || !$this->isValidId($id)) {
            return;
        }

        foreach (self::MIME_EXT as $ext) {
            $path = $this->directoryFor($id) . DIRECTORY_SEPARATOR . $id . '.' . $ext;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{path:string,mime:string,size:int,etag:string}|null
     */
    public function find(?string $id): ?array
    {
        if ($id === null || !$this->isValidId($id)) {
            return null;
        }

        $base = realpath($this->storagePath);
        if ($base === false) {
            return null;
        }

        foreach (self::MIME_EXT as $mime => $ext) {
            $path = $this->directoryFor($id) . DIRECTORY_SEPARATOR . $id . '.' . $ext;
            $realFile = realpath($path);
            if ($realFile === false || !str_starts_with($realFile, $base) || !is_file($realFile)) {
                continue;
            }

            return [
                'path' => $realFile,
                'mime' => $mime,
                'size' => (int) filesize($realFile),
                'etag' => '"' . substr(sha1($id . filemtime($realFile)), 0, 24) . '"',
            ];
        }

        return null;
    }

    private function directoryFor(string $id): string
    {
        return rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . substr($id, 0, 2);
    }

    private function storeBinary(string $binary, string $mime): string
    {
        if (!isset(self::MIME_EXT[$mime])) {
            throw new DomainException('Formato de imagen no permitido para el croquis.');
        }

        $id = IdGenerator::ulid();
        $filename = $id . '.' . self::MIME_EXT[$mime];
        $dir = $this->directoryFor($id);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new DomainException('No se pudo preparar el storage del croquis.');
        }

        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $binary, LOCK_EX) === false) {
            throw new DomainException('No se pudo guardar la imagen del croquis.');
        }

        return $id;
    }

    private function isValidId(string $id): bool
    {
        return IdGenerator::isValid($id);
    }
}
