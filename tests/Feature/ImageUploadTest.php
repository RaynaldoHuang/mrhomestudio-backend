<?php

namespace Tests\Feature;

use App\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    #[TestWith(['jpg'])]
    #[TestWith(['png'])]
    #[TestWith(['webp'])]
    public function test_upload_is_saved_as_real_webp_without_original_file(string $extension): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('original.'.$extension, 120, 80);

        $path = (new ImageUpload)->store($file, 'products', 1600);

        Storage::disk('public')->assertExists($path);
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame('image/webp', $dimensions['mime']);
        $this->assertSame([120, 80], [$dimensions[0], $dimensions[1]]);
        $this->assertSame([$path], Storage::disk('public')->allFiles('products'));
    }

    #[TestWith([2000, 1000, 1600, 1600, 800])]
    #[TestWith([1000, 2000, 1600, 800, 1600])]
    #[TestWith([3000, 1500, 2400, 2400, 1200])]
    public function test_resize_preserves_proportions_and_caps_longest_side(int $width, int $height, int $limit, int $expectedWidth, int $expectedHeight): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('large.jpg', $width, $height);

        $path = (new ImageUpload)->store($file, 'images', $limit);

        Storage::disk('public')->assertExists($path);
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([$expectedWidth, $expectedHeight], [$dimensions[0], $dimensions[1]]);
    }

    public function test_png_transparency_is_preserved_in_webp(): void
    {
        Storage::fake('public');
        $source = imagecreatetruecolor(32, 24);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
        imagefilledrectangle($source, 10, 8, 20, 16, imagecolorallocatealpha($source, 255, 0, 0, 0));
        ob_start();
        imagepng($source);
        $file = UploadedFile::fake()->createWithContent('transparent.png', ob_get_clean());

        $path = (new ImageUpload)->store($file, 'products', 1600);

        Storage::disk('public')->assertExists($path);
        $result = imagecreatefromstring(Storage::disk('public')->get($path));
        $this->assertSame(127, imagecolorsforindex($result, imagecolorat($result, 0, 0))['alpha']);
        $this->assertSame(0, imagecolorsforindex($result, imagecolorat($result, 15, 12))['alpha']);
    }

    public function test_camera_orientation_is_applied_before_encoding(): void
    {
        Storage::fake('public');
        $original = UploadedFile::fake()->image('camera.jpg', 100, 50);
        $jpeg = file_get_contents($original->getPathname());
        $tiff = 'II'.pack('vV', 42, 8).pack('v', 1).pack('vvV', 0x0112, 3, 1).pack('v', 6)."\0\0".pack('V', 0);
        $exif = "Exif\0\0".$tiff;
        $file = UploadedFile::fake()->createWithContent('camera.jpg', "\xff\xd8\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));

        $path = (new ImageUpload)->store($file, 'products', 1600);

        Storage::disk('public')->assertExists($path);
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([50, 100], [$dimensions[0], $dimensions[1]]);
    }

    public function test_failed_batch_removes_already_converted_images(): void
    {
        Storage::fake('public');
        $files = [UploadedFile::fake()->image('valid.jpg'), UploadedFile::fake()->createWithContent('broken.jpg', 'invalid image')];

        try {
            (new ImageUpload)->storeMany($files, 'products', 1600);
            $this->fail('A corrupt upload must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['images.1' => ['This image could not be processed. Please upload a valid JPG, PNG, or WebP image.']], $exception->errors());
        }

        $this->assertSame([], Storage::disk('public')->allFiles('products'));
    }
}
