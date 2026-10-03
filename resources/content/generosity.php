<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/generosity-source.json'), true, flags: JSON_THROW_ON_ERROR);
$version = 'd5f073a0-2156-48ea-8a32-014cad6c4253';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$plain = static fn ($text) => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $text));
$image = static fn (int $n) => ['image' => ['assetId' => 'builtin-generosity-'.$n, 'versionId' => 'builtin-generosity-'.$n.'-v1']];
$make = static fn ($id, $type, $ru, $de, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $de + ['modes' => []] : $de], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $de) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите ответ', 'submitLabel' => 'Отправить'], ['question' => $de, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe deine Antwort', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 1000]);
$reveal = static fn ($id, $ru, $de, $textRu, $textDe, $extra = []) => $make($id, 'presentation', ['title' => $ru, 'label' => $ru, 'hideLabel' => 'Скрыть: '.$ru, 'text' => $plain($textRu)], ['title' => $de, 'label' => $de, 'hideLabel' => 'Ausblenden: '.$de, 'text' => $plain($textDe)], array_replace($base, ['kind' => 'reveal'], $extra));
$cards = [];
foreach ([1, 3, 5, 7, 9, 11, 13, 14, 15, 16, 18, 20, 22, 24, 26, 28, 31, 33, 35, 37, 39, 41, 44, 46, 48, 50] as $cell) {
    $card = [];
    foreach (['ru', 'de'] as $locale) {
        $parts = explode("\n", implode("\n", array_filter($raw[$locale]['cards'][$cell])), 2);
        $card[$locale] = ['title' => $parts[0], 'text' => $parts[1]];
    }
    $cards[] = $card;
}
$cardText = static fn ($index, $locale) => $cards[$index][$locale]['title']."\n".$cards[$index][$locale]['text'];
$readings = $frames = $prayer = [];
foreach (['ru', 'de'] as $locale) {
    $sections = preg_split('/(?m)^## /', $raw[$locale]['script']);
    $frames[$locale] = [];
    preg_match_all('/^### ([^\n]+)\n\n(.*?)(?=\n### |\z)/ms', trim($sections[13]), $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $frames[$locale][] = ['title' => $match[1], 'text' => trim($match[2])];
    }
    foreach ([14, 15] as $section) {
        preg_match_all('/^(\d+)\. (.*?)(?=\n\n\d+\. |\n\nhttps:|\z)/ms', $sections[$section], $verses, PREG_SET_ORDER);
        $readings[$locale][] = array_column(array_map(static fn ($v) => ['number' => (int) $v[1], 'text' => $v[1].'. '.trim($v[2])], $verses), 'text', 'number');
    }
    $prayer[$locale] = trim(explode("\n", $sections[16], 2)[1]);
}
$noteField = static function ($index, $locale, $field) use ($raw): string {
    preg_match('/\*\*'.preg_quote($field, '/').':\*\* (.*?)(?=\n\n|\z)/s', $raw[$locale]['stages'][$index]['notes'], $m);

    return $m[1];
};
$stages = [];
foreach ($raw['ru']['stages'] as $i => $stage) {
    $n = $i + 1;
    $id = 'generosity-step-'.sprintf('%02d', $n);
    $scene = $notes = [];
    foreach (['ru', 'de'] as $locale) {
        $lines = $raw[$locale]['slides'][$i]['text'];
        $title = $n === 1 ? implode(' ', array_splice($lines, 0, 3)) : array_shift($lines);
        if ($n !== 1) {
            array_pop($lines);
        }
        $scene[$locale] = ['title' => $title, 'text' => implode("\n", $lines)];
        $notes[$locale] = $plain($raw[$locale]['stages'][$i]['notes']."\n\n".$raw[$locale]['preparation']);
        if (in_array($n, [2, 3], true)) {
            $notes[$locale] .= "\n\n".implode("\n\n", array_map(static fn ($f) => $f['title']."\n".$f['text'], $frames[$locale]))."\n\n".implode("\n\n", array_merge(...array_values($readings[$locale])));
        }
        $range = match ($n) {
            3 => range(0, 5), 5 => range(6, 9), 6 => range(10, 15), 8 => range(16, 25), default => []
        };
        $notes[$locale] .= "\n\n".implode("\n\n", array_map(static fn ($j) => $cardText($j, $locale), $range));
    }
    $blocks = [$make($id.'-scene', 'presentation', $scene['ru'], $scene['de'], $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $scene['ru']['title'], 'caption' => ''], ['alt' => $scene['de']['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($raw['ru']['slides'][$i]['image']);
    $blocks[] = $picture;
    if ($n === 2) {
        foreach ([[0, 15, 18], [0, 19, 21], [1, 16, 21], [1, 22, 26]] as $part => [$book, $from, $to]) {
            $text = $labels = [];
            foreach (['ru', 'de'] as $locale) {
                $labels[$locale] = ($locale === 'ru' ? ['Лк. 12:', 'Мф. 19:'] : ['Lk 12,', 'Mt 19,'])[$book].$from.'–'.$to;
                $text[$locale] = $labels[$locale]."\n".implode("\n\n", array_intersect_key($readings[$locale][$book], array_flip(range($from, $to))));
            }
            $blocks[] = $reveal($id.'-reading-'.($part + 1), $labels['ru'], $labels['de'], $text['ru'], $text['de']);
        }
    } elseif ($n === 3) {
        $roles = static fn ($locale) => array_map(static fn ($j) => ['roleId' => 'role-'.($j + 1), 'text' => $cards[$j][$locale]['title']], range(0, 5));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Шесть ролей для двух сценок', 'roles' => $roles('ru')], ['text' => 'Sechs Rollen für beide Szenen', 'roles' => $roles('de')], ['capacities' => array_fill_keys(array_map(static fn ($j) => 'role-'.$j, range(1, 6)), 1)]);
        foreach ($frames['ru'] as $j => $frame) {
            $block = $reveal($id.'-frame-'.($j + 1), $frame['title'], $frames['de'][$j]['title'], $frame['text'], $frames['de'][$j]['text']);
            $block['media'] = $image([2, 4, 3, 3, 5, 6][$j]);
            $blocks[] = $block;
        }
    } elseif ($n === 4) {
        $blocks[] = $free($id.'-farmer', 'Богач: на что надеется и что трудно отпустить?', 'Der Bauer: Worauf hofft er und was kann er schwer loslassen?');
        $blocks[] = $free($id.'-young-man', 'Юноша: на что надеется и что трудно отпустить?', 'Der junge Mann: Worauf hofft er und was kann er schwer loslassen?');
        $blocks[] = $reveal($id.'-compare', 'Обсуждаем обе истории', 'Beide Geschichten besprechen', $noteField($i, 'ru', 'Ориентир'), $noteField($i, 'de', 'Hinweis'));
    } elseif ($n === 5) {
        $items = static fn ($locale) => array_map(static fn ($j) => ['itemId' => 'event-'.($j - 5), 'text' => $cardText($j, $locale)], range(6, 9));
        $slots = static fn ($locale) => array_map(static fn ($text, $j) => ['itemId' => 'slot-'.($j + 1), 'text' => $text], $locale === 'ru' ? ['А · Начало притчи', 'А · Завершение притчи', 'Б · Начало встречи', 'Б · Завершение встречи'] : ['A · Anfang des Gleichnisses', 'A · Abschluss des Gleichnisses', 'B · Anfang der Begegnung', 'B · Abschluss der Begegnung'], range(0, 3));
        $blocks[] = $make($id.'-stories', 'matching', ['question' => 'Распределите четыре карточки: две отдельные истории, начало и завершение каждой', 'left' => $items('ru'), 'right' => $slots('ru')], ['question' => 'Vier Karten ordnen: zwei getrennte Geschichten, jeweils Anfang und Abschluss', 'left' => $items('de'), 'right' => $slots('de')], ['allowRepeat' => true], ['pairs' => array_map(static fn ($j, $slot) => ['leftId' => 'event-'.$j, 'rightId' => 'slot-'.$slot], [1, 2, 3, 4], [4, 1, 3, 2])]);
        $blocks[] = $reveal($id.'-story-review', 'Проверяем обе истории', 'Beide Geschichten prüfen', "А: 2 → 4. Б: 3 → 1.\n".$noteField($i, 'ru', 'Ориентир'), "A: 2 → 4. B: 3 → 1.\n".$noteField($i, 'de', 'Hinweis'), ['reviewBlockId' => $id.'-stories']);
        $blocks[] = $free($id.'-hope', 'Общая трудность героев и надежда из финала встречи', 'Gemeinsame Schwierigkeit und Hoffnung am Ende der Begegnung');
    } elseif ($n === 6) {
        $options = static fn ($locale) => array_map(static fn ($j) => ['optionId' => 'case-'.($j - 9), 'text' => $cardText($j, $locale)], range(10, 15));
        $blocks[] = $make($id.'-cases', 'multiple-choice', ['question' => 'В каких двух случаях можно действовать?', 'options' => $options('ru')], ['question' => 'In welchen zwei Fällen kann man handeln?', 'options' => $options('de')], ['allowRepeat' => true, 'minSelections' => 2, 'maxSelections' => 2], ['optionIds' => ['case-1', 'case-4']]);
        $blocks[] = $reveal($id.'-case-review', 'Обсуждаем условия', 'Bedingungen besprechen', 'Можно действовать — 1 и 4; сначала уточнить — 2 и 5; нужно изменить — 3 и 6. '.$noteField($i, 'ru', 'Ориентир'), 'Handeln möglich: 1 und 4; zuerst klären: 2 und 5; Angebot ändern: 3 und 6. '.$noteField($i, 'de', 'Hinweis'), ['reviewBlockId' => $id.'-cases']);
        $blocks[] = $free($id.'-condition', 'Какое условие решает спорный случай?', 'Welche Bedingung entscheidet im strittigen Fall?');
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => $noteField($i, 'ru', 'Как провести')], ['text' => $noteField($i, 'de', 'Durchführung')], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-offer', 'Два варианта помощи и договорённость о возврате, если это заём', 'Zwei Möglichkeiten zu helfen und bei einer Leihgabe eine Rückgabeabsprache');
    } elseif ($n === 8) {
        $options = static fn ($locale) => array_map(static fn ($j) => ['optionId' => 'budget-'.($j - 15), 'text' => $cardText($j, $locale)], range(16, 21));
        $blocks[] = $make($id.'-budget', 'multiple-choice', ['question' => 'Выберите карточки своего плана', 'options' => $options('ru')], ['question' => 'Wählt die Karten für euren Plan', 'options' => $options('de')], ['allowRepeat' => true, 'minSelections' => 3, 'maxSelections' => 5]);
        $blocks[] = $free($id.'-first-plan', 'Первый план: расходы, остаток и кто играет с гостями', 'Erster Plan: Ausgaben, Rest und wer die Gäste begleitet');
        $blocks[] = $reveal($id.'-budget-check', 'Проверяем свой бюджет', 'Unser Budget prüfen', 'Всего 12. Дорога 3 и участие 0 обязательны. Новая игра 7 или ремонт 2 — одно из двух. Украшения 3 и призы 5 по желанию. Каждая покупка один раз. Остаток допустим. Сравните сумму со своим планом.', 'Insgesamt 12. Heimfahrt 3 und Begleitung 0 gehören dazu. Neues Spiel 7 oder Reparatur 2 — eines von beiden. Dekoration 3 und Preise 5 sind freiwillig. Jeder Kauf einmal. Ein Rest darf bleiben. Vergleicht die Summe mit eurem Plan.');
        $blocks[array_key_last($blocks)]['media'] = $image(10);
    } elseif ($n === 9) {
        $blocks[] = $free($id.'-second-plan', 'Второй план: ответ дарителю, расходы без неподтверждённых средств и проведение вечера', 'Zweiter Plan: Antwort, Ausgaben ohne unbestätigte Mittel und Durchführung');
        $blocks[] = $reveal($id.'-review', 'Сравниваем планы', 'Pläne vergleichen', $noteField($i, 'ru', 'Ориентир'), $noteField($i, 'de', 'Hinweis'));
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-dialogue', 'prompt', ['text' => $noteField($i, 'ru', 'Как провести')], ['text' => $noteField($i, 'de', 'Durchführung')], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-reply', 'Ваш ответ дарителю в двух предложениях', 'Eure Antwort an den Unterstützer in zwei Sätzen');
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-private', 'prompt', ['text' => 'Лист остаётся у тебя. Можно выбрать вымышленный случай. Не нужно записывать стоимость вещей или достаток семьи.'], ['text' => 'Das Blatt bleibt bei dir. Ein erfundenes Beispiel ist möglich. Preise deiner Sachen oder das Einkommen deiner Familie brauchst du nicht aufzuschreiben.'], ['kind' => 'reflection', 'target' => 'class']);
        $blocks[] = $reveal($id.'-prayer', 'Молитва', 'Gebet', $prayer['ru']."\nМожно молча слушать.", $prayer['de']."\nStilles Zuhören ist möglich.");
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-surprise', 'В евангельских рассказах меня удивило…', 'In den Geschichten aus dem Evangelium hat mich überrascht …');
        $blocks[] = $free($id.'-step', 'На этой неделе я могу поделиться…', 'Diese Woche kann ich … teilen.');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => $scene['ru']['title'], 'text' => 'Вещи служат человеку, а щедрость помогает откликнуться на Бога и ближнего.', 'source' => 'Лк. 12:15–21; Мф. 19:16–26'], ['title' => $scene['de']['title'], 'text' => 'Dinge dienen Menschen. Großzügigkeit hilft uns, auf Gott und den Nächsten zu antworten.', 'source' => 'Lk 12,15–21; Mt 19,16–26'], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $stage['title'], 'notes' => $notes['ru']], 'de' => ['title' => $raw['de']['stages'][$i]['title'], 'notes' => $notes['de']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $stage['minutes'] * 60, 'openTasks' => true, 'theme' => 'ochre'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'generosity-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => $file['locale']];
    }
}
$plans = [];
foreach (['ru', 'de'] as $locale) {
    $plans[$locale] = ['plan' => implode("\n\n", array_map(static fn ($key) => $raw[$locale][$key], ['passport', 'plan', 'script', 'preparation', 'handout']))];
}

return [
    'sourceRevision' => 'generosity-ru-de-2026-10-04-v1', 'materialId' => 'c5f073a0-2156-48ea-8a32-014cad6c4253', 'versionId' => $version, 'ownerKey' => 'e5f073a0-2156-48ea-8a32-014cad6c4253', 'slug' => 'chto-vazhnee-imet-ili-delitsya',
    'metadata' => ['translations' => ['ru' => ['title' => 'Что важнее: иметь или делиться?', 'description' => trim($raw['ru']['description'])], 'de' => ['title' => 'Was ist wichtiger: besitzen oder teilen?', 'description' => trim($raw['de']['description'])]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'], 'details' => ['ru' => ['goals' => ['Понять, как привязанность к богатству мешает видеть Бога и ближнего, и выбрать посильный способ делиться с уважением к человеку.'], 'materials' => ['Полные Лк. 12:15–21 и Мф. 19:16–26, 12 слайдов, шесть страниц раздатки, бумага, карандаши и 12 счётных предметов на команду.', 'Шесть ролей, четыре события, шесть случаев, шесть бюджетных карточек и четыре раздельных сведения.'], 'devices' => 'Устройства необязательны. Пары и команды работают с бумажными карточками и жетонами; короткие ответы можно отправить Enter.', 'conditions' => '12–15 лет, 45 минут. Оба текста целиком, сразу две сценки, затем обсуждение. Личный лист остаётся у подростка; молитва добровольна.'], 'de' => ['goals' => ['Verstehen, wie die Bindung an Besitz den Blick für Gott und den Nächsten verstellt, und eine mögliche Form des Teilens wählen, die den anderen achtet.'], 'materials' => ['Lk 12,15–21 und Mt 19,16–26 vollständig, 12 Folien, sechs Arbeitsblätter, Papier, Stifte und 12 Zählgegenstände pro Team.', 'Sechs Rollen, vier Ereignisse, sechs Fälle, sechs Budgetkarten und vier getrennte Hinweise.'], 'devices' => 'Geräte freiwillig. Paare und Teams nutzen Karten und Spielmarken; kurze Antworten können mit Enter gesendet werden.', 'conditions' => '12–15 Jahre, 45 Minuten. Beide Texte vollständig, sofort beide Szenen, danach Gespräch. Persönliches Blatt bleibt privat; Gebet freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => 'Что важнее: иметь или делиться?'], 'de' => ['title' => 'Was ist wichtiger: besitzen oder teilen?']], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => $plans, 'files' => $files]],
];
