<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/spasibo-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/spasibo-de.php';
$version = '8d20a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 300]);
$pictures = [1, 5, 2, 3, 6, 4, 7, 8, 9, 10, 11, 12, 13, 14];
$sourceLines = static function (int $number) use ($raw): array {
    $lines = $raw['slides'][$number - 1]['slide'];
    if ($number === 1) {
        return ['title' => $raw['title'], 'text' => $lines[4], 'source' => $lines[5], 'modes' => []];
    }
    $title = array_shift($lines);
    $content = ['title' => $title, 'text' => implode("\n", $lines), 'modes' => []];
    if (in_array($number, [2, 3, 4, 11, 12], true)) {
        $content['source'] = array_pop($lines);
        $content['text'] = implode("\n", $lines);
    }

    return $content;
};
$image = static fn (int $number): array => ['image' => ['assetId' => 'builtin-thanks-'.$pictures[$number - 1], 'versionId' => 'builtin-thanks-'.$pictures[$number - 1].'-v1']];
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'thanks-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $slide = $screen['slides'][0];
    $ru = $sourceLines($slide);
    $german = $de['slides'][$slide - 1] + ['modes' => []];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($slide);
    $blocks[] = $picture;
    if (count($screen['slides']) === 2) {
        $next = $screen['slides'][1];
        $reveal = $make($id.'-next-picture', 'presentation', $sourceLines($next) + ['label' => $n === 2 ? 'Исцеление в пути' : 'События по порядку', 'hideLabel' => 'Вернуться к первому кадру'], $de['slides'][$next - 1] + ['modes' => [], 'label' => $n === 2 ? 'Heilung unterwegs' : 'Ereignisse ordnen', 'hideLabel' => 'Zum ersten Bild zurück'], array_replace($base, ['kind' => 'reveal']));
        $reveal['media'] = $image($next);
        $blocks[] = $reveal;
    }
    if ($n === 3) {
        $items = static fn ($texts) => array_map(static fn ($text, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-sequence', 'sequence', ['question' => 'Четыре события по порядку.', 'items' => $items(['Один вернулся', 'Попросили о милости', 'Прославил Бога и благодарил Иисуса', 'Пошли и очистились']), 'emptyText' => '…'], ['question' => 'Vier Ereignisse in der richtigen Reihenfolge.', 'items' => $items(['Einer kehrte zurück', 'Baten um Erbarmen', 'Lobte Gott und dankte Jesus', 'Gingen und wurden rein']), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-4', 'event-1', 'event-3']]);
    } elseif ($n === 4) {
        $blocks[] = $make($id.'-cases', 'prompt', ['text' => "Бабушка приготовила обед. Ты сел за стол.\nДруг дал тебе карандаш перед уроком.\nОдноклассница выслушала, как ты читаешь текст.\nБиблиотекарь помог найти нужную книгу.\nПовар приготовил обед для класса.\nРебята убрали стол после общего дела."], ['text' => "Großmutter kochte das Essen. Du setzt dich.\nEin Freund gab dir vor dem Unterricht einen Stift.\nEine Mitschülerin hörte beim Lesen zu.\nDer Bibliothekar fand das Buch.\nDer Koch machte Essen für die Klasse.\nDie anderen räumten den Tisch auf."], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 5) {
        $blocks[] = $make($id.'-habits', 'prompt', ['text' => "Спешу к следующему делу\nПривык к ежедневной заботе\nСкажу потом и забываю\nНе знаю как сказать"], ['text' => "Ich eile zur nächsten Aufgabe\nIch bin tägliche Fürsorge gewohnt\nIch sage es später und vergesse es\nIch weiß nicht, wie ich es sagen soll"], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-reminder', 'Какое напоминание поможет перейти от «потом» к действию?', 'Welche Erinnerung hilft, vom „später“ zur Handlung zu kommen?');
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-phrases', 'prompt', ['text' => "Спасибо за всё.\nНу ты мне помог.\nСпасибо, что нашла книгу.\nСпасибо, что выслушала моё чтение."], ['text' => "Danke für alles.\nNa, du hast mir geholfen.\nDanke, dass du das Buch gefunden hast.\nDanke, dass du beim Lesen zugehört hast."], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-phrase', 'По каким словам помощник поймёт, что именно мы заметили?', 'An welchen Worten erkennt der Helfer, was wir bemerkt haben?');
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-dialogue', 'prompt', ['text' => 'Друг объяснил правило игры. Скажи спасибо за его помощь. Подруга выслушала чтение. Поблагодари её за время. Один благодарит, другой принимает спасибо. Затем меняйтесь ролями. Помощник повторяет, какое его действие заметили.'], ['text' => 'Ein Freund erklärte die Spielregel: danke für die Hilfe. Eine Freundin hörte beim Lesen zu: danke für ihre Zeit. Einer dankt, der andere nimmt den Dank an. Danach Rollen wechseln. Der Helfer wiederholt die bemerkte Handlung.'], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 8) {
        $blocks[] = $make($id.'-late-cases', 'prompt', ['text' => 'Вчера библиотекарь нашёл книгу. Ты вспомнил сегодня. Что скажешь? Бабушка приготовила обед, а ты ушёл молча. Как поблагодаришь позже?'], ['text' => 'Gestern fand der Bibliothekar ein Buch; heute erinnerst du dich. Was sagst du? Großmutter kochte, du gingst schweigend. Wie dankst du später?'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-late-thanks', 'Наш вариант благодарности', 'Unser Dankessatz');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-card', 'prompt', ['text' => 'Кому: … Спасибо, что ты… От: … Кто передаст открытку: … Когда: … Проверяющий читает от его имени и повторяет, за что благодарят. Уточните текст и договоритесь о передаче.'], ['text' => 'An: … Danke, dass du … Von: … Wer übergibt die Karte? Wann? Die prüfende Person liest aus Empfängersicht und wiederholt den Grund. Text präzisieren und Übergabe vereinbaren.'], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-prayer', 'prompt', ['text' => 'Господи, благодарю Тебя за… Помоги мне… Можно написать короткую молитву или спокойно послушать.'], ['text' => 'Herr, ich danke dir für … Hilf mir … Du kannst ein kurzes Gebet schreiben oder ruhig zuhören.'], ['kind' => 'reflection', 'target' => 'class']);
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-private-sheet', 'prompt', ['text' => 'Можно выбрать реальный случай или продолжить историю Миши. Лист остаётся у тебя. Мне не нужно покупать подарок. Можно сказать спасибо за конкретную помощь.'], ['text' => 'Du kannst einen echten Fall oder Mischas Geschichte wählen. Das Blatt bleibt bei dir. Kein Geschenk erforderlich: für konkrete Hilfe danken.'], ['kind' => 'reflection', 'target' => 'class']);
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-samaritan', 'Кто вернулся к Иисусу?', 'Wer kehrte zu Jesus zurück?');
        $blocks[] = $free($id.'-step', 'Какой шаг сделает Миша?', 'Welchen Schritt wird Mischa tun?');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Полученное добро можно заметить и ответить благодарностью.', 'text' => 'Самарянин вернулся к Иисусу, прославляя Бога.', 'source' => 'Лк. 17:15', 'eyebrow' => 'Урок завершён', 'modes' => []], ['title' => 'Erhaltenes Gutes bemerken und mit Dank antworten.', 'text' => 'Der Samariter kehrte zu Jesus zurück und lobte Gott.', 'source' => 'Lukas 17,15', 'eyebrow' => 'Die Stunde ist beendet', 'modes' => []], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'berry'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'thanks-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n10–12 Jahre · 45 Minuten · 6–20 Teilnehmende.\n".implode("\n", $de['goals'])."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['handout'];

return [
    'sourceRevision' => 'thanks-ru-de-2026-10-02-v1', 'materialId' => 'c020a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e020a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'spasibo-pochemu-zabyvaem-dobro',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => explode("\n\n", trim($raw['description']))[1]], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['8-10', '11-14'], 'topic' => ['bible', 'mercy'], 'audience' => ['school', 'sunday-school', 'family', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Восстановить события и назвать, что сделал вернувшийся самарянин.', 'Замечать действие помощника в обычной ситуации.', 'Сказать понятное спасибо и спокойно принять благодарность.', 'Назвать адресата, способ и время своего следующего шага.'], 'materials' => ['Пять страниц иллюстрированной раздатки: десять участников, события, ситуации, фразы, открытки и личный план.', 'Карандаши, бумага, Библия или полный текст приложения и экран.', 'Картинки и карточки можно распечатать для занятия без экрана.'], 'devices' => 'Очное чтение, сценка, карточки, пары и команды. Устройства детей не обязательны. Личный лист остаётся у ребёнка.', 'conditions' => '10–12 лет. 45 минут. 6–20 участников. Пары и команды по 3–4 человека. Личные истории и молитва по желанию. Лист остаётся у ребёнка; можно продолжить историю Миши.'], 'de' => ['goals' => $de['goals'], 'materials' => ['Fünf illustrierte Seiten mit Spielkarten, Situationen, Sätzen, Dankeskarten und persönlichem Plan.', 'Stifte, Papier, Bibel oder vollständiger Anhang und Bildschirm.', 'Bilder und Karten können für Unterricht ohne Bildschirm ausgedruckt werden.'], 'devices' => 'Lesen, Spiel, Karten, Paare und Teams im Präsenzunterricht. Geräte der Kinder sind nicht erforderlich. Das persönliche Blatt bleibt beim Kind.', 'conditions' => '10–12 Jahre. 45 Minuten. 6–20 Teilnehmende, Paare und Teams von 3–4. Persönliche Geschichten und Gebet freiwillig; erfundene Figuren sind möglich.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
