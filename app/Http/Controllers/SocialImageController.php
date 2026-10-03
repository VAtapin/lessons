<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final class SocialImageController extends Controller
{
    public function __invoke(string $versionId = 'builtin-conversation-v1'): Response
    {
        $entry = collect(config('media_builtin'))->firstWhere('versionId', $versionId);
        abort_unless($entry !== null, 404);
        $jpeg = Cache::remember('social-image-v1-'.$versionId, 86400, function () use ($entry) {
            $source = imagecreatefromstring(file_get_contents(base_path($entry['file'])));
            $image = imagecreatetruecolor(1200, 630);
            imagefill($image, 0, 0, imagecolorallocate($image, 250, 247, 239));
            $scale = min(1200 / imagesx($source), 630 / imagesy($source));
            $width = (int) round(imagesx($source) * $scale);
            $height = (int) round(imagesy($source) * $scale);
            imagecopyresampled($image, $source, (int) ((1200 - $width) / 2), (int) ((630 - $height) / 2), 0, 0, $width, $height, imagesx($source), imagesy($source));
            ob_start();
            imagejpeg($image, null, 85);
            $bytes = ob_get_clean();
            imagedestroy($source);
            imagedestroy($image);

            return $bytes;
        });

        return response($jpeg, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
