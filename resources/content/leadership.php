<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/leadership-source.json'), true, flags: JSON_THROW_ON_ERROR);
$version = 'd100a423-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$both = static fn ($ru, $de, $key = 'text') => ['ru' => [$key => $ru], 'de' => [$key => $de]];
$make = static fn ($id, $type, $content, $config = []) => ['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => $type === 'presentation' ? array_map(static fn ($c) => $c + ['modes' => []], $content) : $content, 'config' => $config];
$image = static fn ($n) => ['image' => ['assetId' => 'builtin-leadership-'.$n, 'versionId' => 'builtin-leadership-'.$n.'-v1']];
$free = static fn ($id, $ru, $de) => $make($id, 'free-response', ['ru' => ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], 'de' => ['question' => $de, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden']], ['allowRepeat' => true, 'maxLength' => 1000]);
$reveal = static fn ($id, $ru, $de, $textRu, $textDe, $extra = []) => $make($id, 'presentation', ['ru' => ['title' => $ru, 'label' => $ru, 'hideLabel' => 'Скрыть: '.$ru, 'text' => $textRu], 'de' => ['title' => $de, 'label' => $de, 'hideLabel' => 'Ausblenden: '.$de, 'text' => $textDe]], array_replace($base, ['kind' => 'reveal'], $extra));
$poll = static function ($id, $ru, $de, $optionsRu, $optionsDe) use ($make): array {
    $content = [];
    foreach (['ru' => $optionsRu, 'de' => $optionsDe] as $locale => $options) {
        $content[$locale] = ['question' => $locale === 'ru' ? $ru : $de, 'options' => array_map(static fn ($text, $i) => ['optionId' => 'choice-'.($i + 1), 'text' => $text], $options, array_keys($options))];
    }

    return $make($id, 'poll', $content, ['allowRepeat' => true]);
};
$leadOptions = ['ru' => ['А · Быстро решает', 'Б · Умеет слушать', 'В · Лучше всех умеет', 'Г · Берёт ответственность'], 'de' => ['A · Entscheidet schnell', 'B · Kann zuhören', 'C · Kann es am besten', 'D · Übernimmt Verantwortung']];
$stages = [];
foreach ($raw['ru']['stages'] as $i => $stage) {
    $n = $i + 1;
    $id = 'leadership-step-'.sprintf('%02d', $n);
    $scene = $notes = [];
    foreach (['ru', 'de'] as $locale) {
        $scene[$locale] = ['title' => $raw[$locale]['stages'][$i]['title'], 'text' => $locale === 'ru' ? implode("\n", array_slice($raw['ru']['slides'][$i], 0, -1)) : $raw['de']['slides'][$i]];
        $notes[$locale] = $raw[$locale]['stages'][$i]['notes']."\n\n".$raw[$locale]['preparation'];
        if ($n === 6) {
            $notes[$locale] .= "\n\n".$raw[$locale]['bible'];
        }
        // The surprise and roleplay situations stay private until their own stages.
        foreach ($raw['cards'] as $card) {
            if ($card['stage'] === $i) {
                $notes[$locale] .= "\n\n".$card[$locale]['title']."\n".$card[$locale]['text'];
            }
        }
    }
    $blocks = [$make($id.'-scene', 'presentation', $scene, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['ru' => ['alt' => $scene['ru']['title'], 'caption' => ''], 'de' => ['alt' => $scene['de']['title'], 'caption' => '']], ['fit' => 'contain']);
    $picture['media'] = $image($n);
    $blocks[] = $picture;
    if (in_array($n, [2, 12], true)) {
        $blocks[] = $poll($id.'-leader', 'Кому доверим команду?', 'Wem vertrauen wir das Team an?', $leadOptions['ru'], $leadOptions['de']);
        $blocks[] = $reveal($id.'-votes', 'Результаты голосования', 'Abstimmungsergebnis', 'Объясните разные ответы. Единственного правильного варианта нет.', 'Verschiedene Antworten begründen. Keine einzige zwingend richtige Wahl.', []);
    }
    if (in_array($n, [3, 4], true)) {
        $card = $raw['cards'][$n - 3];
        $blocks[] = $make($id.'-rules', 'prompt', $both($card['ru']['text'], $card['de']['text']), ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 5) {
        $blocks[] = $free($id.'-help', 'Общему делу помогло… Назовите конкретное действие.', 'Dem gemeinsamen Vorhaben half … Nenne eine konkrete Handlung.');
        $blocks[] = $reveal($id.'-answers', 'Примеры помощи', 'Beispiele der Hilfe', 'Обсуждаем действия, не сравниваем ценность людей.', 'Handlungen besprechen, keinen Wert von Menschen vergleichen.', ['kind' => 'response-board', 'sourceBlockIds' => [$id.'-help']]);
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-pair', 'prompt', $both('Одна минута в парах: что мог чувствовать Пётр? Почему трудно принять помощь? Предположения отличайте от слов текста. Личный пример необязателен.', 'Eine Minute zu zweit: Was könnte Petrus empfunden haben? Warum ist Hilfe anzunehmen schwer? Vermutungen von Textworten unterscheiden. Persönliche Beispiele freiwillig.'), ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $free($id.'-peter', 'Почему Петру нужно принять действие Христа?', 'Warum muss Petrus Christi Handlung annehmen?');
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-example', 'Как пример Христа связан с ответственностью руководителя?', 'Wie hängt Christi Beispiel mit der Verantwortung einer Leitung zusammen?');
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-instruction', 'prompt', $both('Команды получают по одной ситуации. За две минуты распределите роли, сыграйте начало 30–40 секунд и остановитесь перед решением. Можно быть рассказчиком или наблюдателем.', 'Jedes Team bekommt eine Situation. In zwei Minuten Rollen verteilen, 30–40 Sekunden Anfang spielen, vor der Entscheidung stoppen. Erzähler oder Beobachter sind möglich.'), ['kind' => 'instruction', 'target' => 'group']);
        foreach (range(2, 5) as $j) {
            $card = $raw['cards'][$j];
            $blocks[] = $reveal($id.'-situation-'.($j - 1), $card['ru']['title'], $card['de']['title'], $card['ru']['text'], $card['de']['text']);
        }
        $blocks[] = $poll($id.'-decision', 'Все хотят на фото. Что сделает руководитель?', 'Alle wollen aufs Foto. Was tut die Leitung?', ['Назначит самого тихого убирать', 'Молча уберёт всё за всех', 'Возьмёт часть работы и распределит посильные дела', 'Предложит свой вариант'], ['Teilt die stillste Person zum Aufräumen ein', 'Räumt schweigend alles allein auf', 'Übernimmt einen Anteil und verteilt machbare Aufgaben', 'Schlägt eine eigene Möglichkeit vor']);
        $blocks[] = $free($id.'-first-plan', 'Первая идея: одна фраза и одно действие руководителя', 'Erste Idee: ein Satz und eine Handlung der Leitung');
        $blocks[] = $reveal($id.'-votes', 'Обсуждаем выбор', 'Wahl besprechen', 'Как решение повлияет на остальных? Что можно изменить?', 'Wie wirkt die Entscheidung auf die anderen? Was lässt sich verändern?', []);
    } elseif ($n === 10) {
        $blocks[] = $make($id.'-replay', 'prompt', $both('Смените исполнителя роли руководителя. Каждая команда за 60 секунд переигрывает свою ситуацию с новой фразой и действием. Первый ответ не стирайте.', 'Eine andere Person übernimmt die Leitung. Jedes Team spielt seine Szene in 60 Sekunden mit einem neuen Satz und einer Handlung noch einmal. Erste Antwort behalten.'), ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Вторая идея: новая фраза, действие и то, что изменилось для остальных', 'Zweite Idee: neuer Satz, Handlung und Veränderung für die anderen');
    } elseif ($n === 11) {
        $blocks[] = $poll($id.'-boundary', '«Дай списать» / «Объясни, я не понял». Как ответить?', 'Lass mich abschreiben / Erklär es mir. Wie antwortest du?', ['А · Дать списать', 'Б · Объяснить и дать решить самому', 'В · Отказаться от любого участия', 'Г · Свой вариант'], ['A · Abschreiben lassen', 'B · Erklären und selbst lösen lassen', 'C · Jede Beteiligung ablehnen', 'D · Eigene Möglichkeit']);
        $blocks[] = $free($id.'-phrase', 'Как сказать доброжелательно и сохранить честную границу?', 'Wie antwortest du freundlich und wahrst eine ehrliche Grenze?');
        $blocks[] = $reveal($id.'-review', 'Помощь и границы', 'Hilfe und Grenzen', 'Списать не дам, но помогу разобраться. Служение не требует обмана или унижения. При опасности обратитесь к взрослому.', 'Abschreiben lasse ich dich nicht, aber ich helfe beim Verstehen. Dienen verlangt keinen Betrug und keine Erniedrigung. Bei Gefahr Erwachsene hinzuziehen.', []);
    } elseif ($n === 12) {
        $blocks[] = $make($id.'-private-plan', 'prompt', $both('На личной бумажной карточке: кому помогу, что сделаю, когда в ближайшие сутки. План остаётся у вас и не выводится на общий экран.', 'Auf deiner persönlichen Papierkarte: wem helfe ich, was tue ich, wann in den nächsten 24 Stunden? Der Plan bleibt bei dir und wird nicht öffentlich gezeigt.'), ['kind' => 'instruction', 'target' => 'class']);
        $blocks[] = $free($id.'-christ', 'Почему Христос умыл ноги ученикам?', 'Warum wusch Christus den Jüngern die Füße?');
        $blocks[] = $free($id.'-service', 'Какой поступок руководителя показывает служение?', 'Welche Handlung einer Leitung zeigt Dienen?');
        $blocks[] = $reveal($id.'-prayer', 'Добровольная молитва', 'Freiwilliges Gebet', 'Господи Иисусе Христе, научи нас замечать ближних и помогать им с любовью. Аминь.', 'Herr Jesus Christus, lehre uns, unsere Mitmenschen wahrzunehmen und ihnen mit Liebe zu helfen. Amen.');
        $blocks[] = $make($id.'-closing', 'presentation', ['ru' => ['title' => 'Продолжим пример Христа в жизни', 'text' => 'Ибо Я дал вам пример, чтобы и вы делали то же, что Я сделал вам. Ин. 13:15', 'label' => 'Завершить занятие', 'hideLabel' => 'Закрыть окно'], 'de' => ['title' => 'Christi Beispiel im Alltag fortsetzen', 'text' => 'Denn Ich habe euch ein Beispiel gegeben, damit auch ihr tut, was Ich euch getan habe. Johannes 13,15', 'label' => 'Treffen beenden', 'hideLabel' => 'Fenster schließen']], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $stage['title'], 'notes' => $notes['ru']], 'de' => ['title' => $raw['de']['stages'][$i]['title'], 'notes' => $notes['de']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $stage['minutes'] * 60, 'answerSeconds' => match ($n) {
        3 => 180, 4 => 90, 10 => 60, default => $stage['minutes'] * 60
    }, 'openTasks' => true, 'closeOnTimer' => in_array($n, [2, 9, 11, 12], true), 'theme' => 'cobalt'], 'blocks' => $blocks];
}
$plans = $details = [];
foreach (['ru', 'de'] as $locale) {
    $data = $raw[$locale];
    $plans[$locale] = ['plan' => $data['passport']."\n\n".$data['preparation']."\n\n".implode("\n\n", array_map(static fn ($stage) => $stage['title']."\n".$stage['notes'], $data['stages']))."\n\n".$data['bible']."\n\n".implode("\n\n", array_map(static fn ($card) => $card[$locale]['title']."\n".$card[$locale]['text'], $raw['cards']))];
    $details[$locale] = ['goals' => [$locale === 'ru' ? 'Увидеть любовь Христа и Его пример служения, принимать помощь и брать посильную ответственность.' : 'Christi Liebe und Sein Beispiel des Dienens erkennen, Hilfe annehmen und machbare Verantwortung übernehmen.'], 'materials' => [$locale === 'ru' ? 'По две книги, два листа А4, 30 см ленты и две одинаковые фишки на команду. Полотенце, пустая чаша, карточки и ручки.' : 'Pro Team zwei Bücher, zwei A4-Blätter, 30 cm Klebeband und zwei gleiche Spielsteine. Handtuch, leere Schüssel, Karten und Stifte.'], 'devices' => $locale === 'ru' ? 'Устройства необязательны. Мост, сценки и личный план выполняются без устройств.' : 'Geräte freiwillig. Brücke, Szenen und persönlicher Plan ohne Geräte.', 'conditions' => $locale === 'ru' ? '11–13 лет, 45 минут. Полное Ин. 13:1–17 читает ведущий. Личный план на бумаге, молитва добровольна.' : '11–13 Jahre, 45 Minuten. Leitung liest vollständiges Johannes 13,1–17. Persönlicher Plan auf Papier, Gebet freiwillig.'];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'leadership-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => $file['locale']];
    }
}

return [
    'sourceRevision' => 'leadership-ru-de-2026-10-04-v1', 'materialId' => 'c100a423-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a423-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'kto-samyi-glavnyi',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['ru']['title'], 'description' => $raw['ru']['description']], 'de' => ['title' => $raw['de']['title'], 'description' => $raw['de']['description']]], 'age' => ['11-14'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'], 'details' => $details],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['ru']['title']], 'de' => ['title' => $raw['de']['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => $plans, 'files' => $files]],
];
