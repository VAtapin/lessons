<?php

declare(strict_types=1);

$source = require __DIR__.'/forgiveness.php';
$raw = json_decode(file_get_contents(__DIR__.'/forgiveness-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/forgiveness-de.php';
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
        $block['teacherNotes'] = ['ru' => $raw['screens'][$index]['notes']."\n\n".$raw['teacherPreparation'], 'de' => $de['screens'][$index]['notes']."\n\n".$de['preparation']];
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $suffix = $block['content'][$locale]['label'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['title'] ?? $block['content'][$locale]['alt'] ?? null;
            $title = $stage['content'][$locale]['title'];
            $labels[$locale] = ['title' => $suffix && $suffix !== $title ? $title.' · '.$suffix : $title, 'description' => mb_strlen($description) <= 200 ? $description : $title];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['forgiveness', 'mercy', str_replace('core.', '', $block['type'])]]];
    }
}

$cards = require __DIR__.'/forgiveness-cards.php';
foreach ($cards as $i => [$title, $text, $titleDe, $textDe, $stageIndex]) {
    $id = 'forgiveness-independent-card-'.($i + 1);
    $group = $stageIndex === 6;
    $block = ['id' => $id, 'type' => 'core.prompt', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => $title."\n".$text], 'de' => ['text' => $titleDe."\n".$textDe]], 'config' => ['kind' => 'instruction', 'target' => $group ? 'group' : 'pair'], 'teacherNotes' => ['ru' => $raw['screens'][$stageIndex]['notes']."\n\n".$raw['teacherPreparation'], 'de' => $de['screens'][$stageIndex]['notes']."\n\n".$de['preparation']]];
    $labels = ['ru' => ['title' => $title, 'description' => mb_strlen($text) <= 200 ? $text : $title], 'de' => ['title' => $titleDe, 'description' => mb_strlen($textDe) <= 200 ? $textDe : $titleDe]];
    $templates[] = ['slug' => $id, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $title, 'tags' => ['forgiveness', $group ? 'distributed-condition' : 'pair-card']]];
}

return ['id' => 'forgiveness-starter-v1', 'sourceRevision' => 'forgiveness-ru-de-starters-2026-10-03-v1', 'templates' => $templates];
