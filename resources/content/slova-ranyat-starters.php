<?php

declare(strict_types=1);

$source = require __DIR__.'/slova-ranyat.php';
$templates = [];
foreach ($source['document']['stages'] as $stage) {
    foreach ($stage['blocks'] as $block) {
        if ($block['type'] !== 'core.image' && ! in_array($block['id'], ['words-step-07-answer', 'words-step-08-answer', 'words-step-09-answer', 'words-step-10-instruction', 'words-step-11-answer', 'words-step-13-answer', 'words-step-15-private', 'words-step-03-phrase-1', 'words-step-03-phrase-2'], true)) {
            continue;
        }
        $labels = [];
        if ($block['type'] !== 'core.image') {
            $block['teacherNotes'] = ['ru' => $stage['content']['ru']['notes'], 'de' => $stage['content']['de']['notes']];
        }
        foreach (['ru', 'de'] as $locale) {
            $labels[$locale] = ['title' => $stage['content'][$locale]['title'], 'description' => $block['type'] === 'core.image' ? $block['content'][$locale]['alt'] : ($block['content'][$locale]['question'] ?? $block['content'][$locale]['text'])];
            if ($block['type'] === 'core.poll') {
                $labels[$locale]['title'] .= ' · '.($locale === 'ru' ? 'Фраза ' : 'Satz ').substr($block['id'], -1);
            }
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels,
            'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['words', 'communication', str_replace('core.', '', $block['type'])]]];
    }
}

return ['id' => 'words-starter-v1', 'sourceRevision' => 'words-ru-de-starters-2026-10-01-v1', 'templates' => $templates];
