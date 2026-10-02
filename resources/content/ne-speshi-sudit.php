<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/ne-speshi-sudit-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/ne-speshi-sudit-de.php';
$version = '14d518ee-c4be-4e6a-aec2-1fb87e90a19e';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn (string $id, string $type, array $ru, array $de, array $config = [], ?array $solution = null): array => array_filter([
    'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $de], 'config' => $config, 'solution' => $solution,
], static fn ($value) => $value !== null);
$imageNumbers = [1 => 1, 3 => 2, 5 => 3, 10 => 4];
$alt = [1 => ['Первое впечатление', 'Der erste Eindruck'], 2 => ['Ученик сидит отдельно', 'Ein Schüler sitzt allein'], 3 => ['Разговор с учителем', 'Ein Gespräch mit der Lehrkraft'], 4 => ['Разговор после урока', 'Ein Gespräch nach dem Unterricht']];
$ruText = [1 => '13–15 лет · 45 минут', 2 => "Ученик не включился\nв общую работу.", 3 => "Он сидит отдельно.\nОн не хочет помогать.\nОн всегда эгоист.", 4 => "«Вынь прежде бревно\nиз твоего глаза».", 5 => "Он просит учителя помочь.\nМы всё ещё не знаем\nполной истории.", 6 => "Я тоже иногда делаю выводы,\nне задав вопроса.", 7 => "«Ты лентяй».\n«Твоя часть пока не готова».", 8 => "В чате травят ученика.\nМожно ли назвать это вредом?", 9 => "«Что случилось?»\n«Я правильно понял?»\n«Какая помощь нужна?»", 10 => "«Я заметил, что работа не готова.\nЧто произошло?»", 11 => "«Она всегда всё портит».\nКак сказать о конкретном случае?", 12 => "Я могу проверить факт,\nзаметить свою ошибку\nи задать вопрос."];
$options = static fn (array $texts) => array_map(static fn ($text, $i) => ['optionId' => 'option-'.($i + 1), 'text' => $text], $texts, array_keys($texts));
$free = static fn (string $id, string $ru, string $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 300]);
$stages = [];
foreach ($raw['screens'] as $index => $screen) {
    $number = $screen['number'];
    $translated = $de['screens'][$index];
    $id = 'judge-step-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    $ru = ['title' => $screen['title'], 'text' => $ruText[$number], 'eyebrow' => $number === 1 ? 'Интерактивный урок · 13–15 лет · 45 минут' : 'Не спеши судить · '.$number.' / 12', 'modes' => []];
    $german = ['title' => $translated['title'], 'text' => $translated['text'], 'eyebrow' => $number === 1 ? 'Interaktiver Unterricht · 13–15 Jahre · 45 Minuten' : 'Urteile nicht vorschnell · '.$number.' / 12', 'modes' => []];
    if (isset($translated['question'])) {
        $ru['subtitle'] = end($screen['slide']);
        $german['subtitle'] = $translated['question'];
    }
    if ($number === 4) {
        $ru['source'] = 'Мф. 7:1–5';
        $german['source'] = 'Matthäus 7,1–5 · Übersetzung des bereitgestellten russischen Textes';
    }
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $number === 1 ? 'cover' : 'story'])];
    if (isset($imageNumbers[$number])) {
        $n = $imageNumbers[$number];
        $image = $make($id.'-image', 'image', ['alt' => $alt[$n][0], 'caption' => ''], ['alt' => $alt[$n][1], 'caption' => ''], ['fit' => 'contain']);
        $image['media'] = ['image' => ['assetId' => 'builtin-judge-'.$n, 'versionId' => 'builtin-judge-'.$n.'-v1']];
        $blocks[] = $image;
    }
    if (in_array($number, [2, 5, 8], true)) {
        // First impressions and the renewed decision are separate, ungraded records.
        $blocks[] = $make($id.'-answer', 'single-choice', ['question' => $screen['fields']['Задание'], 'options' => $options(explode(' / ', $screen['fields']['Варианты выбора']))], ['question' => $translated['task'], 'options' => $options($number === 8 ? $translated['options'] : $de['options'])], ['allowRepeat' => true], $number === 8 ? ['optionId' => 'option-2'] : null);
        if ($number !== 8) {
            $modes = static fn ($texts) => array_map(static fn ($text, $i) => ['modeId' => 'confidence-'.$i, 'text' => $text, 'label' => $text], $texts, array_keys($texts));
            $blocks[] = $make($id.'-confidence', 'presentation', ['text' => 'Уверенность', 'modes' => $modes(['0 — не знаю', '1 — предполагаю', '2 — почти уверен', '3 — полностью уверен'])], ['text' => 'Sicherheit', 'modes' => $modes($de['confidence'])], array_replace($base, ['kind' => 'personal-choice']));
            $blocks[] = $make($id.'-record', 'prompt', ['text' => $number === 2 ? 'Запишите букву и уверенность. Свой лист пока оставьте у себя.' : 'Сравните записи. Можно изменить ответ или сохранить его, но важно объяснить основание.'], ['text' => $number === 2 ? 'Notiere den Buchstaben und deine Sicherheit. Behalte dein Blatt zunächst selbst.' : 'Vergleiche die Notizen. Du darfst ändern oder beibehalten; begründe deine Entscheidung.'], ['kind' => 'reflection', 'target' => 'class']);
        }
    } elseif ($number === 3) {
        $items = static fn ($key, $texts) => array_map(static fn ($text, $i) => ['itemId' => $key.($i + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-answer', 'matching', ['question' => $screen['fields']['Задание'], 'left' => $items('claim-', ['Он сидит отдельно.', 'Он не хочет помогать.', 'Он всегда эгоист.']), 'right' => $items('category-', ['Факт', 'Предположение', 'Ярлык'])], ['question' => $translated['task'], 'left' => $items('claim-', ['Er sitzt allein.', 'Er will nicht helfen.', 'Er ist immer egoistisch.']), 'right' => $items('category-', ['Fakt', 'Vermutung', 'Pauschales Urteil'])], ['allowRepeat' => true], ['pairs' => [['leftId' => 'claim-1', 'rightId' => 'category-1'], ['leftId' => 'claim-2', 'rightId' => 'category-2'], ['leftId' => 'claim-3', 'rightId' => 'category-3']]]);
    } elseif (in_array($number, [9, 11, 12], true)) {
        $blocks[] = $free($id.'-answer', $number === 12 ? 'Прежде чем осудить человека, я могу…' : $screen['fields']['Задание'], $number === 12 ? 'Bevor ich einen Menschen verurteile, kann ich …' : $translated['task']);
        if ($number === 11) {
            $blocks[] = $make($id.'-weekly', 'prompt', ['text' => 'Затем выберите один шаг на неделю: сделать паузу перед выводом, уточнить факт или признать собственную поспешность.'], ['text' => 'Wähle einen Schritt für die Woche: vor dem Urteil innehalten, einen Fakt klären oder eigene Voreiligkeit zugeben.'], ['kind' => 'reflection', 'target' => 'class']);
        }
        if ($number === 12) {
            $blocks[] = $make($id.'-closing', 'presentation', ['title' => $screen['title'], 'text' => $screen['fields']['Вывод'], 'quote' => $ruText[12], 'eyebrow' => 'Урок завершён', 'modes' => []], ['title' => $translated['title'], 'text' => 'Ein klarer Blick auf uns selbst hilft, anderen ehrlich und behutsam zu begegnen.', 'quote' => $translated['text'], 'eyebrow' => 'Die Stunde ist beendet', 'modes' => []], array_replace($base, ['kind' => 'closing']));
        }
    } else {
        $blocks[] = $make($id.'-instruction', 'prompt', ['text' => $screen['fields']['Задание'].($number === 6 ? "\nЛичные записи можно оставить при себе." : '')], ['text' => $translated['task'].($number === 6 ? "\nPersönliche Notizen dürfen bei dir bleiben." : '')], ['kind' => $number === 6 ? 'reflection' : 'discussion', 'target' => in_array($number, [6, 7, 10], true) ? 'pair' : 'class']);
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $translated['title'], 'notes' => $translated['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'slate'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'judge-file-') && str_ends_with($id, '-v1')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['number'].' '.$s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = [$de['title'], '13–15 Jahre · 45 Minuten · 6–20 Teilnehmende · Matthäus 7,1–5.', 'Ziel: Beobachtungen von Urteilen über Menschen unterscheiden, eigene Fehler erkennen und respektvoll über konkrete Handlungen sprechen.', implode("\n", $de['goals'])];
$elapsed = 0;
foreach ($raw['screens'] as $i => $screen) {
    $dePlan[] = $elapsed.'–'.($elapsed + $screen['durationMinutes']).' · '.($i + 1).' '.$de['screens'][$i]['title']."\n".$de['screens'][$i]['task']."\n".$de['screens'][$i]['notes'];
    $elapsed += $screen['durationMinutes'];
}
$dePlan[] = $de['preparation'];
$dePlan[] = $de['handout'];

return [
    'sourceRevision' => 'judge-ru-de-2026-10-02-v1', 'materialId' => 'f8e8d3c4-565e-41fa-8e36-5c2ad5bde883', 'versionId' => $version, 'ownerKey' => '2b3d7361-1b3d-488e-a781-52d5c42a5c67', 'slug' => 'ne-speshi-sudit',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => explode("\n\n", trim($raw['description']))[1]], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['school', 'sunday-school', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => ['assetId' => 'builtin-judge-1', 'versionId' => 'builtin-judge-1-v1'],
        'details' => ['ru' => ['goals' => ['Отличат наблюдаемый факт, предположение о мотиве и ярлык.', 'Объяснят образы сучка и бревна без буквальной инсценировки.', 'Составят вопрос и фразу о конкретном поступке без унижения личности.'], 'materials' => ['Карточки для пары и рабочий лист участника.', 'Ручки, общий экран и Библия: Мф. 7:1–5.'], 'devices' => 'Можно провести очно или дистанционно. Устройства участников по желанию; записи первого и повторного решения можно оставить на своём листе.', 'conditions' => '13–15 лет. 45 минут. Группа 6–20 участников. Ситуации вымышленные; личные примеры необязательны.'], 'de' => ['goals' => $de['goals'], 'materials' => ['Karten pro Paar und ein Arbeitsblatt pro Person.', 'Stifte, gemeinsamer Bildschirm und Bibel: Matthäus 7,1–5.'], 'devices' => 'Vor Ort oder online. Eigene Geräte optional; erste und erneute Entscheidung können auf dem eigenen Blatt bleiben.', 'conditions' => '13–15 Jahre. 45 Minuten. 6–20 Teilnehmende. Erfundene Situationen; persönliche Beispiele freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => implode("\n\n", $dePlan)]], 'files' => $files]],
];
