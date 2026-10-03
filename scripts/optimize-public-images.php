<?php

// Rebuild web derivatives without modifying the original illustrations.
$target = __DIR__.'/../resources/images/public';
if (! is_dir($target)) {
    mkdir($target, 0755, true);
}
foreach (['1', '2', '3', '4', '5', '6', '15', '19', '22', 'logo_kl'] as $name) {
    $source = imagecreatefrompng(__DIR__.'/../UI-Design/'.$name.'.png');
    $limit = $name === 'logo_kl' ? 144 : (in_array($name, ['1', '2'], true) ? 1600 : 720);
    $scale = min(1, $limit / imagesx($source));
    $image = imagescale($source, (int) round(imagesx($source) * $scale), (int) round(imagesy($source) * $scale));
    imagepalettetotruecolor($image);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagewebp($image, $target.'/'.$name.'.webp', 82);
    imagedestroy($source);
    imagedestroy($image);
}
