<?php

declare(strict_types=1);

$source = require __DIR__.'/lazarus.php';
$raw = json_decode(file_get_contents(__DIR__.'/lazarus-source.json'), true, flags: JSON_THROW_ON_ERROR);
$notes = static fn ($index) => array_map(static fn ($locale) => str_replace('**', '', $raw[$locale]['stages'][$index]['notes']."\n\n".$raw[$locale]['preparation']), array_combine(['ru', 'de'], ['ru', 'de']));
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
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['lazarus', 'sharing', str_replace('core.', '', $block['type'])]]];
    }
}
foreach ($raw['cards'] as $i => $card) {
    $content = $labels = [];
    foreach (['ru', 'de'] as $locale) {
        $content[$locale] = ['text' => $card[$locale]['title']."\n".$card[$locale]['text']];
        $labels[$locale] = ['title' => $card[$locale]['title'], 'description' => $card[$locale]['title']];
    }
    $id = 'lazarus-independent-card-'.($i + 1);
    $templates[] = ['slug' => $id, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => ['id' => $id, 'type' => 'core.prompt', 'schemaVersion' => 1, 'content' => $content, 'config' => ['kind' => 'instruction', 'target' => $card['stage'] === 7 ? 'group' : 'pair'], 'teacherNotes' => $notes($card['stage'])], 'labels' => $labels, 'attribution' => ['title' => $card['ru']['title'], 'tags' => ['lazarus', 'support']]];
}

return ['id' => 'lazarus-starter-v1', 'sourceRevision' => 'lazarus-ru-de-starters-2026-10-04-v1', 'templates' => $templates];
