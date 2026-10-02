<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/strakh-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/strakh-de.php';
$version = '34d23971-a9c4-4992-8182-f073e0e1b104';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 300]);
$pictures = [5, 1, 2, 6, 3, 7, 8, 9, 4, 10, 11, 12, 13, 14];
$sourceLines = static function (int $number) use ($raw): array {
    $lines = $raw['slides'][$number - 1]['slide'];
    if ($number === 1) {
        return ['title' => $raw['title'], 'text' => $lines[2], 'source' => $lines[3], 'modes' => []];
    }
    $title = array_shift($lines);
    $content = ['title' => $title, 'text' => implode("\n", $lines), 'modes' => []];
    if (in_array($number, [2, 3, 5, 11, 12, 13], true)) {
        $content['source'] = array_pop($lines);
        $content['text'] = implode("\n", $lines);
    }

    return $content;
};
$image = static fn (int $number): array => ['image' => ['assetId' => 'builtin-fear-'.$pictures[$number - 1], 'versionId' => 'builtin-fear-'.$pictures[$number - 1].'-v1']];
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'fear-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $slide = $screen['slides'][0];
    $ru = $sourceLines($slide);
    $german = $de['slides'][$slide - 1] + ['modes' => []];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($slide);
    $blocks[] = $picture;
    if (count($screen['slides']) === 2) {
        $next = $screen['slides'][1];
        $reveal = $make($id.'-next-picture', 'presentation', $sourceLines($next) + ['label' => $n === 2 ? 'Обращение учеников' : 'Разбор после ответов', 'hideLabel' => 'Вернуться к первому кадру'], $de['slides'][$next - 1] + ['modes' => [], 'label' => $n === 2 ? 'Die Bitte der Jünger' : 'Besprechung nach den Antworten', 'hideLabel' => 'Zum ersten Bild zurück'], array_replace($base, ['kind' => 'reveal']));
        $reveal['media'] = $image($next);
        $blocks[] = $reveal;
    }
    if ($n === 3) {
        $items = static fn ($texts) => array_map(static fn ($text, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-sequence', 'sequence', ['question' => 'Что было между бурей и тишиной?', 'items' => $items(['Тишина', 'Буря', 'Переправа', 'Обращение к Иисусу']), 'emptyText' => '…'], ['question' => 'Was geschah zwischen Sturm und Stille?', 'items' => $items(['Stille', 'Sturm', 'Überfahrt', 'Bitte an Jesus']), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-3', 'event-2', 'event-4', 'event-1']]);
    } elseif ($n === 4) {
        $blocks[] = $free($id.'-answer', 'Что рассказ открывает об Иисусе?', 'Was zeigt die Geschichte über Jesus?');
    } elseif ($n === 5) {
        $blocks[] = $make($id.'-cards', 'prompt', ['text' => "Мне страшно\nЯ волнуюсь\nСкажу учителю\nПотренируюсь с другом"], ['text' => "Ich habe Angst\nIch bin aufgeregt\nIch sage es der Lehrkraft\nIch übe mit einem Freund"], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-situations', 'prompt', ['text' => "Саша волнуется перед чтением вслух.\nЛена впервые приходит в новую группу.\nПетя боится ошибиться в ответе.\nСтарший ученик угрожает ударить Диму.\nНа экскурсии Маша потеряла из виду учителя.\nВаня говорит: «Мне страшно идти в класс»."], ['text' => "Sascha ist vor dem Vorlesen aufgeregt.\nLena kommt erstmals in eine neue Gruppe.\nPetja hat Angst vor einem Fehler in seiner Antwort.\nEin älterer Schüler droht, Dima zu schlagen.\nAuf einem Ausflug hat Mascha die Lehrkraft aus den Augen verloren.\nWanja sagt: „Ich habe Angst, in die Klasse zu gehen“."], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-dialogue', 'prompt', ['text' => "Ответ перед классом: Я волнуюсь перед чтением. Мне нужна короткая репетиция.\nНовая группа: Я никого не знаю. Мне нужна помощь, чтобы познакомиться с одним участником.\nВзрослый повторяет просьбу своими словами. Если понял иначе, ребёнок уточняет."], ['text' => "Antwort vor der Klasse: Ich bin vor dem Lesen aufgeregt und brauche eine kurze Probe.\nNeue Gruppe: Ich kenne niemanden und brauche Hilfe, um eine Person kennenzulernen.\nDer Erwachsene wiederholt die Bitte. Bei einem Missverständnis präzisiert das Kind."], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-request', 'По каким словам взрослый поймёт, что именно нужно ребёнку?', 'An welchen Worten versteht der Erwachsene, was das Kind braucht?');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-support', 'Что можно предложить другу без давления?', 'Was kann man einem Freund ohne Druck anbieten?');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-prayer', 'prompt', ['text' => "Господи, мне…\nПомоги мне…\nМожно написать короткую молитву для Саши или спокойно послушать."], ['text' => "Herr, ich …\nHilf mir …\nDu kannst ein kurzes Gebet für Sascha schreiben oder ruhig zuhören."], ['kind' => 'reflection', 'target' => 'class']);
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-workshop', 'prompt', ['text' => 'Лена завтра впервые читает стихотворение перед группой и волнуется. Составьте понятный план. Роли: предложить помощь, записать, проверить от имени Лены. Проверяющий повторяет первый шаг своими словами. Команда уточняет одну непонятную деталь.'], ['text' => 'Lena liest morgen erstmals ein Gedicht vor der Gruppe und ist aufgeregt. Erstellt einen verständlichen Plan. Rollen: Hilfe vorschlagen, aufschreiben, aus Lenas Sicht prüfen. Die prüfende Person wiederholt den ersten Schritt; das Team klärt eine unverständliche Einzelheit.'], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-private-sheet', 'prompt', ['text' => 'Можно выбрать личную ситуацию или историю Лены. Этот лист остаётся у тебя. Можно показать лист выбранному взрослому и вместе уточнить следующий шаг.'], ['text' => 'Du kannst eine persönliche Situation oder Lenas Geschichte wählen. Das Blatt bleibt bei dir. Du kannst es einem gewählten Erwachsenen zeigen und den nächsten Schritt gemeinsam klären.'], ['kind' => 'reflection', 'target' => 'class']);
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-disciples', 'К кому обратились ученики?', 'An wen wandten sich die Jünger?');
        $blocks[] = $free($id.'-step', 'Какой шаг поможет Лене?', 'Welcher Schritt hilft Lena?');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Один следующий шаг', 'text' => 'Когда страшно, можно говорить с Богом и просить людей о помощи. Первый полезный шаг можно сделать, даже если страх ещё остаётся.', 'eyebrow' => 'Урок завершён', 'modes' => []], ['title' => 'Ein nächster Schritt', 'text' => 'Wenn wir Angst haben, können wir mit Gott sprechen und Menschen um Hilfe bitten. Einen hilfreichen ersten Schritt kann man tun, auch wenn die Angst bleibt.', 'eyebrow' => 'Die Stunde ist beendet', 'modes' => []], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'ocean'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'fear-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n10–12 Jahre · 45 Minuten · 6–20 Teilnehmende.\n".implode("\n", $de['goals'])."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['handout'];

return [
    'sourceRevision' => 'fear-ru-de-2026-10-02-v1', 'materialId' => '8c31a6b5-7aa3-4dfe-b7fc-14ac095447cc', 'versionId' => $version, 'ownerKey' => '0a3a33db-8e46-49e3-8a1c-044b08b8fb87', 'slug' => 'kogda-mne-strashno',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => explode("\n\n", trim($raw['description']))[1]], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['8-10', '11-14'], 'topic' => ['bible', 'mercy'], 'audience' => ['school', 'sunday-school', 'family', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Восстановить четыре события рассказа и объяснить, что удивило учеников.', 'Различить чувство страха и решение о следующем поступке.', 'Произнести понятную просьбу о помощи и предложить поддержку другу.', 'Назвать доступного взрослого и посильный первый шаг.'], 'materials' => ['Пять страниц иллюстрированной раздатки с 19 карточками.', 'Два стула, карандаши, Библия или полный текст приложения и экран.', 'Картинки и карточки можно распечатать для занятия без экрана.'], 'devices' => 'Очное чтение, сценка, карточки, пары и команды. Устройства детей не обязательны. Личный лист остаётся у ребёнка.', 'conditions' => '10–12 лет. 45 минут. 6–20 участников. Пары и команды по 3–4 человека. Личные истории и молитва по желанию; можно работать с вымышленным героем.'], 'de' => ['goals' => $de['goals'], 'materials' => ['Fünf illustrierte Seiten Kopiervorlagen mit 19 Karten.', 'Zwei Stühle, Stifte, Bibel oder vollständiger Anhang und Bildschirm.', 'Bilder und Karten können für Unterricht ohne Bildschirm ausgedruckt werden.'], 'devices' => 'Lesen, Spiel, Karten, Paare und Teams im Präsenzunterricht. Geräte der Kinder sind nicht erforderlich. Das persönliche Blatt bleibt beim Kind.', 'conditions' => '10–12 Jahre. 45 Minuten. 6–20 Teilnehmende, Paare und Teams von 3–4. Persönliche Geschichten und Gebet freiwillig; erfundene Figuren sind möglich.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
