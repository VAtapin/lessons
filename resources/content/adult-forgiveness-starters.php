<?php

declare(strict_types=1);

$lessons = [require __DIR__.'/adult-forgiveness.php'];
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
            $block['teacherNotes'] = array_map(static fn ($locale) => $stage['content'][$locale]['notes'], array_combine(['ru', 'de'], ['ru', 'de']));
            $labels = [];
            foreach (['ru', 'de'] as $locale) {
                $text = $block['content'][$locale];
                $title = $text['label'] ?? $text['question'] ?? $text['title'] ?? $text['alt'] ?? $stage['content'][$locale]['title'];
                $description = $text['alt'] ?? $text['question'] ?? $text['text'];
                $labels[$locale] = ['title' => $title, 'description' => mb_strlen($description) <= 200 ? $description : $stage['content'][$locale]['title']];
            }
            $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['adult-forgiveness', 'mercy', str_replace('core.', '', $block['type'])]]];
        }
    }
}

return ['id' => 'adult-forgiveness-starter-v1', 'sourceRevision' => 'adult-forgiveness-ru-de-starters-2026-10-07-v1', 'templates' => $templates];
