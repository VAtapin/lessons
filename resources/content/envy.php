<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/envy-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/envy-de.php';
$cards = require __DIR__.'/envy-cards.php';
$version = 'd080a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-envy-'.$n, 'versionId' => 'builtin-envy-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => implode("\n", array_slice($text, 2, 3)), 'source' => $text[5]];
    }
    $title = array_shift($text);
    array_pop($text);
    $source = null;
    foreach ($text as $i => $line) {
        if (str_starts_with($line, 'Быт. ')) {
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
    $id = 'envy-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($n);
    $blocks[] = $picture;
    if ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для сценки', 'roles' => $roles(['Рассказчик', 'Иосиф', 'Иаков', 'Рувим', 'Иуда', 'Брат', 'Торговец'])], ['text' => 'Rollen für das Spiel', 'roles' => $roles(['Erzähler', 'Josef', 'Jakob', 'Ruben', 'Juda', 'Bruder', 'Händler'])], ['capacities' => ['role-1' => 1, 'role-2' => 1, 'role-3' => 1, 'role-4' => 1, 'role-5' => 1, 'role-6' => 1, 'role-7' => 1]]);
        foreach ([[2, [3, 4, 9, 11], 'Одежда и сны', 'Gewand und Träume', 'Быт. 37:3–4, 9, 11'], [13, [21, 22, 23, 24], 'Ров и слова Рувима', 'Grube und Rubens Worte', 'Быт. 37:21–24'], [4, [26, 27, 28], 'Предложение Иуды и продажа', 'Judas Vorschlag und Verkauf', 'Быт. 37:26–28'], [14, [33, 34, 35, 36], 'Горе отца и Египет', 'Trauer des Vaters und Ägypten', 'Быт. 37:33–36']] as [$pictureNumber, $numbers, $label, $labelDe, $reference]) {
            $frame = $make($id.'-frame-'.$pictureNumber, 'presentation', ['title' => $label, 'label' => $label, 'hideLabel' => 'Скрыть кадр: '.$label, 'text' => $excerpt($ruVerses, $numbers), 'source' => $reference], ['title' => $labelDe, 'label' => $labelDe, 'hideLabel' => 'Bild ausblenden: '.$labelDe, 'text' => $excerpt($deVerses, $numbers), 'source' => str_replace(['Быт. ', ':'], ['Genesis ', ','], $reference)], array_replace($base, ['kind' => 'reveal']));
            $frame['media'] = $image($pictureNumber);
            $blocks[] = $frame;
        }
    } elseif ($n === 4) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 7, 4), [0, 1, 2, 3]);
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок событий', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Reihenfolge der Ereignisse', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-1', 'event-4', 'event-3']]);
        $review = static fn ($locale) => implode("\n", array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2].' · '.$cards[$j][$locale === 'ru' ? 1 : 3], [8, 7, 10, 9]));
        $blocks[] = $make($id.'-check', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => $review('ru')], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => $review('de')], array_replace($base, ['kind' => 'reveal', 'reviewBlockId' => $id.'-order']));
    } elseif ($n === 5) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Шесть карточек на пару. Первые три — переживания; последние три — действия. Объясните разницу между желанием учиться, болью от сравнения и желанием чужой неудачи. Одну вредную фразу замените доброй.'], ['text' => 'Sechs Karten pro Paar. Die ersten drei sind Empfindungen, die letzten drei Handlungen. Lernwunsch, Schmerz durch Vergleich und Wunsch nach fremdem Scheitern unterscheiden. Einen schädlichen Satz freundlich ersetzen.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-replacement', 'Моя честная и добрая замена вредной фразы:', 'Mein ehrlicher und freundlicher Ersatz für den schädlichen Satz:');
    } elseif ($n === 6) {
        $blocks[] = $free($id.'-replacement', 'Моя честная и добрая замена вредной фразы:', 'Mein ehrlicher und freundlicher Ersatz für den schädlichen Satz:');
        $blocks[] = $free($id.'-growth', 'Мой конкретный шаг роста:', 'Mein konkreter Wachstumsschritt:');
    } elseif ($n === 7) {
        $blocks[] = $free($id.'-first-plan', 'Что сохраним на плакате и что исправим: К кому обратимся и как попросим: Что поможет Диме развивать свой дар:', 'Was wir auf dem Plakat behalten und berichtigen: An wen wir uns wenden und wie wir bitten: Was Dima hilft, seine Gabe zu entwickeln:');
    } elseif ($n === 8) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Первый читает контекст. Второй называет переживание и конкретную просьбу без унижения другого. Собеседник: «Я услышал, что…». Затем поменяйтесь ролями и выберите ещё один случай.'], ['text' => 'Einer liest den Kontext. Der andere nennt Gefühl und konkrete Bitte ohne Erniedrigung. Gegenüber: „Ich habe gehört, dass…“. Danach Rollen wechseln und anderen Fall wählen.'], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Новое условие: кто-то предлагает стереть Олино имя. Оля говорит: «Давайте добавим имя автора фото рядом». Справедливость восстановлена, но Диме всё ещё завидно. Что теперь?'], ['text' => 'Neue Bedingung: Jemand schlägt vor, Oljas Namen wegzuradieren. Olja sagt: „Lasst uns den Namen des Fotografen daneben ergänzen“. Gerechtigkeit ist wiederhergestellt, aber Dima ist noch neidisch. Was nun?'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план после нового условия: что добавим:', 'Zweiter Plan nach der neuen Bedingung: Was ergänzen wir:');
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-faith', 'Братья могли остановиться, когда…', 'Die Brüder hätten aufhören können, als…');
        $blocks[] = $free($id.'-step', 'Если мне завидно, я могу…', 'Wenn ich neidisch bin, kann ich…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Чужое добро и мой выбор', 'text' => 'Чужое добро не уменьшает моей ценности.', 'eyebrow' => 'Урок завершён', 'source' => 'Быт. 37'], ['title' => 'Das Gute des anderen und meine Wahl', 'text' => 'Das Gute des anderen mindert meinen Wert nicht.', 'eyebrow' => 'Die Stunde ist beendet', 'source' => 'Genesis 37'], array_replace($base, ['kind' => 'closing']));
    }
    $readingRu = in_array($n, [2, 3], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu)], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'rose'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'envy-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Neid, Lernwunsch und Schmerz durch Ungerechtigkeit unterscheiden; Folgen der Entscheidungen der Brüder erklären und einen guten Schritt wählen.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/envy-handout-de.php');

return [
    'sourceRevision' => 'envy-ru-de-2026-10-03-v1', 'materialId' => 'c080a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e080a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'a-esli-mne-zavidno',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Различать зависть, желание научиться и боль от несправедливости; объяснить последствия решений братьев Иосифа; выбрать добрый шаг.'], 'materials' => ['Полная Быт. 37:1–36 из приложения, PowerPoint, экран, ножницы, ручки, цветная ткань и два стула.', 'Шесть страниц иллюстрированной раздатки: семь ролей, четыре события, шесть мыслей и поступков, четыре командных сведения, четыре ситуации для пар и личный лист.', 'При отсутствии проектора используйте PNG как крупные распечатки.'], 'devices' => 'Пары и команды работают очно и на бумаге. Устройства не обязательны; короткие ответы отправляются через Enter.', 'conditions' => '12–15 лет. 45 минут. 8–24 человека; пары и команды по четыре. Личный лист остаётся у подростка; личные случаи и молитва добровольны. Читается вся Быт. 37:1–36. Глава заканчивается горем Иакова и продажей Иосифа Потифару; дальнейшее примирение не добавляется. Библейские групповые кадры показывают часть семьи.'], 'de' => ['goals' => ['Neid, Lernwunsch und Schmerz durch Ungerechtigkeit unterscheiden; Folgen der Entscheidungen der Brüder Josefs erklären und einen guten Schritt wählen.'], 'materials' => ['Ganze Genesis 37,1–36 aus dem Anhang, PowerPoint, Bildschirm, Schere, Stifte, bunter Stoff und zwei Stühle.', 'Sechs illustrierte Seiten: sieben Rollen, vier Ereignisse, sechs Gedanken und Handlungen, vier Teaminformationen, vier Paarsituationen und persönliches Blatt.', 'Ohne Projektor PNG als große Ausdrucke verwenden.'], 'devices' => 'Paare und Teams arbeiten vor Ort und auf Papier. Geräte sind nicht erforderlich; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. 8–24 Teilnehmende, Paare und Viererteams. Persönliches Blatt bleibt beim Jugendlichen; persönliche Fälle und Gebet freiwillig. Ganze Genesis 37,1–36. Ende: Jakobs Trauer und Josefs Verkauf an Potifar; spätere Versöhnung nicht ergänzen. Biblische Gruppenbilder zeigen einen Teil der Familie.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
