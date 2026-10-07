<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/adult-forgiveness-source.json'), true, flags: JSON_THROW_ON_ERROR);
$version = 'd100a426-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$both = static fn ($ru, $de, $key = 'text') => ['ru' => [$key => $ru], 'de' => [$key => $de]];
$make = static function ($id, $type, $content, $config = []): array {
    if ($type === 'presentation') {
        $content = array_map(static fn ($c) => $c + ['modes' => []], $content);
    }

    return ['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => $content, 'config' => $config];
};
$image = static fn ($n) => ['image' => ['assetId' => 'builtin-adult-forgiveness-'.$n, 'versionId' => 'builtin-adult-forgiveness-'.$n.'-v1']];
$prompt = static fn ($id, $ru, $de, $target = 'class') => $make($id, 'prompt', $both($ru, $de), ['kind' => 'instruction', 'target' => $target]);
$free = static fn ($id, $ru, $de) => $make($id, 'free-response', ['ru' => ['question' => $ru, 'label' => 'Добровольный ответ об учебном примере', 'placeholder' => 'Без имён и личных историй', 'submitLabel' => 'Отправить'], 'de' => ['question' => $de, 'label' => 'Freiwillige Antwort zum Übungsbeispiel', 'placeholder' => 'Keine Namen oder persönlichen Geschichten', 'submitLabel' => 'Senden']], ['allowRepeat' => true, 'maxLength' => 700]);
$reveal = static fn ($id, $ru, $de, $textRu, $textDe) => $make($id, 'presentation', ['ru' => ['title' => $ru, 'label' => $ru, 'hideLabel' => 'Скрыть: '.$ru, 'text' => $textRu], 'de' => ['title' => $de, 'label' => $de, 'hideLabel' => 'Ausblenden: '.$de, 'text' => $textDe]], array_replace($base, ['kind' => 'reveal']));
$poll = static fn ($id) => $make($id, 'poll', ['ru' => ['question' => 'Какое выражение ближе к слову «простить»?', 'options' => [['optionId' => 'forget', 'text' => 'Забыть'], ['optionId' => 'mercy', 'text' => 'Отказаться от мести'], ['optionId' => 'trust', 'text' => 'Снова доверять']]], 'de' => ['question' => 'Welcher Ausdruck passt eher zu „vergeben“?', 'options' => [['optionId' => 'forget', 'text' => 'Vergessen'], ['optionId' => 'mercy', 'text' => 'Auf Vergeltung verzichten'], ['optionId' => 'trust', 'text' => 'Wieder vertrauen']]]], ['allowRepeat' => true]);
$cards = [];
foreach (['ru', 'de'] as $locale) {
    $paragraphs = $raw[$locale]['worksheetParagraphs'];
    foreach ($paragraphs as $index => $paragraph) {
        if (preg_match('/^[1-6]$/', $paragraph) === 1) {
            $cards[$locale][(int) $paragraph] = $paragraphs[$index + 2];
        }
    }
}
$context = [
    'ru' => 'Библейский смысл: полученная от Бога милость обязывает миловать другого и прощать от сердца (ст. 27, 33, 35). Отказ от мести — практический шаг, а не исчерпывающее определение прощения. В контексте Мф. 18:15–17 причинённый вред называют и обсуждают, а не скрывают. Притча не требует забыть происшедшее или безусловно восстановить доверие. Различение памяти, примирения и доверия — педагогическое применение. Ремонт чашки — образ терпения; возвращение отношений к прежнему виду не обязательно.',
    'de' => 'Biblischer Sinn: Empfangene Barmherzigkeit verpflichtet dazu, dem anderen barmherzig zu begegnen und von Herzen zu vergeben (V. 27, 33, 35). Verzicht auf Vergeltung ist ein praktischer Schritt, keine vollständige Definition von Vergebung. Im Kontext Matthäus 18,15–17 wird der Schaden benannt und besprochen, nicht verschwiegen. Das Gleichnis fordert weder Vergessen noch bedingungsloses Vertrauen. Die Unterscheidung von Erinnerung, Versöhnung und Vertrauen ist eine pädagogische Anwendung. Die reparierte Tasse steht für Geduld; Beziehungen müssen nicht ihre frühere Form wiedererlangen. Bibeltext: eigene Arbeitsübersetzung des bereitgestellten russischen Synodaltextes, keine Ausgabe eines deutschen Bibelverlags.',
];
$stages = [];
foreach ($raw['ru']['stages'] as $i => $stage) {
    $n = $i + 1;
    $id = 'adult-forgiveness-step-'.sprintf('%02d', $n);
    $scene = $notes = [];
    foreach (['ru', 'de'] as $locale) {
        $lines = $raw[$locale]['slides'][$i]['text'];
        $scene[$locale] = ['eyebrow' => $lines[0], 'title' => $lines[1], 'text' => implode("\n", array_slice($lines, 2, -1))];
        $notes[$locale] = $raw[$locale]['stages'][$i]['notes'];
        if ($n === 2) {
            $notes[$locale] .= "\n\n".$raw[$locale]['Bible']."\n\n".$context[$locale];
        }
    }
    $blocks = [$make($id.'-scene', 'presentation', $scene, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $blocks[] = $make($id.'-image', 'image', ['ru' => ['alt' => $scene['ru']['title'], 'caption' => ''], 'de' => ['alt' => $scene['de']['title'], 'caption' => '']], ['fit' => 'contain']) + ['media' => $image($n)];
    if ($n === 1) {
        $blocks[] = $poll($id.'-meaning');
        $blocks[] = $prompt($id.'-pair', '30 секунд на выбор, 2 минуты объясните его соседу. Оценки нет. Личный вопрос запишите на бумаге; можно выбрать вымышленную ситуацию. Личные истории не раскрываем.', '30 Sekunden wählen, 2 Minuten dem Gegenüber erklären. Keine Bewertung. Persönliche Frage auf Papier; erfundene Situationen sind erlaubt. Keine persönlichen Geschichten offenlegen.', 'pair');
    } elseif ($n === 2) {
        $blocks[] = $prompt($id.'-reading', 'Прочитайте Мф. 18:21–35 целиком из плана ведущего. Следите за долгами, просьбами и ответами. Талант — денежная единица. Не пропускайте ст. 34–35. Роли добровольны; остальные наблюдают.', 'Matthäus 18,21–35 vollständig aus dem Leitungsplan lesen. Schulden, Bitten und Antworten beachten. Talent ist eine Geldeinheit. V. 34–35 nicht auslassen. Rollen freiwillig; andere beobachten.');
        $roles = [];
        foreach (['ru' => ['Рассказчик', 'Царь', 'Первый должник', 'Товарищ-должник', 'Свидетель'], 'de' => ['Erzähler', 'König', 'Erster Schuldner', 'Mitschuldner', 'Zeuge']] as $locale => $labels) {
            $roles[$locale] = ['text' => $locale === 'ru' ? 'Добровольные роли для сценки' : 'Freiwillige Rollen für das Spiel', 'roles' => array_map(static fn ($label, $index) => ['roleId' => 'role-'.($index + 1), 'text' => $label], $labels, array_keys($labels))];
        }
        $blocks[] = $make($id.'-roles', 'roles', $roles, ['capacities' => ['role-1' => 1, 'role-2' => 1, 'role-3' => 1, 'role-4' => 1, 'role-5' => 1]]);
    } elseif ($n === 3) {
        $blocks[] = $prompt($id.'-play', '4 минуты сценки по ст. 23–35, без физического контакта. Сохраните окончание притчи. 2 минуты сравните просьбы в паре; 3 минуты общего разговора.', '4 Minuten Spiel nach V. 23–35 ohne körperlichen Kontakt. Ende beibehalten. 2 Minuten Bitten zu zweit vergleichen; 3 Minuten gemeinsames Gespräch.', 'group');
        $blocks[] = $free($id.'-mercy', 'О притче: что первый должник получил сверх просьбы и как должен был ответить товарищу?', 'Zum Gleichnis: Was erhielt der erste Schuldner über seine Bitte hinaus, und wie hätte er dem anderen begegnen sollen?');
        $blocks[] = $reveal($id.'-review', 'Разбор притчи', 'Besprechung des Gleichnisses', 'Долги: 10 000 талантов и 100 динариев. Оба просили отсрочки. Царь простил весь долг, а первый должник отказал товарищу и посадил его в темницу. Полученная милость должна была побудить к милости. Ст. 34–35 сохраняют серьёзность прощения от сердца; число 490 не даёт права на следующую месть.', 'Schulden: 10 000 Talente und 100 Denare. Beide baten um Aufschub. Der König erließ die ganze Schuld; der erste Schuldner verweigerte Erbarmen und brachte den anderen ins Gefängnis. Empfangene Barmherzigkeit sollte zu Barmherzigkeit führen. V. 34–35 nehmen Vergebung von Herzen ernst; die Zahl 490 erlaubt keine nächste Vergeltung.');
    } elseif ($n === 4) {
        for ($c = 1; $c <= 4; $c++) {
            $blocks[] = $prompt($id.'-card-'.$c, $c.'. '.$cards['ru'][$c], $c.'. '.$cards['de'][$c], 'group');
        }
        $blocks[] = $free($id.'-distinction', 'Выберите одну учебную карточку: где память, отказ от мести, примирение или доверие? Что зависит от одного человека, а что от обоих?', 'Eine Übungskarte wählen: Erinnerung, Verzicht auf Vergeltung, Versöhnung oder Vertrauen? Was hängt von einer Person, was von beiden ab?');
        $blocks[] = $reveal($id.'-review', 'Разбор карточек', 'Besprechung der Karten', '1: память совместима с отказом от мести. 2: предложение разговора — мой шаг; примирение требует обоих. 3: принятие извинения не возвращает доверие автоматически. 4: вернувшаяся боль сама по себе не доказывает отсутствие прощения. Эти различения — применение, не определения из притчи. Милость от сердца шире отказа от ответного унижения.', '1: Erinnerung ist mit Verzicht auf Vergeltung vereinbar. 2: Ein Gespräch anzubieten ist mein Schritt; Versöhnung braucht beide. 3: Eine angenommene Entschuldigung stellt Vertrauen nicht automatisch wieder her. 4: Wiederkehrender Schmerz beweist allein keine fehlende Vergebung. Diese Unterscheidungen sind Anwendung, keine Definitionen aus dem Gleichnis. Barmherzigkeit von Herzen geht über Verzicht auf Demütigung hinaus.');
    } elseif ($n === 5) {
        $blocks[] = $prompt($id.'-case', $cards['ru'][5], $cards['de'][5], 'group');
        $blocks[] = $free($id.'-before', 'До нового факта, только о Елене и Ольге: вред / возможная месть / просьба / условие общения. Объясните выбор.', 'Vor der neuen Information, nur über Elena und Olga: Schaden / mögliche Vergeltung / Bitte / Bedingung für den Kontakt. Begründen.');
        $blocks[] = $reveal($id.'-new-fact', 'Открыть новый факт', 'Neue Information zeigen', 'Ольга признала поступок, удалила пересланное сообщение и попросила получательницу его не распространять.', 'Olga hat ihr Verhalten eingestanden, die weitergeleitete Nachricht gelöscht und die Empfängerin gebeten, sie nicht weiterzuverbreiten.');
        $blocks[] = $free($id.'-after', 'После раскрытия: что изменилось? Что ещё не восстановилось? Пересмотрите решение о вымышленных героях.', 'Nach der Aufdeckung: Was hat sich verändert? Was ist noch nicht wiederhergestellt? Entscheidung über die erfundenen Figuren überarbeiten.');
        $blocks[] = $reveal($id.'-review', 'Разбор нового факта', 'Information besprechen', 'Признание и попытка ограничить вред — начало ответственности. Нельзя обещать удаление всех копий или немедленное доверие. Елена может отказаться от публичного унижения и всё же попросить о конфиденциальности и ограничить личные подробности.', 'Eingeständnis und Schadensbegrenzung sind ein Anfang von Verantwortung. Das Löschen aller Kopien oder sofortiges Vertrauen kann niemand versprechen. Elena kann auf öffentliche Demütigung verzichten und dennoch Vertraulichkeit verlangen und persönliche Einzelheiten begrenzen.');
    } elseif ($n === 6) {
        $blocks[] = $prompt($id.'-practice', '«Прости, если ты обиделась, но я хотела как лучше». 1 минута: найдите условность и оправдание. Только роли Ольги и Елены: 2 минуты разговора + 1 минута обратной связи, смена ролей, второй такой же круг. Последние 3 минуты обсуждение. Елена может не согласиться сразу. Личный разговор не отправляем.', '„Entschuldige, falls du beleidigt bist, aber ich meinte es gut.“ 1 Minute: Bedingung und Rechtfertigung finden. Nur Rollen Olga und Elena: 2 Minuten Gespräch + 1 Minute Rückmeldung, Rollenwechsel, zweite gleiche Runde. Letzte 3 Minuten besprechen. Elena muss nicht sofort zustimmen. Kein persönliches Gespräch senden.', 'pair');
        $blocks[] = $free($id.'-apology', 'Добровольно: перепишите просьбу о прощении Ольги — поступок, вред, просьба, выполнимое исправление.', 'Freiwillig: Olgas Bitte um Vergebung umschreiben — Tat, Schaden, Bitte, machbare Wiedergutmachung.');
        $blocks[] = $reveal($id.'-example', 'Пример ответственной просьбы', 'Beispiel einer verantwortlichen Bitte', 'Я переслала твои слова без разрешения. Нарушила твоё доверие. Прости. Я уже попросила не распространять сообщение и больше не буду делиться твоими словами без согласия. Это пример, не обязательная формула. Не обещаем управлять всеми получателями.', 'Ich habe deine Worte ohne Erlaubnis weitergeleitet und dein Vertrauen verletzt. Bitte vergib mir. Ich habe bereits darum gebeten, die Nachricht nicht weiterzuverbreiten. Künftig teile ich deine Worte nur mit deiner Zustimmung. Beispiel, keine Pflichtformel. Keine Kontrolle über alle Empfänger versprechen.');
    } elseif ($n === 7) {
        $blocks[] = $reveal($id.'-repeat', 'Открыть карточку 6', 'Karte 6 zeigen', $cards['ru'][6], $cards['de'][6]);
        $blocks[] = $prompt($id.'-practice', 'После карточки 6: 3 минуты пересмотрите условия общения в группе; 2 минуты в паре сформулируйте ответ Елены: факт, граница, дальнейшее действие; 3 минуты общего разбора. Что зависит от неё? Кто отвечает за новый вред?', 'Nach Karte 6: 3 Minuten Kontaktbedingungen in der Gruppe überarbeiten; 2 Minuten zu zweit Elenas Antwort formulieren: Tatsache, Grenze, weiterer Schritt; 3 Minuten gemeinsam besprechen. Was hängt von ihr ab? Wer verantwortet den neuen Schaden?', 'group');
        $blocks[] = $free($id.'-boundary', 'Только о Елене: новый ответ с фактом, границей и дальнейшим действием, без мести.', 'Nur über Elena: neue Antwort mit Tatsache, Grenze und weiterem Schritt, ohne Vergeltung.');
        $blocks[] = $reveal($id.'-review', 'Разбор границы', 'Die Grenze besprechen', 'Ты снова передала личные слова. Пока я не буду обсуждать с тобой семейные подробности. О нашем общении можем поговорить отдельно. Ольга отвечает за повторный поступок. Доверие укрепляют последовательные действия. При продолжающемся вреде сначала защита и помощь. Чашка — образ терпения, не обязанность вернуть прежние отношения.', 'Du hast wieder persönliche Worte weitergegeben. Vorerst bespreche ich mit dir keine familiären Einzelheiten. Über unseren Kontakt können wir gesondert sprechen. Olga verantwortet die wiederholte Tat. Beständige Handlungen stärken Vertrauen. Bei fortgesetztem Schaden zuerst Schutz und Hilfe. Die Tasse steht für Geduld, nicht für die Pflicht, frühere Beziehungen wiederherzustellen.');
    } else {
        $blocks[] = $prompt($id.'-private-plan', '2 минуты на личной бумаге: вред, отказ от мести, просьба или граница, что сам могу исправить, шаг и срок. Можно вымышленный пример. Лист остаётся у вас. Никто не должен заявлять «я простил»; не оцениваем состояние человека.', '2 Minuten auf persönlichem Papier: Schaden, Verzicht auf Vergeltung, Bitte oder Grenze, eigene Wiedergutmachung, Schritt und Zeitpunkt. Erfundener Fall erlaubt. Blatt bleibt bei dir. Niemand muss „Ich habe vergeben“ erklären; keine Bewertung einer Person.');
        $blocks[] = $poll($id.'-meaning');
        $blocks[] = $free($id.'-learning', 'Добровольно, об учебном материале: «Теперь я различаю…». Как полученная первым должником милость должна была изменить его отношение к товарищу?', 'Freiwillig zum Unterricht: „Jetzt unterscheide ich…“. Wie hätte die empfangene Barmherzigkeit das Verhalten des ersten Schuldners verändern sollen?');
        $blocks[] = $reveal($id.'-prayer', 'Добровольная молитва', 'Freiwilliges Gebet', 'Господи Иисусе Христе, Ты милуешь меня. Помоги мне отказаться от мести, увидеть причинённую мной боль и честно искать путь к миру. Дай терпение исправлять сделанное. Аминь.', 'Herr Jesus Christus, Du bist mir barmherzig. Hilf mir, auf Vergeltung zu verzichten, den von mir verursachten Schmerz zu sehen und ehrlich einen Weg zum Frieden zu suchen. Gib mir Geduld, mein Handeln zu korrigieren. Amen.');
        $blocks[] = $make($id.'-closing', 'presentation', ['ru' => ['title' => 'Милость от сердца', 'text' => 'Полученная милость побуждает миловать другого. Один выполнимый шаг к миру, без мести и без сокрытия вреда.', 'label' => 'Завершить занятие', 'hideLabel' => 'Закрыть окно'], 'de' => ['title' => 'Barmherzigkeit von Herzen', 'text' => 'Empfangene Barmherzigkeit führt zu Barmherzigkeit gegenüber anderen. Ein machbarer Schritt zum Frieden, ohne Vergeltung und ohne Schaden zu verschweigen.', 'label' => 'Treffen beenden', 'hideLabel' => 'Fenster schließen']], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $stage['title'], 'notes' => $notes['ru']], 'de' => ['title' => $raw['de']['stages'][$i]['title'], 'notes' => $notes['de']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $stage['minutes'] * 60, 'answerSeconds' => $n === 1 ? 30 : ($n === 6 ? 120 : $stage['minutes'] * 60), 'openTasks' => true, 'closeOnTimer' => true, 'theme' => 'copper'], 'blocks' => $blocks];
}
$translations = $plans = $details = [];
foreach (['ru', 'de'] as $locale) {
    $ru = $locale === 'ru';
    $title = $ru ? 'Простить — значит всё забыть?' : 'Vergeben heißt alles vergessen?';
    $translations[$locale] = ['title' => $title, 'description' => ($ru ? 'Для взрослых от 18 лет. ' : 'Für Erwachsene ab 18 Jahren. ').$raw[$locale]['website']];
    $plans[$locale] = ['plan' => implode("\n\n", [$raw[$locale]['Profile'], $raw[$locale]['Plan'], $raw[$locale]['Scenario_general'], $raw[$locale]['Teacher'], $raw[$locale]['Bible'], implode("\n", $raw[$locale]['worksheetParagraphs']), $context[$locale]])];
    $details[$locale] = ['goals' => [$ru ? 'Связать полученную милость с прощением от сердца. Различить прощение, примирение и доверие; выбрать ответ без мести.' : 'Empfangene Barmherzigkeit mit Vergebung von Herzen verbinden. Vergebung, Versöhnung und Vertrauen unterscheiden; ohne Vergeltung antworten.'], 'materials' => [$ru ? 'Библии, таймер, ручки, пять добровольцев для сценки, карточки на группу из четырёх и личные рабочие листы.' : 'Bibeln, Timer, Stifte, fünf Freiwillige für das Spiel, Karten je Vierergruppe und persönliche Arbeitsblätter.'], 'devices' => $ru ? 'Устройства добровольны. Личные записи остаются на бумаге; цифровые ответы только об учебных героях.' : 'Geräte freiwillig. Persönliche Notizen auf Papier; digitale Antworten nur über Übungsfiguren.', 'conditions' => $ru ? 'Взрослые 18+, 4–20 участников, 60 минут. Вымышленные примеры и отказ от участия допустимы.' : 'Erwachsene ab 18, 4–20 Personen, 60 Minuten. Erfundene Beispiele und Nichtteilnahme sind erlaubt.'];
}
$files = [];
foreach (config('lesson-files') as $fileId => $file) {
    if (str_starts_with($fileId, 'adult-forgiveness-file-')) {
        $files[] = ['fileId' => $fileId, 'kind' => $file['kind'], 'locale' => $file['locale']];
    }
}

return ['sourceRevision' => 'adult-forgiveness-ru-de-2026-10-07-v1', 'materialId' => 'c100a426-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a426-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'prostit-znachit-vse-zabyt', 'metadata' => ['translations' => $translations, 'age' => ['adults'], 'topic' => ['bible'], 'audience' => ['group', 'adults'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 60, 'cover' => $image(1)['image'], 'details' => $details], 'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => array_map(static fn ($t) => ['title' => $t['title']], $translations), 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => $plans, 'files' => $files]]];
