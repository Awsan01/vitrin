<?php

namespace App\Support;

use App\Exceptions\UnsupportedImageException;
use GdImage;

/**
 * Prepares an uploaded product photo for storage and for the AI models:
 * fixes phone-camera rotation, flattens transparency onto white, shrinks it
 * so the long edge fits the limit, and re-encodes it as a JPEG.
 *
 * Uses GD, which is enabled on Hostinger and most PHP hosts. GD decodes the
 * whole image at about 4 bytes per pixel, so the pixel count is checked from
 * the file header before decoding, and the memory limit is raised for large
 * photos (PHP's default 128 MB is less than a 24 MP phone photo needs).
 */
class ProductImage
{
    private const SUPPORTED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    private const BYTES_PER_PIXEL = 4;

    /**
     * Width × height read from the file header, or null if it isn't a
     * supported image. Doesn't decode the image.
     */
    public static function pixelCount(string $path): ?int
    {
        $header = self::header($path);

        return $header === null ? null : $header[0] * $header[1];
    }

    /**
     * @throws UnsupportedImageException
     */
    public static function normalize(string $path, int $maxDimension = 2048, int $maxPixels = 50_000_000, int $quality = 88): string
    {
        $header = self::header($path);

        if ($header === null) {
            throw new UnsupportedImageException('The uploaded file is not a readable JPEG, PNG or WebP image.');
        }

        [$headerWidth, $headerHeight, $type] = $header;
        $pixels = $headerWidth * $headerHeight;

        if ($pixels > $maxPixels) {
            throw new UnsupportedImageException(sprintf(
                'This photo is too large to process (%d megapixels). Use one under %d megapixels.',
                (int) ceil($pixels / 1_000_000),
                intdiv($maxPixels, 1_000_000),
            ));
        }

        // The decoded original, plus the resized copy and its rotated twin.
        self::ensureMemoryFor($pixels * self::BYTES_PER_PIXEL + 2 * $maxDimension ** 2 * self::BYTES_PER_PIXEL);

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new UnsupportedImageException('The uploaded file is not a readable JPEG, PNG or WebP image.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxDimension / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Free the full-size original before rotating, so only the small
        // copy is ever rotated.
        unset($image);

        if ($type === IMAGETYPE_JPEG) {
            $canvas = self::applyExifOrientation($canvas, $path);
        }

        ob_start();
        imagejpeg($canvas, null, $quality);

        return (string) ob_get_clean();
    }

    /**
     * Width, height and IMAGETYPE_* constant from the file header, or null
     * if the file isn't a supported image.
     *
     * @return array{int, int, int}|null
     */
    protected static function header(string $path): ?array
    {
        $info = @getimagesize($path);

        if ($info === false || ! in_array($info[2], self::SUPPORTED_TYPES, true) || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        return [$info[0], $info[1], $info[2]];
    }

    protected static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotate = fn (GdImage $img, int $angle): GdImage => imagerotate($img, $angle, 0) ?: $img;

        return match ($orientation) {
            2 => tap($image, fn ($img) => imageflip($img, IMG_FLIP_HORIZONTAL)),
            3 => $rotate($image, 180),
            4 => tap($image, fn ($img) => imageflip($img, IMG_FLIP_VERTICAL)),
            5 => tap($rotate($image, -90), fn ($img) => imageflip($img, IMG_FLIP_HORIZONTAL)),
            6 => $rotate($image, -90),
            7 => tap($rotate($image, 90), fn ($img) => imageflip($img, IMG_FLIP_HORIZONTAL)),
            8 => $rotate($image, 90),
            default => $image,
        };
    }

    /**
     * Raise the memory limit for the rest of this request when decoding
     * needs more than it allows.
     */
    protected static function ensureMemoryFor(int $bytes): void
    {
        $limit = self::toBytes((string) ini_get('memory_limit'));
        $needed = memory_get_usage(true) + $bytes + 32 * 1024 * 1024;

        if ($limit !== -1 && $limit < $needed) {
            ini_set('memory_limit', (string) $needed);
        }
    }

    /**
     * A php.ini size such as "128M" or "1G" in bytes; -1 means no limit.
     */
    protected static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
