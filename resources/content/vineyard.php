<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/vineyard-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/vineyard-de.php';
$cards = require __DIR__.'/vineyard-cards.php';
$version = 'd200a419-3038-4092-9d8b-e72a0a84bf42';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-vineyard-'.$n, 'versionId' => 'builtin-vineyard-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => implode("\n", array_slice($text, 3, 5)), 'source' => $text[8]];
    }
    $title = array_shift($text);
    array_pop($text);

    return ['title' => $title, 'text' => implode("\n", $text)];
};
$plain = static fn ($text) => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $text));
$reveal = static fn ($id, $label, $labelDe, $ru, $german, $extra = []) => $make($id, 'presentation', ['title' => $label, 'label' => $label, 'hideLabel' => 'Скрыть: '.$label, 'text' => $plain($ru)], ['title' => $labelDe, 'label' => $labelDe, 'hideLabel' => 'Ausblenden: '.$labelDe, 'text' => $german], array_replace($base, ['kind' => 'reveal'], $extra));
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'vineyard-step-'.sprintf('%02d', $n);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image([6, 2, 4, 5, 3, 6, 5, 9, 10, 12, 11, 4][$i]);
    $blocks[] = $picture;
    if ($n === 2) {
        // Two consecutive pages preserve all sixteen verses without shrinking the projection.
        $versesRu = preg_split('/(?=\*\*Мф\. 20:\d+\.\*\*)/u', $raw['bible']);
        $versesDe = preg_split('/(?=Matthäus 20,\d+\. )/u', $de['bible']);
        foreach ([[1, 8], [9, 8]] as $part => [$from, $count]) {
            $blocks[] = $reveal($id.'-reading-'.($part + 1), 'Читаем Мф. 20:'.$from.'–'.($from + $count - 1), 'Matthäus 20,'.$from.'–'.($from + $count - 1).' lesen', $versesRu[0].implode("\n", array_slice($versesRu, $from, $count)), $versesDe[0].implode("\n", array_slice($versesDe, $from, $count)));
        }
    } elseif ($n === 3) {
        $roles = static fn ($locale) => array_map(static fn ($c, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2]], array_slice($cards, 0, 7), range(0, 6));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Семь ролей: пять работников представляют группы, не общее число людей в притче.', 'roles' => $roles('ru')], ['text' => 'Sieben Rollen: Fünf Vertreter stehen für Gruppen, keine Gesamtzahl im Gleichnis.', 'roles' => $roles('de')], ['capacities' => array_fill_keys(array_map(static fn ($j) => 'role-'.$j, range(1, 7)), 1)]);
        preg_match_all('/\*\*(.*?):\*\* (.*?)(?=\n\n\*\*|\z)/su', $raw['roleplay'], $framesRu, PREG_SET_ORDER);
        preg_match_all('/(?:\A|\n\n)(.*?): (.*?)(?=\n\n|\z)/su', str_replace("\r\n", "\n", $de['roleplay']), $framesDe, PREG_SET_ORDER);
        foreach ($framesRu as $j => $frame) {
            $revealFrame = $reveal($id.'-frame-'.($j + 1), $frame[1], $framesDe[$j][1], $frame[1].': '.$frame[2], $framesDe[$j][1].': '.$framesDe[$j][2]);
            $revealFrame['media'] = $image(3);
            $blocks[] = $revealFrame;
        }
    } elseif ($n === 4) {
        $ruVerses = preg_split('/(?=\*\*Мф\. 20:\d+\.\*\*)/u', $raw['bible']);
        $deVerses = preg_split('/(?=Matthäus 20,\d+\. )/u', $de['bible']);
        $blocks[] = $reveal($id.'-reply', 'Ответ хозяина — Мф. 20:13–15', 'Antwort des Hausherrn — Matthäus 20,13–15', implode("\n", array_slice($ruVerses, 13, 3)), implode("\n", array_slice($deVerses, 13, 3)));
    } elseif ($n === 5) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'step-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 7, 4), range(0, 3));
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок событий', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Reihenfolge der Ereignisse wiederherstellen', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['step-2', 'step-4', 'step-1', 'step-3']]);
        $review = static fn ($locale) => implode(' → ', array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2], [8, 10, 7, 9]));
        $blocks[] = $reveal($id.'-check', 'Проверяем вместе', 'Gemeinsam prüfen', $review('ru'), $review('de'), ['reviewBlockId' => $id.'-order']);
        $blocks[] = $free($id.'-promise', 'Что первым обещали и что они получили:', 'Was wurde den Ersten versprochen, was bekamen sie:');
        $blocks[] = $free($id.'-expectation', 'Чего они стали ждать, увидев чужую плату:', 'Was erwarteten sie nach dem fremden Lohn:');
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Шесть случаев на пару: «Общий дар», «Нарушенное правило», «Нужно уточнить». Сначала обсудите три случая одинакового дара. Нарушения правил — граница применения притчи.'], ['text' => 'Sechs Fälle: Gemeinsame Gabe, Regel verletzt, Nachfragen nötig. Zuerst drei Fälle gleicher Gabe. Regelverletzungen zeigen die Grenze der Anwendung.'], ['kind' => 'instruction', 'target' => 'pair']);
        foreach (range(11, 16) as $j) {
            $blocks[] = $reveal($id.'-case-'.($j - 10), $cards[$j][0], $cards[$j][2], $cards[$j][1], $cards[$j][3]);
        }
        $blocks[] = $free($id.'-reason', 'Какое условие меняет наш вывод?', 'Welche Bedingung verändert unseren Schluss?');
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Первый говорит: «Я трудился дольше. Почему ему столько же?» Второй отвечает по Мф. 20:13–15, признавая труд первого. Затем смена ролей.'], ['text' => 'Erster: Ich arbeitete länger. Warum bekam er genauso viel? Zweiter antwortet nach Matthäus 20,13–15 und erkennt die Arbeit an. Rollen wechseln.'], ['kind' => 'instruction', 'target' => 'pair']);
        foreach ([11 => 6, 13 => 5] as $j => $pictureNumber) {
            $frame = $reveal($id.'-case-'.$j, $cards[$j][0], $cards[$j][2], $cards[$j][1], $cards[$j][3]);
            $frame['media'] = $image($pictureNumber);
            $blocks[] = $frame;
        }
        $blocks[] = $free($id.'-request', 'Как признать труд первого и сохранить одинаковый дар последнему?', 'Wie die Arbeit des Ersten anerkennen und gleiche Gabe für den Letzten bewahren?');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-first-plan', 'Первый ответ: что обещано всем, чем различается участие, что получил Саша и как сохранить полный ужин Мише?', 'Erste Antwort: Zusage für alle, verschiedene Teilnahme, was bekam Sascha, wie Mischas volle Portion bewahren?');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Новое сведение: Миша сопровождал бабушку, которой трудно ходить. Он пришёл поздно и сразу включился. Порции остаются одинаковыми. Сохраните первый ответ; допишите второй. Почему общий дар не требует оправдания позднего участника?'], ['text' => 'Neue Information: Mischa begleitete seine Großmutter, die schlecht gehen kann. Er kam spät und half sofort. Portionen bleiben gleich. Erste Antwort behalten, zweite ergänzen. Warum braucht die gemeinsame Gabe keine Rechtfertigung des späten Teilnehmers?'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй ответ: какое предположение изменилось и почему одинаковый дар сохраняется:', 'Zweite Antwort: Welche Vermutung änderte sich, warum bleibt die Gabe gleich:');
    } elseif ($n === 10) {
        $blocks[] = $free($id.'-request', 'Как признать труд Саши, не уменьшая дар Мише', 'Saschas Arbeit anerkennen, ohne Mischas Gabe zu kürzen');
        $blocks[] = $free($id.'-kindness', 'Моё приглашение позднему участнику или общее доброе действие', 'Meine Einladung an den späten Teilnehmer oder gemeinsame Hilfe');
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-personal', 'prompt', ['text' => 'Выбери настоящий или вымышленный случай. Лист остаётся у тебя. Можно назвать только выбранную фразу.'], ['text' => 'Echten oder erfundenen Fall wählen. Blatt bleibt bei dir. Nur gewählten Satz nennen ist möglich.'], ['kind' => 'reflection', 'target' => 'class']);
        $blocks[] = $reveal($id.'-prayer', 'Молитва', 'Gebet', $raw['prayer']."\nАвторская молитва. Можно молча слушать.", $de['prayer']."\nEigens verfasstes Gebet. Still zuhören ist möglich.");
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-promise', 'При разном времени труда все получили…', 'Bei verschiedener Arbeitszeit bekamen alle …');
        $blocks[] = $free($id.'-kindness', 'Доброта хозяина учит меня…', 'Die Güte des Hausherrn lehrt mich …');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => $ru['title'], 'text' => 'Божья милость даёт место и пришедшим поздно.', 'source' => 'Мф. 20:1–16'], ['title' => $german['title'], 'text' => 'Gottes Barmherzigkeit gibt auch spät Gekommenen einen Platz.', 'source' => 'Matthäus 20,1–16'], array_replace($base, ['kind' => 'closing']));
    }
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $readingRu = in_array($n, [2, 3], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu)], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'wine'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'vineyard-file-') && str_ends_with($id, '-v2')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => $file['locale']];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Gleicher Denar bei verschiedener Arbeitszeit, Murren der Ersten und Gottes Güte verstehen.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/vineyard-handout-de.php');

return [
    'sourceRevision' => 'vineyard-ru-de-2026-10-04-v2', 'materialId' => 'c100a419-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a419-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'pochemu-emu-bolshe-chem-mne',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(6)['image'],
        'details' => ['ru' => ['goals' => ['Объяснить ропот при одинаковой плате за разное время труда и ответить на Божью милость к поздно пришедшему без требования преимущества.'], 'materials' => ['Мф. 20:1–16, презентация, шесть страниц раздатки, ручки, пять одинаковых жетонов.', 'Семь ролей, четыре события, шесть случаев, четыре командных сведения, личный лист.'], 'devices' => 'Пары и команды работают на бумаге. Устройства необязательны; короткие ответы по Enter.', 'conditions' => '12–15 лет. 45 минут. Полное чтение, сразу сценка, затем обсуждение. Пять представителей означают группы. Личный лист остаётся у подростка; молитва добровольна.'], 'de' => ['goals' => ['Murren bei gleichem Lohn und verschiedener Arbeitszeit erklären und Gottes Güte zu spät Gekommenen ohne Anspruch auf Vorrang annehmen.'], 'materials' => ['Matthäus 20,1–16, Präsentation, sechs Seiten Arbeitsmaterial, Stifte, fünf gleiche Spielsteine.', 'Sieben Rollen, vier Ereignisse, sechs Fälle, vier Teaminformationen, persönliches Blatt.'], 'devices' => 'Paare und Teams arbeiten auf Papier. Geräte freiwillig; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. Vollständige Lesung, sofort Rollenspiel, danach Besprechung. Fünf Vertreter stehen für Gruppen. Persönliches Blatt bleibt beim Jugendlichen; Gebet freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
