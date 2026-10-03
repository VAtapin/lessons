<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/truth-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/truth-de.php';
$cards = require __DIR__.'/truth-cards.php';
$version = 'd100a418-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-truth-'.$n, 'versionId' => 'builtin-truth-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => implode("\n", array_slice($text, 3, 3)), 'source' => $text[6]];
    }
    $title = array_shift($text);
    array_pop($text);

    return ['title' => $title, 'text' => implode("\n", $text)];
};
$plain = static fn ($text) => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $text));
$ruVersions = preg_split('/\*\*(Общее начало|Версия 1 — прямая ложь|Версия 2 — полуправда|Версия 3 — честный ответ с помощью):\*\* /u', $raw['roleplay'], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
$deVersions = preg_split('/(Gemeinsamer Anfang|Version 1 — direkte Lüge|Version 2 — Halbwahrheit|Version 3 — ehrliche Antwort mit Hilfe): /u', $de['roleplay'], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
$reveal = static fn ($id, $label, $labelDe, $ru, $german, $extra = []) => $make($id, 'presentation', ['title' => $label, 'label' => $label, 'hideLabel' => 'Скрыть: '.$label, 'text' => $plain($ru)], ['title' => $labelDe, 'label' => $labelDe, 'hideLabel' => 'Ausblenden: '.$labelDe, 'text' => $german], array_replace($base, ['kind' => 'reveal'], $extra));
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'truth-step-'.sprintf('%02d', $n);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($n);
    $blocks[] = $picture;
    if ($n === 2) {
        $blocks[] = $reveal($id.'-reading', 'Читаем Еф. 4:25–32', 'Epheser 4,25–32 lesen', $raw['bible'], $de['bible']);
    } elseif ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для трёх версий', 'roles' => $roles(['Учитель', 'Саша', 'Миша', 'Аня'])], ['text' => 'Rollen für drei Versionen', 'roles' => $roles(['Lehrkraft', 'Sascha', 'Mischa', 'Anja'])], ['capacities' => array_fill_keys(['role-1', 'role-2', 'role-3', 'role-4'], 1)]);
        for ($j = 0; $j < 4; $j++) {
            // Every alternative returns to the same unfinished project, never to a new event.
            $textRu = ($j ? $ruVersions[0].': '.$ruVersions[1]."\n\n" : '').$ruVersions[$j * 2].': '.$ruVersions[$j * 2 + 1];
            $textDe = ($j ? $deVersions[0].': '.$deVersions[1]."\n\n" : '').$deVersions[$j * 2].': '.$deVersions[$j * 2 + 1];
            $frame = $reveal($id.'-frame-'.($j + 1), $ruVersions[$j * 2], $deVersions[$j * 2], $textRu, $textDe);
            $frame['media'] = $image(3);
            $blocks[] = $frame;
        }
    } elseif ($n === 4) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'step-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 4, 4), [0, 1, 2, 3]);
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Путь к честному ответу', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Weg zur ehrlichen Antwort', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['step-3', 'step-1', 'step-4', 'step-2']]);
        $review = static fn ($locale) => implode(' → ', array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2], [6, 4, 7, 5]));
        $blocks[] = $reveal($id.'-check', 'Проверяем вместе', 'Gemeinsam prüfen', $review('ru'), $review('de'), ['reviewBlockId' => $id.'-order']);
    } elseif ($n === 5) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Шесть карточек на пару. Разложите по группам: «Ложь», «Полуправда», «Честный ответ». Читайте условия. Какое впечатление получит слушатель? Объясните выбранную карточку.'], ['text' => 'Sechs Karten pro Paar. Nach „Lüge“, „Halbwahrheit“, „Ehrliche Antwort“ ordnen. Bedingungen lesen. Welchen Eindruck bekommt der Zuhörer? Gewählte Karte erklären.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-impression', 'Что слушатель может понять неверно:', 'Was der Zuhörer falsch verstehen könnte:');
        $blocks[] = $free($id.'-clarify', 'Как уточнить ответ честно и бережно:', 'Wie die Antwort ehrlich und behutsam präzisieren:');
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Один задаёт вопрос, другой отвечает своими словами и предлагает посильный шаг. Слушатель повторяет, как понял ответ. Затем смените роли и возьмите другой случай.'], ['text' => 'Einer fragt, der andere antwortet mit eigenen Worten und bietet einen machbaren Schritt. Zuhörer wiederholt, wie er die Antwort verstand. Dann Rollen wechseln und anderen Fall nehmen.'], ['kind' => 'instruction', 'target' => 'pair']);
        foreach ([18 => 6, 19 => 9, 20 => 13, 21 => 14] as $card => $pictureNumber) {
            $frame = $reveal($id.'-case-'.($card - 17), $cards[$card][0], $cards[$card][2], $cards[$card][1], $cards[$card][3]);
            $frame['media'] = $image($pictureNumber);
            $blocks[] = $frame;
        }
    } elseif ($n === 7) {
        $blocks[] = $free($id.'-first-plan', 'Что известно и что ещё надо проверить: Кому и какие сведения нужны: Как помочь и что реально обещать:', 'Was bekannt ist und noch geprüft werden muss: Wer welche Informationen braucht: Wie helfen und was realistisch versprechen:');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-message', 'Что сообщить учителю? Что ещё неизвестно? Какую помощь предложить?', 'Was der Lehrkraft mitteilen? Was ist noch unbekannt? Welche Hilfe anbieten?');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Новое условие: Саша уже написал в общий чат «Все книги готовы к передаче». Учитель подтвердил передачу завтра. Миша просит не исправлять сообщение, чтобы Саша не потерял доброе имя. Что добавим в план?'], ['text' => 'Neue Bedingung: Sascha schrieb schon in den Gruppenchat „Alle Bücher sind zur Übergabe bereit“. Die Lehrkraft bestätigte Übergabe morgen. Mischa bittet, die Nachricht nicht zu berichtigen, damit Sascha seinen guten Namen behält. Was ergänzen wir im Plan?'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план: кому и как исправить прежнее сообщение:', 'Zweiter Plan: Wem und wie frühere Nachricht berichtigen:');
    } elseif ($n === 10) {
        $blocks[] = $free($id.'-correction', 'Как я помогу делом?', 'Wie helfe ich durch Handeln?');
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-personal', 'prompt', ['text' => 'Выбери настоящий или вымышленный случай. Лист остаётся у тебя. Можно прочитать только выбранную фразу.'], ['text' => 'Echten oder erfundenen Fall wählen. Blatt bleibt bei dir. Nur gewählten Satz vorlesen ist möglich.'], ['kind' => 'reflection', 'target' => 'class']);
        $blocks[] = $reveal($id.'-prayer', 'Молитва', 'Gebet', $raw['prayer']."\nАвторская молитва. Можно молча слушать.", $de['prayer']."\nEigens verfasstes Gebet. Still zuhören ist möglich.");
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-truth', 'Полуправда вводит в заблуждение, когда…', 'Eine Halbwahrheit führt in die Irre, wenn…');
        $blocks[] = $free($id.'-help', 'Вместо обмана ради друга я могу…', 'Statt für einen Freund zu täuschen, kann ich…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => $ru['title'], 'text' => 'Правда и забота о ближнем поддерживают друг друга.', 'source' => 'Еф. 4:25–32'], ['title' => $german['title'], 'text' => 'Wahrheit und Sorge für den Nächsten unterstützen einander.', 'source' => 'Epheser 4,25–32'], array_replace($base, ['kind' => 'closing']));
    }
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $readingRu = in_array($n, [2, 3], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu)], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'steel'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'truth-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Lüge und absichtliche Halbwahrheit unterscheiden; ehrlichen und behutsamen nächsten Schritt wählen.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/truth-handout-de.php');

return [
    'sourceRevision' => 'truth-ru-de-2026-10-03-v1', 'materialId' => 'c100a418-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a418-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'mozhno-li-sovrat-radi-dobra',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Различать ложь и полуправду, учитывать вывод слушателя, отвечать честно и бережно, исправлять прежнее сообщение и помогать делом.'], 'materials' => ['Еф. 4:25–32, презентация, шесть страниц раздатки, ручки.', 'Четыре роли, четыре шага, шесть фраз, четыре командных сведения, четыре случая для пар, личный лист.'], 'devices' => 'Работа в парах и командах на бумаге. Устройства необязательны; короткие ответы отправляются по Enter.', 'conditions' => '12–15 лет. 45 минут. Полное чтение, сразу три альтернативные версии одной сценки, обсуждение после третьей. Личный лист остаётся у участника; молитва добровольна.'], 'de' => ['goals' => ['Lüge und Halbwahrheit unterscheiden, den Schluss des Zuhörers beachten, ehrlich und behutsam antworten, frühere Nachricht berichtigen und durch Handeln helfen.'], 'materials' => ['Epheser 4,25–32, Präsentation, sechs Seiten Arbeitsmaterial, Stifte.', 'Vier Rollen, vier Schritte, sechs Sätze, vier Teaminformationen, vier Paarsituationen, persönliches Blatt.'], 'devices' => 'Paare und Teams arbeiten auf Papier. Geräte freiwillig; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. Vollständiges Lesen, sofort drei alternative Versionen desselben Spiels, Besprechung nach der dritten. Persönliches Blatt bleibt beim Teilnehmer; Gebet freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
