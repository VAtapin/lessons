<?php

declare(strict_types=1);

$source = require __DIR__.'/spasibo.php';
$raw = json_decode(file_get_contents(__DIR__.'/spasibo-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/spasibo-de.php';
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
        $block['teacherNotes'] = ['ru' => $raw['screens'][$index]['notes']."\n\nВсе десять получили исцеление до возвращения одного. Мотивы девяти текст не объясняет. Личные истории и молитва добровольны; личный лист не собирается. Благодарность не требует подарка или ответной услуги.", 'de' => $de['screens'][$index]['notes']."\n\nAlle zehn wurden vor der Rückkehr geheilt. Der Text nennt die Motive der neun nicht. Persönliche Geschichten und Gebet freiwillig; das persönliche Blatt nicht einsammeln. Dank verlangt kein Geschenk und keine Gegenleistung."];
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
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['thanks', 'help', str_replace('core.', '', $block['type'])]]];
    }
}

$templates[] = ['slug' => 'thanks-single-player-card', 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru',
    'block' => ['id' => 'thanks-single-player-card', 'type' => 'core.image', 'schemaVersion' => 1,
        'content' => ['ru' => ['alt' => 'Один участник сценки', 'caption' => ''], 'de' => ['alt' => 'Ein Mitspieler', 'caption' => '']],
        'teacherNotes' => ['ru' => $raw['screens'][1]['notes'], 'de' => $de['screens'][1]['notes']],
        'config' => ['fit' => 'contain'], 'media' => ['image' => ['assetId' => 'builtin-thanks-15', 'versionId' => 'builtin-thanks-15-v1']]],
    'labels' => ['ru' => ['title' => 'Один участник сценки', 'description' => 'Каждая из десяти карточек изображает одного человека.'], 'de' => ['title' => 'Ein Mitspieler', 'description' => 'Jede der zehn Spielkarten zeigt einen Menschen.']],
    'attribution' => ['title' => 'Один участник сценки', 'tags' => ['thanks', 'image']]];

return ['id' => 'thanks-starter-v1', 'sourceRevision' => 'thanks-ru-de-starters-2026-10-02-v1', 'templates' => $templates];
