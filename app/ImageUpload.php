<?php

namespace App;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class ImageUpload
{
    public function store(UploadedFile $file, string $directory, int $maxDimension, string $field = 'image'): string
    {
        try {
            $image = ImageManager::gd(autoOrientation: true, decodeAnimation: false, strip: true)
                ->read($file->getPathname());
        } catch (DecoderException $exception) {
            throw ValidationException::withMessages([
                $field => ['This image could not be processed. Please upload a valid JPG, PNG, or WebP image.'],
            ]);
        }

        $encoded = $image->scaleDown(width: $maxDimension, height: $maxDimension)->toWebp(quality: 85);
        $path = $directory.'/'.Str::uuid().'.webp';

        if (! Storage::disk('public')->put($path, (string) $encoded)) {
            throw new RuntimeException('Unable to store the converted image.');
        }

        return $path;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    public function storeMany(array $files, string $directory, int $maxDimension): array
    {
        $paths = [];
        try {
            foreach ($files as $index => $file) {
                $paths[] = $this->store($file, $directory, $maxDimension, 'images.'.$index);
            }
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        return $paths;
    }
}
