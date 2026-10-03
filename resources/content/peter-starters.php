<?php

declare(strict_types=1);

$source = require __DIR__.'/peter.php';
$raw = json_decode(file_get_contents(__DIR__.'/peter-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/peter-de.php';
$notes = static fn ($index) => ['ru' => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $raw['screens'][$index]['notes']."\n\n".$raw['teacherPreparation'])), 'de' => $de['screens'][$index]['notes']."\n\n".$de['preparation']];
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
        $block['teacherNotes'] = $notes($index);
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $suffix = $block['content'][$locale]['label'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['title'] ?? $block['content'][$locale]['alt'] ?? null;
            $title = $stage['content'][$locale]['title'];
            $labels[$locale] = ['title' => $suffix && $suffix !== $title ? $title.' · '.$suffix : $title, 'description' => mb_strlen($description) <= 200 ? $description : $title];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['peter', 'repentance', str_replace('core.', '', $block['type'])]]];
    }
}

$cards = require __DIR__.'/peter-cards.php';
foreach ($cards as $i => [$title, $text, $titleDe, $textDe, $stageIndex]) {
    $id = 'peter-independent-card-'.($i + 1);
    $group = $stageIndex === 7;
    $block = ['id' => $id, 'type' => 'core.prompt', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => $title."\n".$text], 'de' => ['text' => $titleDe."\n".$textDe]], 'config' => ['kind' => 'instruction', 'target' => $group ? 'group' : 'pair'], 'teacherNotes' => $notes($stageIndex)];
    $labels = ['ru' => ['title' => $title, 'description' => mb_strlen($text) <= 200 ? $text : $title], 'de' => ['title' => $titleDe, 'description' => mb_strlen($textDe) <= 200 ? $textDe : $titleDe]];
    $templates[] = ['slug' => $id, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $title, 'tags' => ['peter', $group ? 'distributed-condition' : 'pair-card']]];
}

return ['id' => 'peter-starter-v1', 'sourceRevision' => 'peter-ru-de-starters-2026-10-03-v1', 'templates' => $templates];
