<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FreightMediaService
{
    public function storeOptimizedImage(UploadedFile $file, string $directory, ?string $previousPath = null): array
    {
        $originalBytes = (int) $file->getSize();
        $originalName = $file->getClientOriginalName();
        $originalMime = $file->getClientMimeType();
        $contents = file_get_contents($file->getRealPath());

        $image = $contents !== false && function_exists('imagecreatefromstring')
            ? @imagecreatefromstring($contents)
            : false;

        if ($image === false) {
            $path = $file->store($directory, 'public');
            $meta = [
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'original_size_bytes' => $originalBytes,
                'original_mime_type' => $originalMime,
                'original_name' => $originalName,
                'optimized' => false,
            ];

            $this->deletePrevious($previousPath);

            return ['path' => $path, 'meta' => $meta];
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        [$targetWidth, $targetHeight] = $this->fitSize($sourceWidth, $sourceHeight);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        imagejpeg($canvas, null, 82);
        $optimized = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        if ($optimized === false) {
            $path = $file->store($directory, 'public');
            $size = (int) $file->getSize();
            $mime = $file->getClientMimeType();
            $optimizedFlag = false;
        } else {
            $path = trim($directory, '/').'/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($path, $optimized);
            $size = strlen($optimized);
            $mime = 'image/jpeg';
            $optimizedFlag = true;
        }

        $this->deletePrevious($previousPath);

        return [
            'path' => $path,
            'meta' => [
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'original_size_bytes' => $originalBytes,
                'original_mime_type' => $originalMime,
                'original_name' => $originalName,
                'width' => $targetWidth,
                'height' => $targetHeight,
                'original_width' => $sourceWidth,
                'original_height' => $sourceHeight,
                'optimized' => $optimizedFlag,
            ],
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitSize(int $width, int $height): array
    {
        $maxWidth = 1600;
        $maxHeight = 1200;
        $ratio = min($maxWidth / max($width, 1), $maxHeight / max($height, 1), 1);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function deletePrevious(?string $previousPath): void
    {
        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }
    }
}
