<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/loaves-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/loaves-de.php';
$cards = require __DIR__.'/loaves-cards.php';
$version = 'd100a421-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-loaves-'.$n, 'versionId' => 'builtin-loaves-'.$n.'-v1']];
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
    $id = 'loaves-step-'.sprintf('%02d', $n);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image([1, 2, 5, 4, 7, 3, 10, 9, 11, 13, 12, 14][$i]);
    $blocks[] = $picture;
    if ($n === 1) {
        $options = static fn ($de) => array_map(static fn ($text, $j) => ['optionId' => 'resource-'.($j + 1), 'text' => $text], $de ? ['Zeit', 'Aufmerksamkeit', 'Eine eigene Sache', 'Eine Fähigkeit'] : ['Время', 'Внимание', 'Своя вещь', 'Умение'], range(0, 3));
        $blocks[] = $make($id.'-resource', 'poll', ['question' => 'Что уже есть для небольшого доброго дела?', 'options' => $options(false)], ['question' => 'Was ist für eine kleine gute Tat bereits da?', 'options' => $options(true)], ['allowRepeat' => true]);
    } elseif ($n === 2) {
        $versesRu = preg_split('/(?=\*\*Ин\. 6:\d+\.\*\*)/u', $raw['bible']);
        $versesDe = preg_split('/(?=Johannes 6,\d+\. )/u', $de['bible']);
        foreach ([[1, 5], [6, 5], [11, 5]] as $part => [$from, $count]) {
            $reading = $reveal($id.'-reading-'.($part + 1), 'Читаем Ин. 6:'.$from.'–'.($from + $count - 1), 'Johannes 6,'.$from.'–'.($from + $count - 1).' lesen', $versesRu[0].implode("\n", array_slice($versesRu, $from, $count)), $versesDe[0].implode("\n", array_slice($versesDe, $from, $count)));
            $reading['media'] = $image(2);
            $blocks[] = $reading;
        }
    } elseif ($n === 3) {
        $roles = static fn ($locale) => array_map(static fn ($c, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2]], array_slice($cards, 0, 6), range(0, 5));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Шесть ролей. Двое из народа представляют множество людей. Повествование читает ведущий.', 'roles' => $roles('ru')], ['text' => 'Sechs Rollen. Zwei Menschen vertreten die Menge. Die Lehrkraft liest die Erzählung.', 'roles' => $roles('de')], ['capacities' => array_fill_keys(array_map(static fn ($j) => 'role-'.$j, range(1, 6)), 1)]);
        preg_match_all('/\*\*(.*?):\*\* (.*?)(?=\n\n\*\*|\z)/su', $raw['roleplay'], $framesRu, PREG_SET_ORDER);
        preg_match_all('/(?:\A|\n\n)(.*?): (.*?)(?=\n\n|\z)/su', str_replace("\r\n", "\n", $de['roleplay']), $framesDe, PREG_SET_ORDER);
        foreach ($framesRu as $j => $frame) {
            $revealFrame = $reveal($id.'-frame-'.($j + 1), $frame[1], $framesDe[$j][1], $frame[1].': '.$frame[2], $framesDe[$j][1].': '.$framesDe[$j][2]);
            $revealFrame['media'] = $image([3, 4, 6, 5, 7, 8][$j]);
            $blocks[] = $revealFrame;
        }
    } elseif ($n === 4) {
        $items = static fn ($de, $side) => array_map(static fn ($text, $j) => ['itemId' => $side.'-'.($j + 1), 'text' => $text], $side === 'number' ? ['5', '2', '12'] : ($de ? ['Gerstenbrote am Anfang', 'Fische am Anfang', 'Körbe mit Resten nach der Sättigung'] : ['Ячменные хлебы в начале', 'Рыбы в начале', 'Короба с остатками после насыщения']), range(0, 2));
        $meanings = static fn ($german) => array_map(static fn ($j) => $items($german, 'meaning')[$j], [2, 0, 1]);
        $blocks[] = $make($id.'-numbers', 'matching', ['question' => 'Соедините числа и то, что они означают', 'left' => $items(false, 'number'), 'right' => $meanings(false)], ['question' => 'Zahlen und ihre Bedeutung verbinden', 'left' => $items(true, 'number'), 'right' => $meanings(true)], ['allowRepeat' => true], ['pairs' => array_map(static fn ($j) => ['leftId' => 'number-'.$j, 'rightId' => 'meaning-'.$j], range(1, 3))]);
        $blocks[] = $reveal($id.'-number-review', 'Проверяем числа', 'Zahlen gemeinsam prüfen', 'Пять ячменных хлебов и две рыбы — в начале. Двенадцать коробов — после насыщения. Чудо совершает Христос; люди участвуют в Его заботе.', 'Fünf Gerstenbrote und zwei Fische am Anfang. Zwölf Körbe nach der Sättigung. Christus wirkt das Wunder; Menschen wirken an seiner Sorge mit.', ['reviewBlockId' => $id.'-numbers']);
    } elseif ($n === 5) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'step-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 6, 4), range(0, 3));
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок истории', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Reihenfolge der Geschichte wiederherstellen', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['step-2', 'step-4', 'step-3', 'step-1']]);
        $review = static fn ($locale) => implode(' → ', array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2], [7, 9, 8, 6]));
        $blocks[] = $reveal($id.'-check', 'Проверяем вместе', 'Gemeinsam prüfen', $review('ru'), $review('de'), ['reviewBlockId' => $id.'-order']);
        $blocks[] = $free($id.'-numbers', 'Что означают числа 5, 2 и 12 в рассказе:', 'Was die Zahlen 5,2 und 12 in der Geschichte bedeuten:');
        $blocks[] = $free($id.'-participation', 'Что делает Христос и в чём участвуют люди:', 'Was Christus tut und woran Menschen mitwirken:');
    } elseif ($n === 6) {
        $options = static fn ($locale) => array_map(static fn ($c, $j) => ['optionId' => 'case-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 14, 6), range(0, 5));
        $blocks[] = $make($id.'-cases', 'multiple-choice', ['question' => 'В каких двух случаях можно начать?', 'options' => $options('ru')], ['question' => 'In welchen zwei Fällen kann man beginnen?', 'options' => $options('de')], ['allowRepeat' => true, 'minSelections' => 2, 'maxSelections' => 2], ['optionIds' => ['case-1', 'case-4']]);
        $blocks[] = $reveal($id.'-case-review', 'Обсуждаем условия', 'Bedingungen besprechen', 'Можно начать — 1 и 4. Нужно уточнить — 2 и 5. Нужно изменить — 3 и 6. Разрешение владельца, желание человека и выполнимое обещание важнее размера вклада.', 'Beginnen: 1 und 4. Nachfragen: 2 und 5. Ändern: 3 und 6. Erlaubnis, Wunsch und erfüllbares Versprechen zählen mehr als die Größe des Beitrags.', ['reviewBlockId' => $id.'-cases']);
        $blocks[] = $free($id.'-clarify', 'Какой уточняющий вопрос зададите в спорном случае?', 'Welche klärende Frage stellt ihr in einem strittigen Fall?');
    } elseif ($n === 7) {
        $options = static fn ($locale) => array_map(static fn ($c, $j) => ['optionId' => 'phrase-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 10, 4), range(0, 3));
        $blocks[] = $make($id.'-phrases', 'multiple-choice', ['question' => 'Выберите две полезные фразы. Неудачные переделайте в паре.', 'options' => $options('ru')], ['question' => 'Zwei hilfreiche Sätze wählen. Unpassende im Paar verbessern.', 'options' => $options('de')], ['allowRepeat' => true, 'minSelections' => 2, 'maxSelections' => 2], ['optionIds' => ['phrase-2', 'phrase-4']]);
        $blocks[] = $reveal($id.'-phrase-review', 'Проверяем предложение', 'Angebot gemeinsam prüfen', 'Полезны 2 и 4. Измените 1 и 3: назовите конкретное посильное дело и оставьте человеку возможность отказаться.', 'Sätze 2 und 4 helfen. Ändert 1 und 3: konkrete mögliche Handlung nennen und eine Ablehnung ermöglichen.', ['reviewBlockId' => $id.'-phrases']);
        $blocks[] = $free($id.'-offer', 'Какое действие я могу предложить:', 'Welche Handlung ich anbieten kann:');
        $blocks[] = $free($id.'-consent', 'Как я спрошу о согласии или нужде:', 'Wie ich nach Zustimmung oder Bedarf frage:');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-first-plan', 'Что у нас есть и что сначала спросим: Первый план — действие и исполнитель: Когда сделаем и чья помощь нужна:', 'Was haben wir, was fragen wir zuerst? Erster Plan: Handlung und Person. Wann tun wir es, wessen Hilfe ist nötig?');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Роман сказал: «У меня только десять минут. Я пока плохо понимаю длинные объяснения. Покажите, где повесить куртку. Можно я просто посижу рядом и порисую?» Сохраните первый план.'], ['text' => 'Roman sagt: „Ich habe nur zehn Minuten. Lange Erklärungen verstehe ich noch schlecht. Zeigt mir, wo ich meine Jacke aufhängen kann. Darf ich mich einfach dazusetzen und zeichnen?“ Ersten Plan behalten.'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план — что изменим после просьбы Романа:', 'Zweiter Plan — was sich nach Romans Bitte ändert:');
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-workshop', 'poll', ['question' => 'Что пробуем за две минуты?', 'options' => [['optionId' => 'card', 'text' => 'Открытка с рисунком'], ['optionId' => 'meeting', 'text' => 'Репетиция встречи']]], ['question' => 'Was probieren wir in zwei Minuten?', 'options' => [['optionId' => 'card', 'text' => 'Eine Bildkarte'], ['optionId' => 'meeting', 'text' => 'Begrüßung üben']]], ['allowRepeat' => true]);
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Выберите действие из второго плана. Сделайте открытку или разыграйте приветствие, вопрос и понятное предложение. Смените роли. Проверьте, осталось ли место для ответа новичка.'], ['text' => 'Handlung aus dem zweiten Plan wählen. Karte gestalten oder Gruß, Frage und verständliches Angebot spielen. Rollen tauschen. Prüfen, ob Platz für die Antwort blieb.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-feedback', 'Что уже получилось и как проверим, удобно ли человеку?', 'Was gelang bereits und wie prüfen wir, ob es für den Menschen passt?');
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-personal', 'prompt', ['text' => 'Выбери настоящий или вымышленный случай. Лист остаётся у тебя. Можно поделиться только своим следующим шагом.'], ['text' => 'Echten oder erfundenen Fall wählen. Blatt bleibt bei dir. Nur den nächsten Schritt teilen ist möglich.'], ['kind' => 'reflection', 'target' => 'class']);
        $blocks[] = $reveal($id.'-prayer', 'Молитва', 'Gebet', $raw['prayer']."\nАвторская молитва. Можно молча слушать.", $de['prayer']."\nEigens verfasstes Gebet. Still zuhören ist möglich.");
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-christ', 'В этом рассказе Христос…', 'In dieser Geschichte tut Christus…');
        $blocks[] = $free($id.'-step', 'Мой небольшой шаг…', 'Mein kleiner Schritt…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => $ru['title'], 'text' => 'Малое добро тоже имеет значение. Замечайте нужду, благодарите за полученное и делайте добро по силам.', 'source' => 'Ин. 6:1–15'], ['title' => $german['title'], 'text' => 'Auch kleine gute Taten zählen. Not bemerken, für das Erhaltene danken und nach den eigenen Möglichkeiten Gutes tun.', 'source' => 'Johannes 6,1–15'], array_replace($base, ['kind' => 'closing']));
    }
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $readingRu = in_array($n, [2, 3], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $extensionRu = $n === 1 ? "\n\nДополнение для устройств: выбор ресурса добровольный. Не обсуждайте результаты сейчас; можно просто выбрать мысленно, как в исходном плане." : '';
    $extensionDe = $n === 1 ? "\n\nErgänzung für Geräte: Die Ressourcenwahl ist freiwillig. Ergebnisse jetzt nicht besprechen; eine stille Wahl wie im ursprünglichen Plan genügt." : '';
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu).$extensionRu], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe.$extensionDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'azure'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'loaves-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => $file['locale']];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Die Sättigung mit fünf Broten und zwei Fischen verstehen; eine mögliche gute Tat wählen, auch wenn die eigenen Möglichkeiten klein erscheinen.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/loaves-handout-de.php');

return [
    'sourceRevision' => 'loaves-ru-de-2026-10-04-v1', 'materialId' => 'c100a421-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a421-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'u-menya-slishkom-malo',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Понять рассказ о насыщении народа пятью хлебами и двумя рыбами и выбрать посильное доброе дело, даже когда своих возможностей кажется мало.'], 'materials' => ['Ин. 6:1–15, презентация, шесть страниц раздатки, бумага, карандаши, иллюстрация пяти хлебов и двух рыб, один короб.', 'Шесть ролей, четыре события, четыре фразы, шесть случаев, четыре командных сведения, личный лист.'], 'devices' => 'Устройства необязательны. Короткие ответы по Enter; пары и команды работают с карточками и делают открытку или репетируют встречу.', 'conditions' => '12–15 лет. 45 минут. Все 15 стихов, сразу сценка, затем обсуждение. Сохранены чудо Христа и уход на гору. Не требуем отдавать последнее. Личный лист остаётся у подростка; молитва добровольна.'], 'de' => ['goals' => ['Die Sättigung mit fünf Broten und zwei Fischen verstehen und eine mögliche gute Tat wählen, auch wenn die eigenen Möglichkeiten klein erscheinen.'], 'materials' => ['Johannes 6,1–15, Präsentation, sechs Seiten Arbeitsmaterial, Papier, Stifte, Bild der fünf Brote und zwei Fische, ein Korb.', 'Sechs Rollen, vier Ereignisse, vier Sätze, sechs Fälle, vier Teaminformationen, persönliches Blatt.'], 'devices' => 'Geräte freiwillig. Kurze Antworten mit Enter; Paare und Teams nutzen Karten, gestalten eine Karte oder üben eine Begrüßung.', 'conditions' => '12–15 Jahre. 45 Minuten. Alle 15 Verse, sofort Rollenspiel, danach Besprechung. Christi Wunder und Rückzug erhalten. Das Letzte hergeben wird nicht verlangt. Persönliches Blatt bleibt privat; Gebet freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
