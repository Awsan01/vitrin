<?php

namespace Tests\Unit;

use App\Exceptions\UnsupportedImageException;
use App\Support\ProductImage;
use PHPUnit\Framework\TestCase;

class ProductImageTest extends TestCase
{
    /** @var list<string> */
    protected array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_large_photos_are_shrunk_to_the_long_edge_limit_and_saved_as_jpeg(): void
    {
        $output = ProductImage::normalize($this->jpeg(3000, 1500), 2048);

        $this->assertSame([2048, 1024, IMAGETYPE_JPEG], array_slice((array) getimagesizefromstring($output), 0, 3));
    }

    public function test_phone_rotation_from_exif_is_applied(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('The exif extension is not installed.');
        }

        // Stored landscape with "rotate 90° clockwise to view" (orientation 6),
        // the way phones save portrait photos.
        $output = ProductImage::normalize($this->jpeg(400, 200, exifOrientation: 6));

        $this->assertSame([200, 400], array_slice((array) getimagesizefromstring($output), 0, 2));
    }

    public function test_transparency_is_flattened_onto_white(): void
    {
        $image = imagecreatetruecolor(400, 400);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        $path = $this->tempFile();
        imagepng($image, $path);

        $output = imagecreatefromstring(ProductImage::normalize($path));
        $this->assertNotFalse($output);

        $rgb = imagecolorat($output, 200, 200);
        $this->assertGreaterThan(250, ($rgb >> 16) & 0xFF);
        $this->assertGreaterThan(250, $rgb & 0xFF);
    }

    public function test_images_over_the_pixel_limit_are_rejected_before_decoding(): void
    {
        $path = $this->jpeg(2000, 1000);

        $this->assertSame(2_000_000, ProductImage::pixelCount($path));

        $this->expectException(UnsupportedImageException::class);
        $this->expectExceptionMessage('This photo is too large to process (2 megapixels). Use one under 1 megapixels.');

        ProductImage::normalize($path, maxPixels: 1_000_000);
    }

    public function test_files_that_are_not_images_are_rejected(): void
    {
        $path = $this->tempFile();
        file_put_contents($path, '<?php echo "not an image";');

        $this->assertNull(ProductImage::pixelCount($path));

        $this->expectException(UnsupportedImageException::class);

        ProductImage::normalize($path);
    }

    protected function jpeg(int $width, int $height, ?int $exifOrientation = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 90, 160));

        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        if ($exifOrientation !== null) {
            // A minimal APP1 segment: one IFD entry, tag 0x0112 (Orientation).
            $tiff = "II*\x00\x08\x00\x00\x00"."\x01\x00"
                ."\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $exifOrientation)."\x00\x00"
                ."\x00\x00\x00\x00";
            $payload = "Exif\x00\x00".$tiff;
            $jpeg = "\xFF\xD8\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
        }

        $path = $this->tempFile();
        file_put_contents($path, $jpeg);

        return $path;
    }

    protected function tempFile(): string
    {
        return $this->files[] = (string) tempnam(sys_get_temp_dir(), 'vitrin-test');
    }
}
