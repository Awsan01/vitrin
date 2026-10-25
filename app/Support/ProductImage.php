<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Prepares an uploaded product photo for storage and for the AI models:
 * fixes phone-camera rotation, flattens transparency onto white, shrinks it
 * so the long edge fits the limit, and re-encodes it as a JPEG.
 *
 * Uses GD, which is enabled on Hostinger and most PHP hosts.
 */
class ProductImage
{
    public static function normalize(string $path, int $maxDimension = 2048, int $quality = 88): string
    {
        $info = @getimagesize($path);
        $type = $info === false ? null : $info[2];

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The uploaded file is not a readable JPEG, PNG or WebP image.');
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = self::applyExifOrientation($image, $path);
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

        ob_start();
        imagejpeg($canvas, null, $quality);

        return (string) ob_get_clean();
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
}
