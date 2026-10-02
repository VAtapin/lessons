<?php

declare(strict_types=1);

$source = require __DIR__.'/ne-speshi-sudit.php';
$templates = [];
foreach ($source['document']['stages'] as $stage) {
    foreach ($stage['blocks'] as $block) {
        if ($block['type'] === 'core.presentation' && $block['config']['kind'] !== 'personal-choice') {
            continue;
        }
        if ($block['type'] !== 'core.image') {
            $block['teacherNotes'] = ['ru' => $stage['content']['ru']['notes'], 'de' => $stage['content']['de']['notes']];
        }
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $labels[$locale] = ['title' => $stage['content'][$locale]['title'].' · '.$description, 'description' => $description];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['ne-speshi-sudit', 'judgment', str_replace('core.', '', $block['type'])]]];
    }
}

return ['id' => 'judge-starter-v1', 'sourceRevision' => 'judge-ru-de-starters-2026-10-02-v1', 'templates' => $templates];
