<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/listening-source.json'), true, flags: JSON_THROW_ON_ERROR);
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$both = static fn ($ru, $de, $key = 'text') => ['ru' => [$key => $ru], 'de' => [$key => $de]];
$make = static function ($id, $type, $content, $config = [], $solution = null): array {
    if ($type === 'presentation') {
        $content = array_map(static fn ($c) => $c + ['modes' => []], $content);
    }
    $block = ['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => $content, 'config' => $config];
    if ($solution !== null) {
        $block['solution'] = $solution;
    }

    return $block;
};
$image = static fn ($n) => ['image' => ['assetId' => 'builtin-listening-'.$n, 'versionId' => 'builtin-listening-'.$n.'-v1']];
$prompt = static fn ($id, $ru, $de, $target = 'class') => $make($id, 'prompt', $both($ru, $de), ['kind' => 'instruction', 'target' => $target]);
$free = static fn ($id, $ru, $de) => $make($id, 'free-response', ['ru' => ['question' => $ru, 'label' => 'Ответ о вымышленных героях', 'placeholder' => 'Личные сведения не нужны', 'submitLabel' => 'Отправить'], 'de' => ['question' => $de, 'label' => 'Antwort über die erfundenen Figuren', 'placeholder' => 'Keine persönlichen Angaben', 'submitLabel' => 'Senden']], ['allowRepeat' => true, 'maxLength' => 1000]);
$reveal = static fn ($id, $ru, $de, $textRu, $textDe) => $make($id, 'presentation', ['ru' => ['title' => $ru, 'label' => $ru, 'hideLabel' => 'Скрыть: '.$ru, 'text' => $textRu], 'de' => ['title' => $de, 'label' => $de, 'hideLabel' => 'Ausblenden: '.$de, 'text' => $textDe]], array_replace($base, ['kind' => 'reveal']));
$choice = static function ($id, $ru, $de, $optionsRu, $optionsDe, $solution = null) use ($make): array {
    $content = [];
    foreach (['ru' => $optionsRu, 'de' => $optionsDe] as $locale => $options) {
        $content[$locale] = ['question' => $locale === 'ru' ? $ru : $de, 'options' => array_map(static fn ($text, $i) => ['optionId' => 'choice-'.($i + 1), 'text' => $text], $options, array_keys($options))];
    }

    return $make($id, $solution === null ? 'poll' : 'single-choice', $content, ['allowRepeat' => true], $solution === null ? null : ['optionId' => 'choice-'.$solution]);
};
$cards = [
    ['Михаил смотрит в телефон, когда Анна начинает говорить.', 'Michael schaut auf sein Handy, als Anna zu sprechen beginnt.', 1],
    ['Михаилу совершенно неинтересно, что чувствует Анна.', 'Es interessiert Michael überhaupt nicht, wie Anna sich fühlt.', 2],
    ['Анна говорит: «С тобой невозможно поговорить».', 'Anna sagt: „Mit dir kann man einfach nicht reden.“', 1],
    ['Анна хочет испортить Михаилу весь вечер.', 'Anna möchte Michael den ganzen Abend verderben.', 2],
];
$readingContext = static fn ($locale) => ($locale === 'ru' ? 'Контекст Иак. 1:21–22: слушание связано с принятием Божьего слова и его исполнением. Разговорные упражнения — применение, не полный смысл наставления. Это пояснение ведущему, не дополнительная цитата в выбранном чтении.' : 'Kontext Jakobus 1,21–22: Hören hängt mit dem Annehmen und Tun des Wortes Gottes zusammen. Gesprächsübungen sind eine Anwendung, nicht die ganze Bedeutung der Weisung. Hinweis für die Leitung, kein zusätzliches Zitat innerhalb der gewählten Lesung.');
$lessons = [];
foreach (['general', 'couples'] as $variant) {
    $couples = $variant === 'couples';
    $prefix = 'listening-'.$variant;
    $key = $couples ? '5' : '4';
    $version = 'd100a42'.$key.'-3038-4092-9d8b-e72a0a84bf41';
    $stages = [];
    foreach ($raw['ru']['variants'][$variant]['stages'] as $i => $stage) {
        $n = $i + 1;
        $id = $prefix.'-step-'.sprintf('%02d', $n);
        $scene = $notes = [];
        foreach (['ru', 'de'] as $locale) {
            $data = $raw[$locale]['variants'][$variant]['stages'][$i];
            $slide = array_values(array_filter(array_slice($data['slide'], 2, -1), static fn ($line) => $line !== ''));
            $scene[$locale] = ['title' => $data['slide'][1], 'text' => implode("\n", $slide)];
            $notes[$locale] = $data['notes']."\n\n".$raw[$locale]['Teacher'];
            if ($n === 4) {
                $notes[$locale] .= "\n\n".$raw[$locale]['Bible']."\n\n".$readingContext($locale);
            }
        }
        $blocks = [$make($id.'-scene', 'presentation', $scene, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
        $picture = $make($id.'-image', 'image', ['ru' => ['alt' => $scene['ru']['title'], 'caption' => ''], 'de' => ['alt' => $scene['de']['title'], 'caption' => '']], ['fit' => 'contain']);
        $picture['media'] = $image($n);
        $blocks[] = $picture;
        if ($n === 1) {
            $blocks[] = $choice($id.'-barrier', 'Что чаще мешает мне слушать?', 'Was erschwert mir das Zuhören?', ['Спешка', 'Готовый ответ', 'Усталость', 'Догадки'], ['Zeitdruck', 'Fertige Antwort', 'Müdigkeit', 'Vermutungen']);
            $blocks[] = $prompt($id.'-goal', 'Выберите одну собственную цель. Личные истории необязательны. Не сравниваем, кто слушает хуже.', 'Ein eigenes Ziel wählen. Persönliche Geschichten sind freiwillig. Nicht vergleichen, wer schlechter zuhört.', $couples ? 'pair' : 'class');
        } elseif ($n === 2) {
            $blocks[] = $prompt($id.'-roles', 'Вымышленная сценка. Анна: «Ты опять в телефоне». Михаил: «Я вообще-то делом занят». Анна: «С тобой невозможно поговорить». Михаил: «А ты можешь без претензий?» Пока читаем только реплики. Обстоятельства узнаем позже.', 'Erfundene Szene. Anna: „Du bist schon wieder am Handy.“ Michael: „Ich erledige gerade etwas.“ Anna: „Mit dir kann man einfach nicht reden.“ Michael: „Geht es auch ohne Vorwürfe?“ Zunächst nur die Sätze lesen. Hintergründe erfahren wir später.', $couples ? 'pair' : 'class');
            $blocks[] = $free($id.'-heard', 'Добровольно, только о героях: точная реплика / моя догадка о её смысле. Это разные вещи.', 'Freiwillig, nur über die Figuren: genauer Satz / meine Vermutung über seine Bedeutung. Beides trennen.');
        } elseif ($n === 3) {
            $blocks[] = $choice($id.'-reply', '«Я сегодня совсем вымоталась». Какой ответ помогает понять просьбу?', '„Ich bin völlig erschöpft.“ Welche Antwort hilft, das Anliegen zu verstehen?', ['А · Тогда просто ложись раньше', 'Б · А я, по-твоему, не устал?', 'В · Тебе сейчас хочется, чтобы я послушал, или нужна помощь?'], ['A · Dann geh doch früher schlafen', 'B · Glaubst du, ich bin nicht müde?', 'C · Soll ich dir gerade zuhören, oder brauchst du Hilfe?']);
            $blocks[] = $reveal($id.'-review', 'Разбор ответов', 'Antworten besprechen', 'В помогает уточнить просьбу. А может быть полезным советом, но мы ещё не знаем, нужен ли он. Б переводит разговор в соревнование. Придумайте открытый вопрос.', 'C ermöglicht Klärung. A könnte hilfreicher Rat sein, doch der Bedarf ist noch unklar. B macht daraus einen Wettbewerb. Eine offene Frage finden.');
        } elseif ($n === 4) {
            $blocks[] = $prompt($id.'-reading', 'Ведущий читает полностью Иак. 1:19–20 и Еф. 4:25–32 из своего плана. До конца чтения не обсуждаем. Выберите стих и конкретное действие для себя. Стих не применяем к недостаткам другого.', 'Die Leitung liest Jakobus 1,19–20 und Epheser 4,25–32 vollständig aus ihrem Plan. Danach einen Vers und eine eigene Handlung wählen. Den Vers nicht auf Fehler des Gegenübers anwenden.', 'pair');
        } elseif ($n === 5) {
            foreach ($cards as $j => $card) {
                $blocks[] = $choice($id.'-fact-'.($j + 1), $card[0], $card[1], ['Наблюдение', 'Предположение'], ['Beobachtung', 'Vermutung'], $card[2]);
            }
            $blocks[] = $reveal($id.'-categories', 'Сверяем категории', 'Zuordnung besprechen', '1 и 3 — наблюдения. 2 и 4 — предположения о мотивах. Объясните выбор.', '1 und 3 sind Beobachtungen. 2 und 4 sind Deutungen der Absicht. Wahl begründen.');
            $blocks[] = $reveal($id.'-background', 'Что осталось за кадром', 'Was wir noch nicht wissen', 'Анна получила тревожное сообщение от сестры и хотела поговорить. Михаил пытался отправить документ до 20:00; нужно ещё три минуты. Оба этого не объяснили. Это обстоятельства вымышленной истории. Они объясняют напряжение, но не оправдывают резкость.', 'Anna hat eine beunruhigende Nachricht ihrer Schwester erhalten und möchte reden. Michael versucht, vor 20 Uhr ein Dokument abzuschicken; er braucht noch drei Minuten. Beide haben das nicht erklärt. Dies sind Hintergründe der erfundenen Geschichte. Sie erklären die Anspannung, rechtfertigen keine scharfen Worte.');
            $blocks[] = $free($id.'-question', 'Добровольно: уточняющий вопрос Анне или Михаилу. Только учебный пример.', 'Freiwillig: eine klärende Frage an Anna oder Michael. Nur das Übungsbeispiel.');
        } elseif ($n === 6) {
            $blocks[] = $prompt($id.'-listen', $couples ? 'Выберите небольшую тему, которую оба готовы обсуждать, или вымышленных героев. 2 минуты — говорящий; 1 минута — пересказ и вопрос; 30 секунд — уточнение; 30 секунд — подтверждение. Затем смена ролей и второй круг. Содержание разговора остаётся у пары. Советы — только по просьбе.' : 'Выберите небольшой случай или вымышленных героев. 2 минуты — говорящий; 1 минута — пересказ и вопрос; 30 секунд — уточнение. Затем смена ролей и второй круг. Содержание разговора остаётся у пары. Советы — только по просьбе.', $couples ? 'Ein kleines Thema wählen, das beide besprechen möchten, oder bei den Figuren bleiben. 2 Minuten erzählen; 1 Minute wiedergeben und fragen; 30 Sekunden klären; 30 Sekunden bestätigen. Dann Rollenwechsel und zweite Runde. Gespräch bleibt beim Paar. Ratschläge nur auf Wunsch.' : 'Einen kleinen Fall oder die Figuren wählen. 2 Minuten erzählen; 1 Minute wiedergeben und fragen; 30 Sekunden klären. Dann Rollenwechsel und zweite Runde. Gespräch bleibt beim Paar. Ratschläge nur auf Wunsch.', 'pair');
        } elseif ($n === 7) {
            $blocks[] = $prompt($id.'-replay', $couples ? 'Сначала перепишите реплику героев без обвинения. Разыграйте сцену вдвоём, поменяйтесь ролями и попробуйте вариант, когда ждать невозможно. Проверьте: пересказ, вопрос, конкретная просьба. Личные разговоры не отправляйте.' : 'Анна, Михаил, наблюдатель: 3 минуты на новый разговор, 2 минуты на сценку. Наблюдатель проверяет пересказ, вопрос и конкретную просьбу. Учитывайте известные обстоятельства. Не приписывайте согласие собеседнику.', $couples ? 'Einen Satz der Figuren ohne Vorwurf umschreiben. Zu zweit spielen, Rollen wechseln und eine Variante ohne Wartezeit versuchen. Wiedergeben, Rückfrage und konkrete Bitte prüfen. Keine persönlichen Gespräche senden.' : 'Anna, Michael, Beobachter: 3 Minuten für den neuen Dialog, 2 Minuten spielen. Beobachter prüft Wiedergeben, Rückfrage und konkrete Bitte. Bekannte Hintergründe berücksichtigen. Zustimmung nicht vorwegnehmen.', $couples ? 'pair' : 'group');
            $blocks[] = $free($id.'-new-dialog', 'Добровольно, только о героях: новая реплика / вопрос / выполнимая договорённость. Не отправляйте личный разговор.', 'Freiwillig, nur über die Figuren: neuer Satz / Rückfrage / machbare Absprache. Kein persönliches Gespräch senden.');
        } elseif ($n === 8) {
            $blocks[] = $prompt($id.'-pause', 'Сравните «Всё, отстань!» и «Я начинаю говорить резко. Мне нужно десять минут. Давай продолжим в 20:30?» На бумаге: причина паузы, просьба и предложение вернуться. Другой может предложить иное время. Затем смена ролей. Не требуется доводить изматывающий спор до конца любой ценой.', 'Vergleicht „Lass mich endlich in Ruhe!“ und „Ich werde gerade scharf. Ich brauche zehn Minuten. Sprechen wir um 20:30 Uhr weiter?“ Auf Papier: Grund, Bitte und Rückkehr. Das Gegenüber darf eine andere Zeit vorschlagen. Dann Rollenwechsel. Keinen erschöpfenden Streit um jeden Preis fortsetzen.', 'pair');
        } elseif ($n === 9) {
            $blocks[] = $prompt($id.'-private-plan', $couples ? 'На общем бумажном листе супругов: тема, когда и где, первая фраза, как проверим понимание, кто предложит новое время при изменении плана. Договорённость должна подходить обоим. Содержание не собирается и не выводится на экран.' : 'На личной бумаге: с кем поговорю, когда и где, что хочу понять, первая фраза, как проверю понимание. Лист остаётся у вас. Не записываем обещания за другого человека.', $couples ? 'Auf dem gemeinsamen Papierblatt: Thema, wann und wo, erster Satz, Verständnis prüfen, wer bei Änderungen eine neue Zeit vorschlägt. Absprache muss beiden passen. Inhalt wird nicht eingesammelt und nicht angezeigt.' : 'Auf persönlichem Papier: mit wem, wann und wo, was möchte ich verstehen, erster Satz, Verständnis prüfen. Blatt bleibt bei dir. Keine Zusagen für jemand anderen.', $couples ? 'pair' : 'class');
        } else {
            if ($couples) {
                $blocks[] = $prompt($id.'-exit', 'Ответьте друг другу на фразу экрана и поблагодарите за одно конкретное действие. Личные ответы остаются у супругов. На неделе попробуйте договорённость и обсудите, что помогло.', 'Antwortet einander auf den Satz am Bildschirm und dankt für eine konkrete Handlung. Persönliche Antworten bleiben beim Paar. Absprache diese Woche versuchen und besprechen, was half.', 'pair');
            } else {
                $blocks[] = $free($id.'-exit', '«Ты меня совсем не слушаешь». Каким будет новый ответ вымышленного героя?', '„Du hörst mir überhaupt nicht zu.“ Wie antwortet die erfundene Figur jetzt?');
            }
            $blocks[] = $prompt($id.'-skill', 'Удалось ли в упражнении получить подтверждение точного пересказа? Поднимите руку или обсудите вдвоём. Проверяем навык, не сравниваем людей.', 'Wurde das Wiedergeben in der Übung bestätigt? Handzeichen oder zu zweit besprechen. Die Übung prüfen, nicht Menschen vergleichen.', $couples ? 'pair' : 'class');
            $blocks[] = $reveal($id.'-prayer', 'Добровольная молитва', 'Freiwilliges Gebet', 'Господи, помоги нам слышать друг друга, удерживать обидные слова и говорить правду с любовью. Дай нам терпение исправлять свои ошибки и заботиться о ближнем. Аминь.', 'Herr, hilf uns, einander zuzuhören, verletzende Worte zurückzuhalten und die Wahrheit in Liebe zu sagen. Gib uns Geduld, eigene Fehler zu korrigieren und für unseren Nächsten zu sorgen. Amen.');
            $blocks[] = $make($id.'-closing', 'presentation', ['ru' => ['title' => 'Продолжим слушать в жизни', 'text' => 'Один разговор на неделе. Учимся слышать, говорить правду с любовью и исправлять свои слова.', 'label' => 'Завершить занятие', 'hideLabel' => 'Закрыть окно'], 'de' => ['title' => 'Im Alltag weiter zuhören', 'text' => 'Ein Gespräch in dieser Woche. Zuhören, wahrhaftig und liebevoll sprechen, eigene Worte korrigieren.', 'label' => 'Treffen beenden', 'hideLabel' => 'Fenster schließen']], array_replace($base, ['kind' => 'closing']));
        }
        $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $stage['title'], 'notes' => $notes['ru']], 'de' => ['title' => $raw['de']['variants'][$variant]['stages'][$i]['title'], 'notes' => $notes['de']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $stage['minutes'] * 60, 'answerSeconds' => match ($n) {
            1 => 30, 5 => 120, 6 => 120, default => $stage['minutes'] * 60
        }, 'openTasks' => true, 'closeOnTimer' => true, 'theme' => 'slate'], 'blocks' => $blocks];
    }
    $translations = $plans = $details = [];
    foreach (['ru', 'de'] as $locale) {
        $ru = $locale === 'ru';
        $title = $ru ? 'Почему мы перестаём слышать друг друга?' : 'Warum hören wir einander nicht mehr zu?';
        if ($couples) {
            $title .= $ru ? ' — для супругов' : ' — für Ehepaare';
        }
        $translations[$locale] = ['title' => $title, 'description' => ($ru ? 'Для взрослых от 18 лет. ' : 'Für Erwachsene ab 18 Jahren. ').($couples ? ($ru ? 'Практическое занятие для одной или нескольких супружеских пар, 75 минут. ' : 'Praktischer Abend für ein oder mehrere Ehepaare, 75 Minuten. ') : ($ru ? 'Общий урок для 4–24 участников, 60 минут. ' : 'Allgemeiner Unterricht für 4–24 Personen, 60 Minuten. ')).trim($raw[$locale]['website'])];
        $plans[$locale] = ['plan' => $raw[$locale]['Profile']."\n\n".$raw[$locale]['Plan']."\n\n".$raw[$locale]['variants'][$variant]['scenario']."\n\n".$raw[$locale]['Teacher']."\n\n".$raw[$locale]['Bible']."\n\n".$raw[$locale]['handout']."\n\n".$readingContext($locale)];
        $details[$locale] = ['goals' => [$ru ? 'Отличать наблюдение от догадки, уточнять смысл, выражать конкретную просьбу без обвинения.' : 'Beobachtung und Vermutung unterscheiden, Verständnis klären, konkret ohne Vorwurf bitten.'], 'materials' => [$ru ? 'Таймер, ручки, бумага, две чашки и телефон или заменяющий предмет. Раздатка выбранного формата.' : 'Timer, Stifte, Papier, zwei Tassen und Handy oder Ersatzgegenstand. Druckmaterial des gewählten Formats.'], 'devices' => $ru ? 'Устройства необязательны. Личные разговоры и недельный план остаются на бумаге.' : 'Geräte freiwillig. Persönliche Gespräche und Wochenplan bleiben auf Papier.', 'conditions' => ($ru ? 'От 18 лет. ' : 'Ab 18 Jahren. ').($couples ? ($ru ? 'Одна пара с ведущим или 2–8 пар, 75 минут.' : 'Ein Paar mit Leitung oder 2–8 Paare, 75 Minuten.') : ($ru ? '4–24 участника, 60 минут, семейное положение любое.' : '4–24 Personen, 60 Minuten, unabhängig vom Familienstand.'))];
    }
    $files = [];
    foreach (config('lesson-files') as $fileId => $file) {
        if (str_starts_with($fileId, 'listening-file-') && (! str_contains($fileId, '-slides-') || str_contains($fileId, '-slides-'.$variant.'-'))) {
            $files[] = ['fileId' => $fileId, 'kind' => $file['kind'], 'locale' => $file['locale']];
        }
    }
    $lessons[$variant] = ['sourceRevision' => 'listening-'.$variant.'-ru-de-2026-10-04-v1', 'materialId' => 'c100a42'.$key.'-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a42'.$key.'-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'pochemu-my-ne-slyshim-drug-druga'.($couples ? '-suprugi' : ''), 'metadata' => ['translations' => $translations, 'age' => ['adults'], 'topic' => ['bible'], 'audience' => $couples ? ['family', 'group', 'adults'] : ['group', 'adults'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => $couples ? 75 : 60, 'cover' => $image(1)['image'], 'details' => $details], 'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => array_map(static fn ($t) => ['title' => $t['title']], $translations), 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => $plans, 'files' => $files]]];
}

return $lessons;
