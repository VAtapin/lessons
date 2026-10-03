<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/peter-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/peter-de.php';
$cards = require __DIR__.'/peter-cards.php';
$version = 'd100a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-peter-'.$n, 'versionId' => 'builtin-peter-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => implode("\n", array_slice($text, 2, 3)), 'source' => $text[5]];
    }
    $title = array_shift($text);
    array_pop($text);

    return ['title' => $title, 'text' => implode("\n", $text)];
};
$ruVerses = [];
preg_match_all('/\*\*(Лк\. 22:|Ин\. 21:)(\d+)\.\*\* (.*)/u', $raw['bible'], $ruMatches, PREG_SET_ORDER);
foreach ($ruMatches as $verse) {
    $ruVerses[($verse[1] === 'Лк. 22:' ? 'lk-' : 'jn-').$verse[2]] = str_replace('**', '', $verse[0]);
}
$deVerses = [];
preg_match_all('/^(Lukas 22,|Johannes 21,)(\d+)\. (.*)$/m', $de['bible'], $deMatches, PREG_SET_ORDER);
foreach ($deMatches as $verse) {
    $deVerses[($verse[1] === 'Lukas 22,' ? 'lk-' : 'jn-').$verse[2]] = $verse[0];
}
$excerpt = static fn ($verses, $numbers) => implode("\n", array_map(static fn ($n) => $verses[$n], $numbers));
$plain = static fn ($text) => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $text));
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'peter-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($n === 3 ? 2 : $n);
    $blocks[] = $picture;
    if ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для двух сценок', 'roles' => $roles(['Рассказчик', 'Пётр', 'Служанка', 'Первый мужчина', 'Второй мужчина'])], ['text' => 'Rollen für zwei Spiele', 'roles' => $roles(['Erzähler', 'Petrus', 'Magd', 'Erster Mann', 'Zweiter Mann'])], ['capacities' => array_fill_keys(['role-1', 'role-2', 'role-3', 'role-4', 'role-5'], 1)]);
    }
    if (in_array($n, [3, 5], true)) {
        $frames = $n === 3 ? [[2, ['lk-31', 'lk-32', 'lk-33', 'lk-34'], 'Обещание и предупреждение', 'Versprechen und Warnung', 'Лк. 22:31–34'], [13, ['lk-54', 'lk-55', 'lk-56', 'lk-57'], 'Первое отречение', 'Erste Verleugnung', 'Лк. 22:54–57'], [14, ['lk-58'], 'Второе отречение', 'Zweite Verleugnung', 'Лк. 22:58'], [14, ['lk-59', 'lk-60'], 'Третье отречение', 'Dritte Verleugnung', 'Лк. 22:59–60'], [3, ['lk-61', 'lk-62'], 'Взгляд Господа и плач', 'Blick des Herrn und Weinen', 'Лк. 22:61–62']] : [[4, ['jn-2', 'jn-3', 'jn-6', 'jn-7', 'jn-11', 'jn-14'], 'Встреча после Воскресения', 'Begegnung nach der Auferstehung', 'Ин. 21:1–14'], [5, ['jn-15'], 'Первый вопрос и ответ', 'Erste Frage und Antwort', 'Ин. 21:15'], [5, ['jn-16'], 'Второй вопрос и ответ', 'Zweite Frage und Antwort', 'Ин. 21:16'], [5, ['jn-17'], 'Третий вопрос и ответ', 'Dritte Frage und Antwort', 'Ин. 21:17'], [5, ['jn-18', 'jn-19'], 'Иди за Мною', 'Folge Mir nach', 'Ин. 21:18–19']];
        foreach ($frames as $j => [$pictureNumber, $numbers, $label, $labelDe, $reference]) {
            $frame = $make($id.'-frame-'.($j + 1), 'presentation', ['title' => $label, 'label' => $label, 'hideLabel' => 'Скрыть кадр: '.$label, 'text' => $excerpt($ruVerses, $numbers), 'source' => $reference], ['title' => $labelDe, 'label' => $labelDe, 'hideLabel' => 'Bild ausblenden: '.$labelDe, 'text' => $excerpt($deVerses, $numbers), 'source' => str_replace(['Лк. ', 'Ин. ', ':'], ['Lukas ', 'Johannes ', ','], $reference)], array_replace($base, ['kind' => 'reveal']));
            $frame['media'] = $image($pictureNumber);
            $blocks[] = $frame;
        }
    } elseif ($n === 6) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 5, 4), [0, 1, 2, 3]);
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок событий', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Reihenfolge der Ereignisse herstellen', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-4', 'event-3', 'event-1']]);
        $review = static fn ($locale) => implode(' → ', array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2], [6, 8, 7, 5]));
        $blocks[] = $make($id.'-check', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => $review('ru')], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => $review('de')], array_replace($base, ['kind' => 'reveal', 'reviewBlockId' => $id.'-order']));
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Шесть карточек на пару. Разложите по группам: «Что произошло», «Что мешает», «Шаг исправления». Объясните выбранную карточку. Чем различаются промах в умении и сознательная ложь?'], ['text' => 'Sechs Karten pro Paar. Nach „Was geschehen ist“, „Was hindert“, „Schritt der Wiedergutmachung“ ordnen. Eine gewählte Karte erklären. Wie unterscheiden sich Fehler beim Können und bewusste Lüge?'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-truth', 'Моя честная фраза о случившемся:', 'Mein ehrlicher Satz über das Geschehen:');
        $blocks[] = $free($id.'-repair', 'Первое посильное исправление:', 'Erste machbare Wiedergutmachung:');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-first-plan', 'Кому и какую правду скажет Даня: Как исправить обвинение Лизы: Как восстановить плакат и кто поможет:', 'Wem sagt Danja welche Wahrheit: Wie Lisas Beschuldigung korrigieren: Wie das Plakat wiederherstellen und wer hilft:');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Один называет случившееся и предлагает помощь или исправление. Второй отвечает, что ему нужно дальше. Затем поменяйтесь ролями и возьмите другой случай. Для ошибки в умении подходит просьба объяснить.'], ['text' => 'Einer nennt das Geschehen und bietet Hilfe oder Wiedergutmachung. Der andere antwortet, was er weiter braucht. Dann Rollen wechseln und anderen Fall nehmen. Bei einem Fehler beim Können passt eine Bitte um Erklärung.'], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Новое условие: Лиза услышала извинение, но пока не хочет выступать с Даней в паре. Учитель предлагает Дане помочь восстановить плакат с другим участником. Даня говорит: «Значит, всё бессмысленно?» Что ответим и добавим в план?'], ['text' => 'Neue Bedingung: Lisa hat die Entschuldigung gehört, will aber vorerst nicht mit Danja als Paar auftreten. Die Lehrkraft bietet Danja an, das Plakat mit einem anderen Teilnehmer wiederherzustellen. Danja sagt: „Dann ist alles sinnlos?“ Was antworten und ergänzen wir?'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план: ответ Лизы и следующий поступок Дани:', 'Zweiter Plan: Lisas Antwort und Danjas nächste Handlung:');
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-personal', 'prompt', ['text' => 'Выбери настоящий или вымышленный случай. Лист остаётся у тебя. Можно прочитать только первый шаг, без подробностей.'], ['text' => 'Echten oder erfundenen Fall wählen. Blatt bleibt bei dir. Nur ersten Schritt ohne Einzelheiten vorlesen ist möglich.'], ['kind' => 'reflection', 'target' => 'class']);
        $blocks[] = $make($id.'-prayer', 'presentation', ['label' => 'Молитва', 'hideLabel' => 'Скрыть молитву', 'text' => 'Господи Иисусе Христе, прости мои грехи. Помоги мне сказать правду о своём поступке, попросить прощения и исправить причинённый вред. Дай мне не отчаиваться, принять Твою помощь и снова делать добро. Аминь.'."\n\n".'Авторская молитва. Можно молча слушать.'], ['label' => 'Gebet', 'hideLabel' => 'Gebet ausblenden', 'text' => 'Herr Jesus Christus, vergib meine Sünden. Hilf mir, die Wahrheit über meine Handlung zu sagen, um Verzeihung zu bitten und den verursachten Schaden wiedergutzumachen. Lass mich nicht verzweifeln, Deine Hilfe annehmen und wieder Gutes tun. Amen.'."\n\n".'Eigens verfasstes Gebet. Still zuhören ist möglich.'], array_replace($base, ['kind' => 'reveal']));
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-faith', 'Христос дал Петру новое начало, когда…', 'Christus schenkte Petrus einen neuen Anfang, als…');
        $blocks[] = $free($id.'-step', 'После плохого поступка я могу…', 'Nach einer schlechten Handlung kann ich…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Новое начало и мой поступок', 'text' => 'Надежда помогает начать исправление и делать добро.', 'eyebrow' => 'Урок завершён', 'source' => 'Лк. 22; Ин. 21'], ['title' => 'Neuer Anfang und meine Handlung', 'text' => 'Hoffnung hilft, Wiedergutmachung zu beginnen und Gutes zu tun.', 'eyebrow' => 'Die Stunde ist beendet', 'source' => 'Lukas 22; Johannes 21'], array_replace($base, ['kind' => 'closing']));
    }
    $readingRu = in_array($n, [2, 3, 4, 5], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3, 4, 5], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu)], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'sand'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'peter-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Petrus’ Fall und Christi neuen Auftrag verstehen; Fehler beim Können und bewusste Lüge unterscheiden, Umkehr und konkrete Wiedergutmachung üben.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/peter-handout-de.php');

return [
    'sourceRevision' => 'peter-ru-de-2026-10-03-v1', 'materialId' => 'c100a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'ya-oshibsya-vse-poteryano',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Объяснить, как Христос дал Петру новое начало; различать промах в умении и сознательный вред; признать поступок и выбрать посильное исправление.'], 'materials' => ['Лк. 22:31–34, 54–62 и Ин. 21:1–19 полностью из приложения, презентация, ручки и ножницы.', 'Шесть страниц раздатки: пять ролей, четыре события, шесть фраз, четыре командных сведения, четыре ситуации для пар и личный лист.'], 'devices' => 'Пары и команды работают очно и на бумаге. Устройства не обязательны; короткие ответы отправляются через Enter.', 'conditions' => '12–15 лет. 45 минут. Пары и команды по четыре. После каждого полного чтения сразу своя сценка; обсуждение после обеих. Во второй сценке тот же Пётр и рассказчик; слова Христа читает рассказчик. Личный лист остаётся у подростка, молитва добровольна. Новое поручение не обещает лёгкой жизни; извинение не требует немедленного доверия.'], 'de' => ['goals' => ['Erklären, wie Christus Petrus einen neuen Anfang schenkte; Fehler beim Können und bewussten Schaden unterscheiden; Handlung eingestehen und machbare Wiedergutmachung wählen.'], 'materials' => ['Lukas 22,31–34,54–62 und Johannes 21,1–19 vollständig aus dem Anhang, Präsentation, Stifte und Scheren.', 'Sechs Seiten: fünf Rollen, vier Ereignisse, sechs Sätze, vier Teaminformationen, vier Paarsituationen und persönliches Blatt.'], 'devices' => 'Paare und Teams arbeiten vor Ort und auf Papier. Geräte sind nicht erforderlich; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. Paare und Viererteams. Sofort nach jeder vollständigen Lesung das zugehörige Spiel; Besprechung nach beiden. Im zweiten Spiel dieselben Petrus und Erzähler; Erzähler liest Christi Worte. Persönliches Blatt bleibt beim Jugendlichen, Gebet freiwillig. Neuer Auftrag verspricht kein leichtes Leben; Entschuldigung verlangt kein sofortiges Vertrauen.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
