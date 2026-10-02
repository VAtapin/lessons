<?php

declare(strict_types=1);

$releases = require __DIR__.'/illustrated-releases.php';
$media = json_decode(file_get_contents(__DIR__.'/illustrated-source.json'), true, flags: JSON_THROW_ON_ERROR);
$templates = [];
foreach ($media as $key => $lesson) {
    foreach ($lesson['slides'] as $slide) {
        $index = array_search(true, array_map(static fn ($numbers) => in_array($slide['number'], $numbers, true), $lesson['mapping']), true);
        $stage = $releases[$key]['next']['document']['stages'][$index];
        $block = ['id' => 'illustrated-'.$key.'-'.$slide['number'], 'type' => 'core.image', 'schemaVersion' => 1,
            'content' => array_map(static fn ($content) => ['alt' => $content['title'], 'caption' => ''], $stage['content']),
            'config' => ['fit' => 'contain'], 'media' => ['image' => $slide['media']]];
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $labels[$locale] = ['title' => $stage['content'][$locale]['title'].' · '.($locale === 'ru' ? 'Слайд ' : 'Folie ').$slide['number'],
                'description' => $releases[$key]['next']['metadata']['translations'][$locale]['title']];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels,
            'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['illustrated', $key]]];
    }
}

return ['id' => 'illustrated-starter-v2', 'sourceRevision' => 'illustrated-2026-10-02-v2', 'templates' => $templates];
