<?php

declare(strict_types=1);

$source = require __DIR__.'/strakh.php';
$raw = json_decode(file_get_contents(__DIR__.'/strakh-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/strakh-de.php';
$templates = [];
foreach ($source['document']['stages'] as $index => $stage) {
    foreach ($stage['blocks'] as $block) {
        if ($block['type'] === 'core.presentation' && in_array($block['config']['kind'], ['scene', 'closing'], true)) {
            continue;
        }
        if ($block['type'] === 'core.presentation' && $block['config']['kind'] === 'reveal') {
            // A reusable reveal is independent of the source lesson's sequence ID.
            $block['config']['reviewBlockId'] = null;
        }
        $block['teacherNotes'] = ['ru' => $raw['screens'][$index]['notes']."\n\n".explode('Библейский текст для чтения', $raw['teacherPreparation'])[0], 'de' => $de['screens'][$index]['notes']."\n\n".explode('Biblischer Lesetext:', $de['preparation'])[0]];
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $suffix = $block['content'][$locale]['label'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['title'] ?? $block['content'][$locale]['alt'] ?? null;
            if (str_ends_with($block['id'], '-cards')) {
                $suffix = $locale === 'ru' ? 'Карточки значений' : 'Bedeutungskarten';
            }
            $title = $stage['content'][$locale]['title'];
            $labels[$locale] = ['title' => $suffix && $suffix !== $title ? $title.' · '.$suffix : $title, 'description' => mb_strlen($description) <= 200 ? $description : $title];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['fear', 'help', str_replace('core.', '', $block['type'])]]];
    }
}

return ['id' => 'fear-starter-v1', 'sourceRevision' => 'fear-ru-de-starters-2026-10-02-v1', 'templates' => $templates];
