<?php

declare(strict_types=1);

$source = require __DIR__.'/poteryannaya-ovechka.php';
$templates = [];
foreach ($source['document']['stages'] as $stage) {
    foreach ($stage['blocks'] as $block) {
        if ($block['type'] === 'core.presentation' && in_array($block['config']['kind'], ['scene', 'closing'], true)) {
            // Oral activities retain their own stage instructions in the library.
            if ($block['config']['kind'] === 'closing' || in_array($stage['id'], ['sheep-step-01', 'sheep-step-04', 'sheep-step-05', 'sheep-step-08', 'sheep-step-12'], true)) {
                continue;
            }
        }
        if ($block['type'] === 'core.presentation' && $block['config']['kind'] === 'reveal') {
            // A reusable reveal is independent of the source lesson's sequence ID.
            $block['config']['reviewBlockId'] = null;
        }
        $block['teacherNotes'] = ['ru' => $stage['content']['ru']['notes'], 'de' => $stage['content']['de']['notes']];
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $suffix = $block['content'][$locale]['label'] ?? $block['content'][$locale]['title'] ?? $block['content'][$locale]['alt'] ?? null;
            if (str_ends_with($block['id'], '-cards')) {
                $suffix = $locale === 'ru' ? 'Карточки заботы' : 'Fürsorgekarten';
            } elseif (str_ends_with($block['id'], '-choice')) {
                $suffix = $locale === 'ru' ? 'Личный выбор' : 'Persönliche Wahl';
            }
            $title = $stage['content'][$locale]['title'];
            $labels[$locale] = ['title' => $suffix && $suffix !== $title ? $title.' · '.$suffix : $title, 'description' => $description];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['poteryannaya-ovechka', 'care', str_replace('core.', '', $block['type'])]]];
    }
}

return ['id' => 'sheep-starter-v1', 'sourceRevision' => 'sheep-ru-de-starters-2026-10-02-v1', 'templates' => $templates];
