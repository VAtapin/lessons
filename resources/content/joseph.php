<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/joseph-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/joseph-de.php';
$cards = require __DIR__.'/joseph-cards.php';
$version = 'd100a420-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-joseph-'.$n, 'versionId' => 'builtin-joseph-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => implode("\n", array_slice($text, 2, 3)), 'source' => $text[5]];
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
    $id = 'joseph-step-'.sprintf('%02d', $n);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image([1, 2, 3, 6, 4, 7, 8, 9, 10, 12, 11, 14][$i]);
    $blocks[] = $picture;
    if ($n === 2) {
        $versesRu = preg_split('/(?=\*\*Быт\. 39:\d+\.\*\*)/u', $raw['bible']);
        $versesDe = preg_split('/(?=Genesis 39,\d+\. )/u', $de['bible']);
        foreach ([[1, 6], [7, 6], [13, 6], [19, 5]] as $part => [$from, $count]) {
            $reading = $reveal($id.'-reading-'.($part + 1), 'Читаем Быт. 39:'.$from.'–'.($from + $count - 1), 'Genesis 39,'.$from.'–'.($from + $count - 1).' lesen', $versesRu[0].implode("\n", array_slice($versesRu, $from, $count)), $versesDe[0].implode("\n", array_slice($versesDe, $from, $count)));
            $reading['media'] = $image(2);
            $blocks[] = $reading;
        }
    } elseif ($n === 3) {
        $roles = static fn ($locale) => array_map(static fn ($c, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2]], array_slice($cards, 0, 4), range(0, 3));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Четыре роли — выбор для сценки, не общее число людей в доме. Повествование и слова жены Потифара читает ведущий.', 'roles' => $roles('ru')], ['text' => 'Vier Spielrollen, keine Gesamtzahl im Haus. Lehrkraft liest Erzählung und Worte von Potifars Frau.', 'roles' => $roles('de')], ['capacities' => array_fill_keys(array_map(static fn ($j) => 'role-'.$j, range(1, 4)), 1)]);
        preg_match_all('/\*\*(.*?):\*\* (.*?)(?=\n\n\*\*|\z)/su', $raw['roleplay'], $framesRu, PREG_SET_ORDER);
        preg_match_all('/(?:\A|\n\n)(.*?): (.*?)(?=\n\n|\z)/su', str_replace("\r\n", "\n", $de['roleplay']), $framesDe, PREG_SET_ORDER);
        foreach ($framesRu as $j => $frame) {
            $revealFrame = $reveal($id.'-frame-'.($j + 1), $frame[1], $framesDe[$j][1], $frame[1].': '.$frame[2], $framesDe[$j][1].': '.$framesDe[$j][2]);
            $revealFrame['media'] = $image([2, 3, 4, 5, 6, 6][$j]);
            $blocks[] = $revealFrame;
        }
    } elseif ($n === 4) {
        $ruVerses = preg_split('/(?=\*\*Быт\. 39:\d+\.\*\*)/u', $raw['bible']);
        $deVerses = preg_split('/(?=Genesis 39,\d+\. )/u', $de['bible']);
        $blocks[] = $reveal($id.'-conscience', 'Отказ — Быт. 39:8–9', 'Weigerung — Genesis 39,8–9', implode("\n", array_slice($ruVerses, 8, 2)), implode("\n", array_slice($deVerses, 8, 2)));
        $blocks[] = $reveal($id.'-prison', 'Настоящий финал — Быт. 39:20–23', 'Das wirkliche Ende — Genesis 39,20–23', implode("\n", array_slice($ruVerses, 20, 4)), implode("\n", array_slice($deVerses, 20, 4)));
    } elseif ($n === 5) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'step-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 4, 4), range(0, 3));
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок истории', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Reihenfolge der Geschichte wiederherstellen', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['step-2', 'step-4', 'step-1', 'step-3']]);
        $review = static fn ($locale) => implode(' → ', array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2], [5, 7, 4, 6]));
        $blocks[] = $reveal($id.'-check', 'Проверяем вместе', 'Gemeinsam prüfen', $review('ru'), $review('de'), ['reviewBlockId' => $id.'-order']);
        $blocks[] = $free($id.'-choice', 'Какой выбор сделал сам Иосиф:', 'Welche Wahl Josef selbst traf:');
        $blocks[] = $free($id.'-accusation', 'Какой чужой поступок привёл его в темницу:', 'Welche fremde Handlung ihn ins Gefängnis brachte:');
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Шесть случаев на пару. Группы: «Верность совести», «Нарушение», «Нужно уточнить». Читайте условия. Если сведений мало, назовите вопрос перед выводом.'], ['text' => 'Sechs Fälle pro Paar. Dem Gewissen treu; Verstoß; Nachfragen nötig. Bedingungen lesen. Fehlen Informationen, Frage vor Schluss nennen.'], ['kind' => 'instruction', 'target' => 'pair']);
        foreach (range(12, 17) as $j) {
            $case = $reveal($id.'-case-'.($j - 11), $cards[$j][0], $cards[$j][2], $cards[$j][1], $cards[$j][3]);
            if ($j === 13) {
                $case['media'] = $image(13);
            }
            $blocks[] = $case;
        }
        $blocks[] = $free($id.'-reason', 'Какое разрешение, право или действие определяет вывод?', 'Welche Erlaubnis, welches Recht oder Handeln bestimmt den Schluss?');
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Четыре фразы на пару. Какие помогают сохранить правду? Улучшите первую. Один предлагает скрыть повреждение макета, другой отвечает. Затем смените роли.'], ['text' => 'Vier Sätze pro Paar. Welche bewahren Wahrheit? Ersten verbessern. Einer schlägt Vertuschung des Modellschadens vor, anderer antwortet. Dann Rollen tauschen.'], ['kind' => 'instruction', 'target' => 'pair']);
        foreach (range(8, 11) as $j) {
            $blocks[] = $reveal($id.'-phrase-'.($j - 7), $cards[$j][0], $cards[$j][2], $cards[$j][1], $cards[$j][3]);
        }
        $blocks[] = $free($id.'-refusal', 'Мой спокойный отказ и следующий шаг:', 'Meine ruhige Weigerung und mein nächster Schritt:');
        $blocks[] = $free($id.'-support', 'Как я попрошу помощи при ложном обвинении:', 'Wie ich bei falscher Beschuldigung Hilfe erbitte:');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-first-plan', 'Мой поступок и доверенная мне просьба: Кому и что скажу без перекладывания вины: Какое исправление предложу и у кого попрошу помощи:', 'Mein Handeln und anvertraute Bitte: Wem sage ich was ohne Abschieben der Schuld: Welche Wiedergutmachung und welche Hilfe:');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Новое предложение Дениса: «Скажем, что Маша сама плохо склеила. Тогда тебя не обвинят». Что появилось в этой версии? Нужно ли теперь обвинить Машу, чтобы сохранить своё лицо? Сохраните первый план.'], ['text' => 'Denis neuer Vorschlag: „Sagen wir, Mascha hat selbst schlecht geklebt. Dann beschuldigt man dich nicht“. Was kam hinzu? Muss Mascha beschuldigt werden, um mein Gesicht zu wahren? Ersten Plan behalten.'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план: мой отказ от ложной версии и честная фраза:', 'Zweiter Plan: Meine Ablehnung der falschen Version und mein ehrlicher Satz:');
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'В парах один произносит признание Кирилла и просьбу разрешить ремонт. Второй отвечает и повторяет факты. Затем смена ролей. В конце каждый формулирует просьбу о помощи для случая ложного обвинения.'], ['text' => 'Im Paar spricht einer Kirills Eingeständnis und Bitte um Reparaturerlaubnis. Zweiter antwortet und wiederholt Fakten. Rollen tauschen. Jeder Hilfebitte bei falscher Beschuldigung formulieren.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-admission', 'Моё признание и просьба разрешить исправление:', 'Mein Eingeständnis und Bitte um Wiedergutmachung:');
        $blocks[] = $free($id.'-help', 'Как попрошу поддержки при ложном обвинении:', 'Wie ich bei falscher Beschuldigung Unterstützung erbitte:');
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-personal', 'prompt', ['text' => 'Выбери настоящий или вымышленный случай. Лист остаётся у тебя. Можно назвать только выбранную фразу.'], ['text' => 'Echten oder erfundenen Fall wählen. Blatt bleibt bei dir. Nur gewählten Satz nennen ist möglich.'], ['kind' => 'reflection', 'target' => 'class']);
        $blocks[] = $reveal($id.'-prayer', 'Молитва', 'Gebet', $raw['prayer']."\nАвторская молитва. Можно молча слушать.", $de['prayer']."\nEigens verfasstes Gebet. Still zuhören ist möglich.");
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-refusal', 'Иосиф отказался, потому что…', 'Josef weigerte sich, weil…');
        $blocks[] = $free($id.'-step', 'Когда никто не видит, мой честный шаг…', 'Wenn niemand hinsieht, ist mein ehrlicher Schritt…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => $ru['title'], 'text' => 'Бог с Иосифом и в темнице. Помощь можно попросить.', 'source' => 'Быт. 39:1–23'], ['title' => $german['title'], 'text' => 'Gott ist auch im Gefängnis mit Josef. Man darf um Hilfe bitten.', 'source' => 'Genesis 39,1–23'], array_replace($base, ['kind' => 'closing']));
    }
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $readingRu = in_array($n, [2, 3], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu)], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'umber'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'joseph-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Josefs Weigerung und Treue zu Gott verstehen; eigenes Handeln am Gewissen prüfen und ohne äußere Kontrolle ehrlich handeln.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/joseph-handout-de.php');

return [
    'sourceRevision' => 'joseph-ru-de-2026-10-03-v1', 'materialId' => 'c100a420-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a420-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'kogda-nikto-ne-vidit',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Понять отказ Иосифа и его верность Богу, научиться проверять свой поступок по совести и выбрать честное действие без внешнего контроля.'], 'materials' => ['Быт. 39:1–23, презентация, шесть страниц раздатки, ручки, платок как условная верхняя одежда и два предмета доверенного дела.', 'Четыре роли, четыре события, четыре фразы, шесть случаев, четыре командных сведения, личный лист.'], 'devices' => 'Пары и команды работают на бумаге. Устройства необязательны; короткие ответы по Enter.', 'conditions' => '12–15 лет. 45 минут. Полное чтение, сразу сценка, затем обсуждение. Финал в темнице; освобождение не разыгрываем. Личный лист остаётся у подростка; молитва добровольна.'], 'de' => ['goals' => ['Josefs Weigerung und Treue zu Gott verstehen; eigenes Handeln am Gewissen prüfen und ohne äußere Kontrolle ehrlich handeln.'], 'materials' => ['Genesis 39,1–23, Präsentation, sechs Seiten Arbeitsmaterial, Stifte, Tuch als Obergewand, zwei Arbeitsgegenstände.', 'Vier Rollen, vier Ereignisse, vier Sätze, sechs Fälle, vier Teaminformationen, persönliches Blatt.'], 'devices' => 'Paare und Teams arbeiten auf Papier. Geräte freiwillig; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. Vollständige Lesung, sofort Rollenspiel, danach Besprechung. Ende im Gefängnis; Befreiung nicht spielen. Persönliches Blatt bleibt privat; Gebet freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
