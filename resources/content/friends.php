<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/friends-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/friends-de.php';
$version = 'd040a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 300]);
$pictures = [1, 5, 2, 6, 3, 7, 8, 9, 4, 10, 11, 12, 13, 14];
$sourceLines = static function (int $number) use ($raw): array {
    $lines = $raw['slides'][$number - 1]['slide'];
    if ($number === 1) {
        return ['title' => 'Четверо друзей', 'text' => $lines[2], 'source' => $lines[3], 'modes' => []];
    }
    $title = array_shift($lines);
    $source = null;
    foreach ($lines as $i => $line) {
        if (str_starts_with($line, 'Мк. ') || $line === 'Пример короткой молитвы') {
            $source = $line;
            unset($lines[$i]);
        }
    }

    return array_filter(['title' => $title, 'text' => implode("\n", $lines), 'source' => $source, 'modes' => []], static fn ($v) => $v !== null);
};
$image = static fn (int $number): array => ['image' => ['assetId' => 'builtin-friends-'.$pictures[$number - 1], 'versionId' => 'builtin-friends-'.$pictures[$number - 1].'-v1']];
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'friends-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $slide = $screen['slides'][0];
    $ru = $sourceLines($slide);
    $german = $de['slides'][$slide - 1] + ['modes' => []];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($slide);
    $blocks[] = $picture;
    foreach (array_slice($screen['slides'], 1) as $next) {
        $content = $sourceLines($next);
        $reveal = $make($id.'-frame-'.$next, 'presentation', $content + ['label' => $content['title'], 'hideLabel' => 'Скрыть кадр: '.$content['title']], $de['slides'][$next - 1] + ['modes' => [], 'label' => $de['slides'][$next - 1]['title'], 'hideLabel' => 'Bild ausblenden: '.$de['slides'][$next - 1]['title']], array_replace($base, ['kind' => 'reveal']));
        $reveal['media'] = $image($next);
        $blocks[] = $reveal;
    }
    if ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для евангельской сценки', 'roles' => $roles(['Рассказчик', 'Четверо друзей', 'Человек на постели', 'Иисус', 'Книжники', 'Толпа'])], ['text' => 'Rollen für das Evangeliumsspiel', 'roles' => $roles(['Erzähler', 'Vier Freunde', 'Mann auf der Liege', 'Jesus', 'Schriftgelehrte', 'Menge'])], ['capacities' => ['role-1' => 1, 'role-2' => 4, 'role-3' => 1, 'role-4' => 1, 'role-5' => 8, 'role-6' => 20]]);
    } elseif ($n === 4) {
        $blocks[] = $free($id.'-answer', 'Что Иисус сказал прежде, чем человек встал? Что исцеление показывает о Его власти?', 'Was sagte Jesus, bevor der Mann aufstand? Was zeigt die Heilung über seine Vollmacht?');
    } elseif ($n === 5) {
        $items = static fn ($texts) => array_map(static fn ($text, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-sequence', 'sequence', ['question' => 'События по порядку', 'items' => $items(['Иисус объявляет человеку прощение грехов.', 'Четверо приносят человека к переполненному дому.', 'Человек встаёт и берёт постель. Люди прославляют Бога.', 'Друзья спускают постель через отверстие в крыше.']), 'emptyText' => '…'], ['question' => 'Ereignisse ordnen', 'items' => $items(['Jesus erklärt die Vergebung der Sünden.', 'Vier bringen den Mann zum vollen Haus.', 'Der Mann steht auf und nimmt die Liege. Menschen loben Gott.', 'Freunde lassen die Liege durch das Dach hinunter.']), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-4', 'event-1', 'event-3']]);
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-team-plan', 'prompt', ['text' => "Что предложим Саше сначала?\nКто что сделает?\nКак дадим ему попробовать самому?\nЧто спросим, чтобы проверить результат?"], ['text' => "Was bieten wir Sascha zuerst?\nWer tut was?\nWie probiert er selbst?\nWas fragen wir zur Prüfung?"], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-invitation', 'prompt', ['text' => '«Хочу сам передвигать свою фишку». Как сохранить для друга возможность делать свой ход самому? Затем поменяйтесь ролями.'], ['text' => '„Ich möchte meine Figur selbst bewegen.“ Wie bleibt dem Freund die Möglichkeit, seinen Zug selbst zu machen? Danach Rollenwechsel.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-phrase', 'Как начать разговор?', 'Wie beginnen wir das Gespräch?');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-adjust', 'Что изменишь, если человек скажет: «Это мне не подходит»?', 'Was änderst du, wenn die Person sagt: „Das passt für mich nicht“?');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-dialogue', 'prompt', ['text' => '«Хочешь, я помогу? Что именно нужно?» — «Покажи начало, дальше попробую сам» либо «Спасибо, сейчас хочу попробовать сам». После каждого разговора слушатель повторяет, какую помощь услышал.'], ['text' => '„Soll ich dir helfen? Was genau brauchst du?“ – „Zeig mir den Anfang, danach versuche ich es selbst“ oder „Danke, ich möchte es gerade selbst versuchen“. Nach jedem Gespräch wiederholt der Zuhörer die gehörte Hilfe.'], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-prayer', 'prompt', ['text' => 'Можно молча слушать. Какое дело может стать продолжением нашей молитвы?'], ['text' => 'Still zuhören ist möglich. Welche Handlung kann unser Gebet fortsetzen?'], ['kind' => 'reflection', 'target' => 'class']);
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-private-sheet', 'prompt', ['text' => 'Можно выбрать реальный случай или продолжить историю Саши. Лист остаётся у тебя.'], ['text' => 'Echter Fall oder Saschas Geschichte möglich. Das Blatt bleibt bei dir.'], ['kind' => 'reflection', 'target' => 'class']);
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-christ', 'Что совершил Иисус в рассказе?', 'Was tat Jesus im Bericht?');
        $blocks[] = $free($id.'-help', 'С какой фразы ты начнёшь помощь другу?', 'Mit welchem Satz beginnst du, einem Freund zu helfen?');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Друзья принесли человека к Иисусу.', 'text' => 'Христос, видя веру их, простил его грехи и исцелил. Мы тоже можем молиться о друзьях и помогать им конкретными делами.', 'source' => 'Мк. 2:1–12', 'eyebrow' => 'Урок завершён', 'modes' => []], ['title' => 'Die Freunde brachten den Mann zu Jesus.', 'text' => 'Christus sah ihren Glauben, vergab seine Sünden und heilte ihn. Auch wir können für Freunde beten und ihnen mit konkreten Taten helfen.', 'source' => 'Markus 2,1–12', 'eyebrow' => 'Die Stunde ist beendet', 'modes' => []], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'cobalt'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'friends-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n9–11 Jahre · 45 Minuten · 8–20 Teilnehmende, Paare und Viererteams.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['handout'];

return [
    'sourceRevision' => 'friends-ru-de-2026-10-02-v1', 'materialId' => 'c040a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e040a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'chetvero-druzey',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['8-10', '11-14'], 'topic' => ['bible', 'mercy'], 'audience' => ['school', 'sunday-school', 'family', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Познакомиться с рассказом о прощении и исцелении расслабленного, увидеть веру и заботу четырёх друзей и потренироваться помогать сообща, учитывая ответ человека.'], 'materials' => ['Шесть страниц иллюстрированной раздатки: роли, события, четыре сведения и общий план, ситуации, разговоры и личный шаг.', 'Библия или полный текст Мк. 2:1–12, экран, карандаши, листы и простая настольная игра.', 'Сложенная ткань как постель; крышу и спуск показывает иллюстрация.'], 'devices' => 'Ответы дети обсуждают устно и записывают на бумаге. Устройства детей не обязательны.', 'conditions' => '9–11 лет. 45 минут. 8–20 участников; пары и команды по четыре. Личные примеры, чтение личного листа и молитва добровольны. Лист остаётся у ребёнка.'], 'de' => ['goals' => ['Den Bericht über Vergebung und Heilung des Gelähmten kennenlernen, Glauben und Fürsorge der vier Freunde sehen und gemeinsam helfen üben, wobei die Antwort des Menschen berücksichtigt wird.'], 'materials' => ['Sechs illustrierte Seiten: Rollen, Ereignisse, vier Informationen und Teamplan, Situationen, Gespräche und persönlicher Schritt.', 'Bibel oder vollständiger Markus 2,1–12, Bildschirm, Stifte, Papier und einfaches Brettspiel.', 'Gefaltetes Tuch als Liege; das Bild zeigt Dach und Hinablassen.'], 'devices' => 'Antworten mündlich und auf Papier. Geräte der Kinder sind nicht erforderlich.', 'conditions' => '9–11 Jahre. 45 Minuten. 8–20 Teilnehmende, Paare und Viererteams. Persönliche Beispiele, Vorlesen und Gebet freiwillig. Das Blatt bleibt beim Kind.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
