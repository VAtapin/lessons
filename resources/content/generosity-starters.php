<?php

declare(strict_types=1);

$source = require __DIR__.'/generosity.php';
$raw = json_decode(file_get_contents(__DIR__.'/generosity-source.json'), true, flags: JSON_THROW_ON_ERROR);
$notes = static fn ($index) => array_map(static fn ($data) => str_replace('**', '', $data['stages'][$index]['notes']."\n\n".$data['preparation']), $raw);
$templates = [];
foreach ($source['document']['stages'] as $index => $stage) {
    foreach ($stage['blocks'] as $block) {
        if ($block['type'] === 'core.presentation' && in_array($block['config']['kind'], ['scene', 'closing'], true)) {
            continue;
        }
        if ($block['type'] === 'core.presentation') {
            $block['config']['reviewBlockId'] = null;
        }
        $block['teacherNotes'] = $notes($index);
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $text = $block['content'][$locale];
            $title = $text['label'] ?? $text['question'] ?? $text['title'] ?? $text['alt'] ?? $stage['content'][$locale]['title'];
            $description = $text['alt'] ?? $text['question'] ?? $text['text'];
            $labels[$locale] = ['title' => $title, 'description' => mb_strlen($description) <= 200 ? $description : $stage['content'][$locale]['title']];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['generosity', 'sharing', str_replace('core.', '', $block['type'])]]];
    }
}
foreach ([1, 3, 5, 7, 9, 11, 13, 14, 15, 16, 18, 20, 22, 24, 26, 28, 31, 33, 35, 37, 39, 41, 44, 46, 48, 50] as $index => $cell) {
    $content = $labels = [];
    foreach (['ru', 'de'] as $locale) {
        $text = implode("\n", array_filter($raw[$locale]['cards'][$cell]));
        [$title, $description] = explode("\n", $text, 2);
        $content[$locale] = ['text' => $text];
        $labels[$locale] = ['title' => $title, 'description' => mb_strlen($description) <= 200 ? $description : $title];
    }
    $stageIndex = $index < 6 ? 2 : ($index < 10 ? 4 : ($index < 16 ? 5 : 7));
    $id = 'generosity-independent-card-'.($index + 1);
    $block = ['id' => $id, 'type' => 'core.prompt', 'schemaVersion' => 1, 'content' => $content, 'config' => ['kind' => 'instruction', 'target' => $index >= 16 ? 'group' : 'pair'], 'teacherNotes' => $notes($stageIndex)];
    $templates[] = ['slug' => $id, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['generosity', $index >= 22 ? 'distributed-condition' : 'card']]];
}

return ['id' => 'generosity-starter-v1', 'sourceRevision' => 'generosity-ru-de-starters-2026-10-04-v1', 'templates' => $templates];
