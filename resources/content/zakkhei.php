<?php

declare(strict_types=1);

// Reviewed source files remain immutable; the teaching kit is content, not instructions to the importer.
$raw = json_decode(file_get_contents(__DIR__.'/zakkhei-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/zakkhei-de.php';
$version = 'd5f98648-10af-4cd8-b8d0-78282020f487';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn (string $id, string $type, array $ru, array $de, array $config = [], ?array $solution = null): array => array_filter([
    'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $de], 'config' => $config, 'solution' => $solution,
], static fn ($value) => $value !== null);
$imageNumbers = [1 => 1, 3 => 2, 5 => 3, 10 => 4];
$alt = [
    1 => ['Закхей на дереве и Иисус', 'Zachäus auf einem Baum und Jesus'],
    2 => ['Встреча у дерева', 'Die Begegnung am Baum'],
    3 => ['Воображаемое исполнение обещания Закхея о возмещении вреда', 'Eine vorgestellte Erfüllung des Versprechens von Zachäus, Unrecht zu ersetzen'],
    4 => ['Разговор об испорченной книге', 'Ein Gespräch über ein beschädigtes Buch'],
];
$ruText = [1 => "Можно ли исправить\nошибку?", 2 => "Случайно пролил воду.\nСписал и сказал, что решил сам.", 3 => 'Закхей хотел увидеть Иисуса.', 4 => "Иисус зовёт Закхея по имени\nи хочет быть у него дома.", 5 => "Половину имущества бедным.\nВозместить обиду вчетверо.", 6 => "«Прости, но ты сам виноват».\n«Я обманул. Прости. Я скажу правду».", 7 => $raw['screens'][6]['fields']['Задание'], 8 => "Ты выдал чужой рисунок за свой.\nУчитель похвалил тебя.", 9 => "Друг пока не хочет давать\nтебе свою вещь снова.", 10 => "Ты порвал чужую книгу.\nЧто скажешь? Что предложишь?", 11 => "На этой неделе я могу…\nпризнать • вернуть • исправить", 12 => "«Сын Человеческий пришёл\nвзыскать и спасти погибшее»"];
$references = [3 => '19:1–4', 4 => '19:5–7', 5 => '19:8', 12 => '19:10'];
$free = static fn (string $id, string $ru, string $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 220]);
$stages = [];
foreach ($raw['screens'] as $index => $screen) {
    $number = $screen['number'];
    $translated = $de['screens'][$index];
    $id = 'zakkhei-step-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    $ru = ['title' => $screen['title'], 'text' => $ruText[$number], 'eyebrow' => $number === 1 ? 'Интерактивный урок · 9–11 лет · 45 минут' : 'Закхей · '.$number.' / 12', 'modes' => []];
    $german = ['title' => $translated['title'], 'text' => $translated['text'], 'eyebrow' => $number === 1 ? 'Interaktiver Unterricht · 9–11 Jahre · 45 Minuten' : 'Zachäus · '.$number.' / 12', 'modes' => []];
    if (isset($de['slideQuestions'][$number])) {
        $ru['subtitle'] = $screen['fields']['Вопрос'];
        $german['subtitle'] = $de['slideQuestions'][$number];
    }
    if (isset($references[$number])) {
        $ru['source'] = 'Лк. '.$references[$number];
        $german['source'] = 'Lukas '.str_replace(':', ',', $references[$number]).($number === 12 ? ' · Übersetzung des bereitgestellten russischen Textes' : '');
    }
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $number === 1 ? 'cover' : 'story'])];
    if (isset($imageNumbers[$number])) {
        $n = $imageNumbers[$number];
        $image = $make($id.'-image', 'image', ['alt' => $alt[$n][0], 'caption' => $n === 3 ? 'Воображаемое исполнение обещания; Лк. 19:8 передаёт само обещание.' : ''], ['alt' => $alt[$n][1], 'caption' => $n === 3 ? 'Eine vorgestellte Erfüllung; Lukas 19,8 berichtet das Versprechen selbst.' : ''], ['fit' => 'contain']);
        $image['media'] = ['image' => ['assetId' => 'builtin-zakkhei-'.$n, 'versionId' => 'builtin-zakkhei-'.$n.'-v1']];
        $blocks[] = $image;
    }
    if ($number === 1) {
        $blocks[] = $make($id.'-signals', 'signals', ['text' => $screen['fields']['Задание']], ['text' => $translated['task']]);
    } elseif ($number === 2) {
        $items = static fn (string $key, array $texts) => array_map(static fn ($text, $i) => ['itemId' => $key.($i + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-answer', 'matching', ['question' => $screen['fields']['Задание'], 'left' => $items('case-', ['Случайно пролил воду.', 'Списал и сказал, что решил сам.']), 'right' => $items('kind-', ['Случайная ошибка', 'Сознательный обман'])], ['question' => $translated['task'], 'left' => $items('case-', ['Versehentlich Wasser verschüttet.', 'Abgeschrieben und behauptet, selbst gelöst zu haben.']), 'right' => $items('kind-', ['Versehentliches Missgeschick', 'Bewusste Täuschung'])], ['allowRepeat' => true], ['pairs' => [['leftId' => 'case-1', 'rightId' => 'kind-1'], ['leftId' => 'case-2', 'rightId' => 'kind-2']]]);
    } elseif (in_array($number, [4, 9], true)) {
        $ruOptions = explode(' / ', $screen['fields']['Варианты выбора']);
        $deOptions = $number === 4 ? ['Jesus', 'Zachäus', 'Die Menge'] : ['Vertrauen verlangen', 'Zeit geben und Versprechen halten', 'Wieder ohne Erlaubnis nehmen'];
        $options = static fn ($texts) => array_map(static fn ($text, $i) => ['optionId' => 'option-'.($i + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-answer', 'single-choice', ['question' => $screen['fields']['Задание'], 'options' => $options($ruOptions)], ['question' => $translated['task'], 'options' => $options($deOptions)], ['allowRepeat' => true], ['optionId' => 'option-'.($number === 4 ? 1 : 2)]);
    } elseif ($number === 7) {
        $texts = ['Признать поступок.', 'Попросить прощения.', 'Исправить вред.', 'Поступить иначе.'];
        $germanTexts = ['Die eigene Tat zugeben.', 'Um Vergebung bitten.', 'Den Schaden wiedergutmachen.', 'Anders handeln.'];
        $items = static fn ($texts) => array_map(static fn ($text, $i) => ['itemId' => 'action-'.($i + 1), 'text' => $text], $texts, array_keys($texts));
        // The supplied scenario explicitly accepts different reasoned orders. No grading solution is stored.
        $blocks[] = $make($id.'-answer', 'sequence', ['question' => $screen['fields']['Задание'], 'items' => $items($texts)], ['question' => $translated['task'], 'items' => $items($germanTexts)], ['allowRepeat' => true]);
        $blocks[] = $make($id.'-memo', 'presentation', ['text' => implode("\n", $texts), 'label' => 'Показать памятку после обсуждения', 'hideLabel' => 'Скрыть памятку', 'modes' => []], ['text' => implode("\n", $germanTexts), 'label' => 'Merkhilfe nach dem Gespräch zeigen', 'hideLabel' => 'Merkhilfe ausblenden', 'modes' => []], array_replace($base, ['kind' => 'reveal']));
    } elseif ($number === 8) {
        $blocks[] = $free($id.'-teacher', 'Что сказать учителю?', 'Was sagst du der Lehrkraft?');
        $blocks[] = $free($id.'-author', 'Что сказать Саше?', 'Was sagst du Sascha?');
    } elseif ($number === 12) {
        $blocks[] = $free($id.'-answer', $screen['fields']['Задание'], $translated['task']);
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => $screen['title'], 'text' => $screen['fields']['Вывод'], 'eyebrow' => 'Урок завершён', 'quote' => $ruText[$number], 'source' => 'Лк. 19:10', 'modes' => []], ['title' => $translated['title'], 'text' => 'Jesus gibt Hoffnung und wir lernen, unsere Taten zu verantworten.', 'eyebrow' => 'Die Stunde ist beendet', 'quote' => $translated['text'], 'source' => 'Lukas 19,10 · Übersetzung des bereitgestellten russischen Textes', 'modes' => []], array_replace($base, ['kind' => 'closing']));
    } else {
        // Dialogue and the personal worksheet stay oral/on paper as specified, without compulsory disclosure.
        $blocks[] = $make($id.'-instruction', 'prompt', ['text' => $screen['fields']['Задание'].($number === 11 ? "\nКарточку оставьте себе. Читать личные ответы вслух мы не будем." : '')], ['text' => $translated['task'].($number === 11 ? "\nBehalte die Karte. Wir lesen persönliche Antworten nicht laut vor." : '')], ['kind' => $number === 11 ? 'reflection' : 'discussion', 'target' => $number === 6 || $number === 10 ? 'pair' : 'class']);
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $translated['title'], 'notes' => $translated['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'terracotta'], 'blocks' => $blocks];
}
$dePlan = [$de['title'], '9–11 Jahre · 45 Minuten · Lukas 19,1–10. Vor Ort oder online, 6–20 Teilnehmende.', 'Ziel: Am Beispiel von Zachäus Umkehr erkennen, die eigene Tat zugeben und Schaden wiedergutmachen.', 'Hauptgedanke: Jesus sucht und nimmt den Menschen an. Die Begegnung bewegt Zachäus dazu, sein Leben zu ändern und Unrecht zu ersetzen.', implode("\n", $de['goals'])];
$elapsed = 0;
foreach ($raw['screens'] as $i => $screen) {
    $dePlan[] = $elapsed.'–'.($elapsed + $screen['durationMinutes']).' · '.($i + 1).' '.$de['screens'][$i]['title']."\n".$de['screens'][$i]['task'];
    $elapsed += $screen['durationMinutes'];
}
$dePlan[] = 'Verständnisprüfung: Das Kind schlägt eine machbare Wiedergutmachung vor und erklärt, warum eine Bitte um Vergebung kein Recht auf sofortiges Vertrauen gibt.';
foreach ($de['screens'] as $i => $screen) {
    $dePlan[] = ($i + 1).' '.$screen['title']."\n".$screen['notes'];
}
$dePlan[] = $de['preparation'];
$dePlan[] = $de['handout'];
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'zakkhei-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['number'].' '.$s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];

return [
    'sourceRevision' => 'zakkhei-ru-de-2026-10-02-v1', 'materialId' => 'c2fd0d14-6f27-4212-bb14-80282561223c', 'versionId' => $version, 'ownerKey' => '28a2c224-0e18-4652-af4f-4898d560cbdb', 'slug' => 'zakkhei',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => explode("\n\n", trim($raw['description']))[1]], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['8-10', '11-14'], 'topic' => ['bible'], 'audience' => ['school', 'sunday-school', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => ['assetId' => 'builtin-zakkhei-1', 'versionId' => 'builtin-zakkhei-1-v1'],
        'details' => ['ru' => ['goals' => ['Перескажут, что изменилось после встречи Закхея с Иисусом.', 'Отличат случайную ошибку от сознательного обмана.', 'Предложат честное извинение и посильный способ исправить вред.'], 'materials' => ['Четыре карточки действий на пару и рабочий лист участника.', 'Бумага, карандаши и Библия: Лк. 19:1–10.'], 'devices' => 'Экран ведущего; устройства учеников по желанию. Карточки и устные ответы сохраняют возможность занятия без телефонов.', 'conditions' => '9–11 лет. 45 минут. Группа 6–20 участников. Личные признания не требуются.'], 'de' => ['goals' => $de['goals'], 'materials' => ['Vier Handlungskarten pro Paar und ein Arbeitsblatt pro Kind.', 'Papier, Stifte und Bibel: Lukas 19,1–10.'], 'devices' => 'Bildschirm der Leitung; Geräte für Kinder optional. Karten und mündliche Antworten ermöglichen die Teilnahme ohne Handy.', 'conditions' => '9–11 Jahre. 45 Minuten. 6–20 Teilnehmende. Persönliche Bekenntnisse sind nicht erforderlich.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => implode("\n\n", $dePlan)]], 'files' => $files]],
];
