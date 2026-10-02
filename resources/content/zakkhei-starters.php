<?php

declare(strict_types=1);

$source = require __DIR__.'/zakkhei.php';
$templates = [];
foreach ($source['document']['stages'] as $stage) {
    foreach ($stage['blocks'] as $block) {
        if (in_array($block['type'], ['core.presentation', 'core.signals'], true)) {
            continue;
        }
        $labels = [];
        if ($block['type'] !== 'core.image') {
            $block['teacherNotes'] = ['ru' => $stage['content']['ru']['notes'], 'de' => $stage['content']['de']['notes']];
        }
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $labels[$locale] = ['title' => $stage['content'][$locale]['title'].($stage['id'] === 'zakkhei-step-08' ? ' · '.$description : ''), 'description' => $description];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['zakkhei', 'responsibility', str_replace('core.', '', $block['type'])]]];
    }
}

return ['id' => 'zakkhei-starter-v1', 'sourceRevision' => 'zakkhei-ru-de-starters-2026-10-02-v1', 'templates' => $templates];
