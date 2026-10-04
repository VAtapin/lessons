<?php

declare(strict_types=1);

$lessons = require __DIR__.'/listening.php';
$raw = json_decode(file_get_contents(__DIR__.'/listening-source.json'), true, flags: JSON_THROW_ON_ERROR);
$templates = [];
foreach ($lessons as $variant => $source) {
    foreach ($source['document']['stages'] as $index => $stage) {
        foreach ($stage['blocks'] as $block) {
            if ($block['type'] === 'core.presentation' && in_array($block['config']['kind'], ['scene', 'closing', 'response-board'], true)) {
                continue;
            }
            if ($block['type'] === 'core.presentation') {
                $block['config']['reviewBlockId'] = null;
            }
            $block['teacherNotes'] = array_map(static fn ($locale) => $raw[$locale]['variants'][$variant]['stages'][$index]['notes'], array_combine(['ru', 'de'], ['ru', 'de']));
            $labels = [];
            foreach (['ru', 'de'] as $locale) {
                $text = $block['content'][$locale];
                $title = $text['label'] ?? $text['question'] ?? $text['title'] ?? $text['alt'] ?? $stage['content'][$locale]['title'];
                $description = $text['alt'] ?? $text['question'] ?? $text['text'];
                $labels[$locale] = ['title' => $title, 'description' => mb_strlen($description) <= 200 ? $description : $stage['content'][$locale]['title']];
            }
            $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['listening', 'conversation', str_replace('core.', '', $block['type'])]]];
        }
    }
}

return ['id' => 'listening-starter-v1', 'sourceRevision' => 'listening-ru-de-starters-2026-10-04-v1', 'templates' => $templates];
