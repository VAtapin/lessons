<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/pressure-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/pressure-de.php';
$cards = require __DIR__.'/pressure-cards.php';
$version = 'd070a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-pressure-'.$n, 'versionId' => 'builtin-pressure-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => implode("\n", array_slice($text, 2, 3)), 'source' => $text[5]];
    }
    $title = array_shift($text);
    array_pop($text);
    $source = null;
    foreach ($text as $i => $line) {
        if (str_starts_with($line, 'Дан. ')) {
            $source = $line;
            unset($text[$i]);
        }
    }

    return array_filter(['title' => $title, 'text' => implode("\n", $text), 'source' => $source], static fn ($v) => $v !== null);
};
$ruVerses = [];
preg_match_all('/\*\*(\d+)\.\*\* (.*)/u', $raw['bible'], $ruMatches, PREG_SET_ORDER);
foreach ($ruMatches as $verse) {
    $ruVerses[(int) $verse[1]] = $verse[1].'. '.$verse[2];
}
$deVerses = [];
preg_match_all('/^(\d+)\. (.*)$/m', $de['bible'], $deMatches, PREG_SET_ORDER);
foreach ($deMatches as $verse) {
    $deVerses[(int) $verse[1]] = $verse[0];
}
$excerpt = static fn ($verses, $numbers) => implode("\n", array_map(static fn ($n) => $verses[$n], $numbers));
$plain = static fn ($text) => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $text));
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'pressure-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($n);
    $blocks[] = $picture;
    if ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для сценки', 'roles' => $roles(['Рассказчик-глашатай', 'Царь', 'Седрах', 'Мисах', 'Авденаго'])], ['text' => 'Rollen für das Spiel', 'roles' => $roles(['Erzähler und Herold', 'König', 'Schadrach', 'Meschach', 'Abed-Nego'])], ['capacities' => ['role-1' => 1, 'role-2' => 1, 'role-3' => 1, 'role-4' => 1, 'role-5' => 1]]);
        foreach ([[13, [4, 5, 6], 'Повеление царя', 'Befehl des Königs', 'Дан. 3:4–6'], [3, [16, 17, 18], 'Ответ троих', 'Antwort der drei', 'Дан. 3:16–18'], [4, [49, 50, 51], 'Четверо в печи', 'Vier im Ofen', 'Дан. 3:49–51'], [14, [93, 94, 95], 'Трое выходят', 'Drei kommen heraus', 'Дан. 3:93–95']] as [$pictureNumber, $numbers, $label, $labelDe, $reference]) {
            $frame = $make($id.'-frame-'.$pictureNumber, 'presentation', ['title' => $label, 'label' => $label, 'hideLabel' => 'Скрыть кадр: '.$label, 'text' => $excerpt($ruVerses, $numbers), 'source' => $reference], ['title' => $labelDe, 'label' => $labelDe, 'hideLabel' => 'Bild ausblenden: '.$labelDe, 'text' => $excerpt($deVerses, $numbers), 'source' => str_replace(['Дан. ', ':'], ['Daniel ', ','], $reference)], array_replace($base, ['kind' => 'reveal']));
            $frame['media'] = $image($pictureNumber);
            $blocks[] = $frame;
        }
    } elseif ($n === 4) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 5, 4), [0, 1, 2, 3]);
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок событий', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Reihenfolge der Ereignisse', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-4', 'event-1', 'event-3']]);
        $review = static fn ($locale) => implode("\n", array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2].' · '.$cards[$j][$locale === 'ru' ? 1 : 3], [6, 8, 5, 7]));
        $blocks[] = $make($id.'-check', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => $review('ru')], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => $review('de')], array_replace($base, ['kind' => 'reveal', 'reviewBlockId' => $id.'-order']));
    } elseif ($n === 5) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Шесть карточек на пару. Выберите две, подчеркните слова давления. Замените каждую честным приглашением, которое уважает отказ. Объясните, что изменилось.'], ['text' => 'Sechs Karten pro Paar. Zwei auswählen, Druckworte unterstreichen. Durch ehrliche Einladung ersetzen, die ein Nein respektiert. Die Änderung erklären.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-invitation', 'Предложите честное приглашение.', 'Eine ehrliche Einladung vorschlagen.');
    } elseif ($n === 6) {
        $blocks[] = $free($id.'-refusal', 'Моя ясная фраза отказа:', 'Mein klarer Satz zum Nein:');
        $blocks[] = $free($id.'-alternative', 'Вместо плохого поступка я предлагаю:', 'Statt der schlechten Handlung schlage ich vor:');
    } elseif ($n === 7) {
        $blocks[] = $free($id.'-first-plan', 'Первый шаг, чтобы не распространять вред: Как учтём просьбу Артёма: К кому обратимся и какое другое общее дело предложим:', 'Erster Schritt gegen die Verbreitung des Schadens: Wie wir Artjoms Bitte beachten: An wen wir uns wenden und welche andere gemeinsame Aktivität wir anbieten:');
    } elseif ($n === 8) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Первый читает напечатанное предложение, второй отвечает спокойно и конкретно. Слушатель повторяет: «Я услышал твой выбор…». Затем поменяйтесь ролями и возьмите другую ситуацию.'], ['text' => 'Einer liest den gedruckten Vorschlag, der andere antwortet ruhig und konkret. Zuhörer: „Ich habe deine Entscheidung gehört…“ Dann Rollen wechseln und anderen Fall nehmen.'], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Новое условие: в чате снова требуют переслать фото, а за отказ обещают исключить. Как изменится ваш план?'], ['text' => 'Neue Bedingung: Im Chat verlangt man erneut, das Foto weiterzuleiten, und droht bei Ablehnung mit Ausschluss. Wie ändert sich euer Plan?'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план: что добавим, если давление продолжается:', 'Zweiter Plan: Was wir bei weiterem Druck ergänzen:');
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-faith', 'Трое отказались, потому что…', 'Die drei lehnten ab, weil…');
        $blocks[] = $free($id.'-step', 'Когда на меня давят, я могу…', 'Wenn man mich unter Druck setzt, kann ich…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Смелость и поддержка', 'text' => 'Друзья могут поддержать друг друга в добром выборе.', 'eyebrow' => 'Урок завершён', 'source' => 'Дан. 3'], ['title' => 'Mut und Unterstützung', 'text' => 'Freunde können einander bei guten Entscheidungen unterstützen.', 'eyebrow' => 'Die Stunde ist beendet', 'source' => 'Daniel 3'], array_replace($base, ['kind' => 'closing']));
    }
    $readingRu = in_array($n, [2, 3], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu)], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'indigo'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'pressure-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Druck erkennen, die Entscheidung der drei erklären, ruhiges Nein üben und einen konkreten unterstützenden Schritt wählen.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/pressure-handout-de.php');

return [
    'sourceRevision' => 'pressure-ru-de-2026-10-03-v1', 'materialId' => 'c070a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e070a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'vse-tak-delayut',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Распознавать давление, объяснить выбор трёх отроков, потренировать спокойный отказ и выбрать конкретный шаг поддержки.'], 'materials' => ['Библия или выбранные фрагменты Дан. 3 из пособия, PowerPoint, экран, ножницы, ручки.', 'Шесть страниц иллюстрированной раздатки: пять ролей, четыре события, шесть фраз давления, четыре командных сведения, четыре ситуации для пар и личный лист.', 'При отсутствии проектора используйте PNG как крупные распечатки.'], 'devices' => 'Пары и команды работают очно и на бумаге. Устройства не обязательны; короткие ответы отправляются через Enter.', 'conditions' => '12–15 лет. 45 минут. 8–24 человека; пары и команды по четыре. Личный лист остаётся у подростка; личные случаи и молитва добровольны. Читается подборка Дан. 3:1, 4–7, 12–24, 49–51, 91–95, а не вся глава. Православная нумерация; ст. 91–95 соответствуют ст. 24–28 в изданиях без молитвы Азарии и песни отроков.'], 'de' => ['goals' => ['Druck erkennen, die Entscheidung der drei erklären, ruhiges Nein üben und einen konkreten unterstützenden Schritt wählen.'], 'materials' => ['Bibel oder ausgewählte Daniel-3-Texte, PowerPoint, Bildschirm, Schere, Stifte.', 'Sechs illustrierte Seiten: fünf Rollen, vier Ereignisse, sechs Druckformulierungen, vier Teaminformationen, vier Paarsituationen und persönliches Blatt.', 'Ohne Projektor PNG als große Ausdrucke verwenden.'], 'devices' => 'Paare und Teams arbeiten vor Ort und auf Papier. Geräte sind nicht erforderlich; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. 8–24 Teilnehmende, Paare und Viererteams. Persönliches Blatt bleibt beim Jugendlichen; persönliche Fälle und Gebet freiwillig. Auswahl aus Daniel 3,1; 4–7; 12–24; 49–51; 91–95, nicht das ganze Kapitel. Orthodoxe Zählung; Verse 91–95 entsprechen 24–28 in Ausgaben ohne Asarjas Gebet und das Lied der drei.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
