<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/poteryannaya-ovechka-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/poteryannaya-ovechka-de.php';
$version = 'bc3a05b4-1b2b-40ba-9b26-a80fed6625ab';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn (string $id, string $type, array $ru, array $german, array $config = [], ?array $solution = null): array => array_filter([
    'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $german], 'config' => $config, 'solution' => $solution,
], static fn ($value) => $value !== null);
$alt = [['Пастух с найденной овечкой', 'Hirte mit dem gefundenen Schaf'], ['Овечка за кустом', 'Schaf hinter dem Busch'], ['Пастух находит овечку', 'Der Hirte findet das Schaf'], ['Приглашение в общую игру', 'Einladung zum gemeinsamen Spiel']];
$images = [1 => 1, 4 => 2, 5 => 3, 10 => 4];
$stages = [];
foreach ($raw['screens'] as $index => $screen) {
    $n = $screen['number'];
    $id = 'sheep-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $slideIndex = $screen['slides'][0] - 1;
    $lines = $raw['slides'][$slideIndex]['slide'];
    $translated = $de['slides'][$slideIndex];
    $ru = ['title' => $n === 1 ? $raw['title'] : $lines[0], 'text' => implode("\n", array_slice($lines, $n === 1 ? 2 : 1)), 'modes' => []];
    $german = ['title' => $translated['title'], 'text' => $translated['text'], 'modes' => []];
    if ($n === 1) {
        $ru['text'] = '5–7 лет · 35 минут';
    }
    if ($n === 5) {
        $ru['source'] = 'Лк. 15:5';
        $ru['text'] = "Он радуется\nи бережно несёт\nовечку.";
        $german['source'] = $translated['source'];
    }
    if ($n === 9) {
        $ru['text'] = "Бог рад, когда люди\nвозвращаются к Нему.";
        $ru['quote'] = '«Прости меня. Я хочу помириться».';
        $ru['source'] = 'Лк. 15:7';
        $german += ['quote' => $translated['quote'], 'source' => $translated['source']];
    }
    if ($n === 12) {
        $ru['text'] = "Пастух искал и нашёл овечку.\nОн радовался.";
        $ru['subtitle'] = 'Какую заботу я покажу на этой неделе?';
        $german['subtitle'] = $translated['question'];
    }
    if ($n === 8) {
        // The mixed cards appear once as movable controls, not as a second text list.
        $ru['text'] = $ru['title'];
    }
    $ru['text'] = $ru['text'] === '' ? $ru['title'] : $ru['text'];
    $german['text'] = $german['text'] === '' ? $german['title'] : $german['text'];
    $blocks = [];
    if ($n === 2) {
        // One teacher-controlled picture changes both pupil and projector views.
        $modes = static fn (string $locale): array => [
            ['modeId' => 'herd', 'count' => 5, 'title' => $locale === 'ru' ? 'Наше маленькое стадо' : 'Unsere kleine Herde', 'text' => $locale === 'ru' ? 'Сколько овечек в нашей игре?' : 'Wie viele Schafe sind in unserem Spiel?', 'label' => $locale === 'ru' ? 'Показать пять овечек' : 'Fünf Schafe zeigen'],
            ['modeId' => 'missing', 'count' => 4, 'title' => $locale === 'ru' ? 'Одной не хватает' : 'Eines fehlt', 'text' => $locale === 'ru' ? 'Кого не хватает?' : 'Wer fehlt?', 'label' => $locale === 'ru' ? 'Убрать одну овечку' : 'Ein Schaf wegnehmen'],
        ];
        $block = $make($id.'-herd', 'presentation', ['title' => 'Наше маленькое стадо', 'text' => 'Овечка', 'modes' => $modes('ru')], ['title' => 'Unsere kleine Herde', 'text' => 'Schaf', 'modes' => $modes('de')], array_replace($base, ['kind' => 'picture-count', 'maxItems' => 5]));
        $block['media'] = ['image' => ['assetId' => 'builtin-sheep-5', 'versionId' => 'builtin-sheep-5-v1']];
        $blocks[] = $block;
    } else {
        $blocks[] = $make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story']);
    }
    if (isset($images[$n])) {
        $imageN = $images[$n];
        $image = $make($id.'-image', 'image', ['alt' => $alt[$imageN - 1][0], 'caption' => ''], ['alt' => $alt[$imageN - 1][1], 'caption' => ''], ['fit' => 'contain']);
        $image['media'] = ['image' => ['assetId' => 'builtin-sheep-'.$imageN, 'versionId' => 'builtin-sheep-'.$imageN.'-v1']];
        $blocks[] = $image;
    }
    if ($n === 4) {
        foreach ([['Овечка рядом с зелёным', 'Das Schaf ist neben etwas Grünem.'], ['Овечка спрятана под тканью', 'Das Schaf ist unter einem Tuch versteckt.']] as $i => [$text, $germanText]) {
            $blocks[] = $make($id.'-hint-'.($i + 1), 'presentation', ['text' => $text, 'label' => 'Подсказка '.($i + 1), 'hideLabel' => 'Скрыть подсказку', 'modes' => []], ['text' => $germanText, 'label' => 'Hinweis '.($i + 1), 'hideLabel' => 'Hinweis ausblenden', 'modes' => []], array_replace($base, ['kind' => 'reveal']));
        }
    } elseif ($n === 8) {
        $ruItems = ['Нашёл', 'Одной нет', 'Радуется', 'Ищет'];
        $deItems = ['Er findet es', 'Eines fehlt', 'Er freut sich', 'Er sucht'];
        $items = static fn (array $texts): array => array_map(static fn ($text, $i) => ['itemId' => 'event-'.($i + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-sequence', 'sequence', ['question' => 'Что было сначала?', 'items' => $items($ruItems), 'emptyText' => '…', 'reviewLabel' => 'История по порядку'], ['question' => 'Was geschah zuerst?', 'items' => $items($deItems), 'emptyText' => '…', 'reviewLabel' => 'Die Geschichte in der richtigen Reihenfolge'], ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-4', 'event-1', 'event-3']]);
        $blocks[] = $make($id.'-review', 'presentation', ['label' => 'История по порядку', 'hideLabel' => 'Скрыть историю по порядку', 'text' => "История по порядку\n1. Одной нет\n2. Ищет\n3. Нашёл\n4. Радуется", 'modes' => []], ['label' => $de['slides'][9]['title'], 'hideLabel' => 'Geordnete Geschichte ausblenden', 'text' => $de['slides'][9]['title']."\n".$de['slides'][9]['text'], 'modes' => []], array_replace($base, ['kind' => 'reveal', 'reviewBlockId' => $id.'-sequence']));
    } elseif ($n === 12) {
        $ruActions = ['ПРИГЛАШУ — «Хочешь играть с нами?»', 'ПОМОГУ — Подам нужный кубик.', 'ПОКАЖУ — Помогу понять, как начать.'];
        $deActions = ['ICH LADE EIN — „Möchtest du mit uns spielen?“', 'ICH HELFE — Ich reiche den benötigten Baustein.', 'ICH ZEIGE — Ich helfe zu verstehen, wie man anfängt.'];
        $blocks[] = $make($id.'-cards', 'prompt', ['text' => implode("\n", $ruActions)], ['text' => implode("\n", $deActions)], ['kind' => 'reflection', 'target' => 'class']);
        $modes = static fn (array $texts): array => array_map(static fn ($text, $i) => ['modeId' => 'care-'.($i + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-choice', 'presentation', ['text' => 'Можно сказать свой выбор или показать на карточку.', 'modes' => $modes($ruActions)], ['text' => 'Du kannst deine Wahl sagen oder auf eine Karte zeigen.', 'modes' => $modes($deActions)], array_replace($base, ['kind' => 'personal-choice']));
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Каждый дорог Богу', 'text' => $ru['text'], 'eyebrow' => 'Урок завершён', 'modes' => []], ['title' => 'Jeder ist Gott wichtig', 'text' => $german['text'], 'eyebrow' => 'Die Stunde ist beendet', 'modes' => []], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $de['screens'][$index]['title'], 'notes' => $de['screens'][$index]['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'lavender'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'sheep-file-') && str_ends_with($id, '-v1')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['number'].' '.$s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n5–7 Jahre · 35 Minuten · 6–16 Kinder. Alle Beschriftungen liest die Lehrkraft; Gesten sind gleichwertige Antworten.\nZiel: Jeder Mensch ist Gott wichtig. Gott freut sich über die Rückkehr zu ihm. Fürsorge zeigt sich durch konkrete Handlungen.\n".implode("\n", $de['goals'])."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['handout'];

return [
    'sourceRevision' => 'sheep-ru-de-2026-10-02-v1', 'materialId' => '3e2b45cb-69d8-4e90-b242-329fdd7ec716', 'versionId' => $version, 'ownerKey' => 'f3b8b0af-ebdf-46cf-9918-e0b85c9f5b9d', 'slug' => 'poteryannaya-ovechka',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => explode("\n\n", trim($raw['description']))[1]], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['5-7'], 'topic' => ['bible', 'parables', 'mercy'], 'audience' => ['school', 'sunday-school', 'family', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 35, 'cover' => ['assetId' => 'builtin-sheep-1', 'versionId' => 'builtin-sheep-1-v1'],
        'details' => ['ru' => ['goals' => ['Показать порядок событий: одной нет, пастух ищет, находит и радуется.', 'Сказать, что потерянная овечка была важна пастуху и каждый человек дорог Богу.', 'Назвать или показать один способ пригласить другого ребёнка и помочь ему.'], 'materials' => ['Четыре страницы раздатки, шесть овечек и конверт.', 'Зелёная бумага, бежевый лист, зелёная салфетка, фигурка пастуха и игрушка.', '3–4 крупных кубика на пару, экран и Библия: Лк. 15:3–7.'], 'devices' => 'Очное занятие с экраном, общим поиском, настольным театром и работой в парах. Устройства детей не обязательны. Учитель читает все подписи, дети могут отвечать жестами.', 'conditions' => '5–7 лет. 35 минут. 6–16 детей. Слова, жесты и действия с карточками равноценны. Молитва и роли с репликами по желанию.'], 'de' => ['goals' => $de['goals'], 'materials' => ['Vier Seiten Kopiervorlagen, sechs Schafe und ein Umschlag.', 'Grünes und beiges Papier, grüne Serviette, Hirtenfigur und Spielzeug.', '3–4 große Bausteine pro Paar, Bildschirm und Bibel: Lukas 15,3–7.'], 'devices' => 'Präsenzunterricht mit gemeinsamer Suche, Tischtheater und Paarspielen. Kinder brauchen kein Gerät. Die Lehrkraft liest alle Beschriftungen; Gesten sind willkommen.', 'conditions' => '5–7 Jahre. 35 Minuten. 6–16 Kinder. Worte, Gesten und Kartenhandlungen sind gleichwertig. Gebet und Sprechrollen freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
