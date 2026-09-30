<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Application\Shared\ApiProblem;
use Illuminate\Http\UploadedFile;

final class ImageUpload
{
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $file->isValid() || $path === false || ! is_file($path)) {
            throw new ApiProblem('invalid_media', 422);
        }
        $bytes = filesize($path);
        if ($bytes === false || $bytes < 1 || $bytes > config('lessons.media.max_file_bytes')) {
            throw new ApiProblem('invalid_media', 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $dimensions = @getimagesize($path);
        if (! isset($extensions[$mime]) || $dimensions === false || ($dimensions['mime'] ?? null) !== $mime
            || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] > config('lessons.media.max_dimension') || $dimensions[1] > config('lessons.media.max_dimension')
            || config('lessons.media.max_pixels') < $dimensions[0] * $dimensions[1]) {
            throw new ApiProblem('invalid_media', 422);
        }
        $decoded = @imagecreatefromstring(file_get_contents($path));
        if ($decoded === false || imagesx($decoded) !== $dimensions[0] || imagesy($decoded) !== $dimensions[1]) {
            throw new ApiProblem('invalid_media', 422);
        }
        // GdImage releases its resources when unreferenced; imagedestroy is
        // deprecated as of PHP 8.5. The original bytes are never transformed.
        unset($decoded);

        return ['mime' => $mime, 'bytes' => $bytes, 'width' => $dimensions[0], 'height' => $dimensions[1],
            'sha256' => hash_file('sha256', $path), 'extension' => $extensions[$mime]];
    }
}
