<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/choices-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/choices-de.php';
$version = 'd050a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 300]);
$pictures = [1, 5, 6, 2, 7, 3, 8, 9, 10, 11, 4, 12, 13, 14];
$sourceLines = static function (int $number) use ($raw): array {
    $lines = $raw['slides'][$number - 1]['slide'];
    if ($number === 1) {
        return ['title' => implode(' ', array_slice($lines, 0, 4)), 'text' => $lines[4], 'source' => $lines[5], 'modes' => []];
    }
    $title = array_shift($lines);
    if (end($lines) === str_pad((string) $number, 2, '0', STR_PAD_LEFT)) {
        array_pop($lines);
    }
    $source = null;
    foreach ($lines as $i => $line) {
        if (str_starts_with($line, 'Лк. ') || $line === 'Авторский пример короткой молитвы') {
            $source = $line;
            unset($lines[$i]);
        }
    }

    return array_filter(['title' => $title, 'text' => implode("\n", $lines), 'source' => $source, 'modes' => []], static fn ($v) => $v !== null);
};
$image = static fn (int $number): array => ['image' => ['assetId' => 'builtin-choices-'.$pictures[$number - 1], 'versionId' => 'builtin-choices-'.$pictures[$number - 1].'-v1']];
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'choices-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $slide = $screen['slides'][0];
    $ru = $sourceLines($slide);
    $german = $de['slides'][$slide - 1] + ['modes' => []];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($slide);
    $blocks[] = $picture;
    foreach (array_slice($screen['slides'], 1) as $next) {
        $content = $sourceLines($next);
        $reveal = $make($id.'-frame-'.$next, 'presentation', $content + ['label' => $content['title'], 'hideLabel' => 'Скрыть кадр: '.$content['title']], $de['slides'][$next - 1] + ['modes' => [], 'label' => $de['slides'][$next - 1]['title'], 'hideLabel' => 'Bild ausblenden: '.$de['slides'][$next - 1]['title']], array_replace($base, ['kind' => 'reveal']));
        $reveal['media'] = $image($next);
        $blocks[] = $reveal;
    }
    if ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для евангельской сценки', 'roles' => $roles(['Рассказчик', 'Младший сын', 'Отец', 'Старший сын', 'Слуга'])], ['text' => 'Rollen für das Evangeliumsspiel', 'roles' => $roles(['Erzähler', 'Jüngerer Sohn', 'Vater', 'Älterer Sohn', 'Diener'])], ['capacities' => ['role-1' => 1, 'role-2' => 1, 'role-3' => 1, 'role-4' => 1, 'role-5' => 1]]);
    } elseif ($n === 4) {
        $blocks[] = $make($id.'-sort', 'prompt', ['text' => 'В паре разделите их на три группы: выбор младшего, выбор старшего, внешнее обстоятельство. Сверьте с текстом; затем поменяйтесь ролями.'], ['text' => 'Zu zweit in drei Gruppen sortieren: Entscheidung des jüngeren, Entscheidung des älteren, äußerer Umstand. Am Text prüfen, dann Rollen wechseln.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $make($id.'-review', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => 'Сам ли младший сын вызвал голод? Какой новый выбор он сделал в нужде?', 'table' => ['headers' => ['Событие', 'Группа'], 'rows' => [['Просит свою долю и уходит.', 'Выбор младшего сына'], ['Растрачивает имущество.', 'Выбор младшего сына'], ['В стране наступает голод.', 'Внешнее обстоятельство'], ['Решает вернуться к отцу.', 'Выбор младшего сына'], ['Встаёт и идёт к отцу.', 'Выбор младшего сына'], ['Старший не хочет войти.', 'Выбор старшего сына']]]], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => 'Verursachte der jüngere die Hungersnot selbst? Welche neue Entscheidung traf er in seiner Not?', 'table' => ['headers' => ['Ereignis', 'Gruppe'], 'rows' => [['Bittet um seinen Anteil und geht.', 'Entscheidung des jüngeren'], ['Verbraucht den Besitz.', 'Entscheidung des jüngeren'], ['Hungersnot im Land.', 'Äußerer Umstand'], ['Beschließt zum Vater zurückzukehren.', 'Entscheidung des jüngeren'], ['Steht auf und geht zum Vater.', 'Entscheidung des jüngeren'], ['Älterer will nicht eintreten.', 'Entscheidung des älteren']]]], array_replace($base, ['kind' => 'reveal']));
    } elseif ($n === 5) {
        $blocks[] = $free($id.'-open-end', 'Что может выбрать старший? Отменяет ли принятие сына всё, что произошло раньше?', 'Was kann der ältere wählen? Macht die Annahme des Sohnes alles Vorherige ungeschehen?');
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-first-plan', 'prompt', ['text' => 'Для каждого варианта назовите: что будет сейчас; что возможно завтра; кого затронет; кто за что отвечает. После нового условия сохраните оба плана.'], ['text' => 'Je Möglichkeit: Was geschieht jetzt, was ist morgen möglich, wen betrifft es, wer übernimmt Verantwortung? Nach der neuen Bedingung beide Pläne aufbewahren.'], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-second-plan', 'prompt', ['text' => 'В 18:00 Илья может работать ещё 20 минут, но всей части за это время не завершит. Обсудите раннее сообщение, передачу доступной работы и просьбу о согласованном изменении. Отделите известные факты от прогнозов.'], ['text' => 'Ab 18:00 kann Ilja noch 20 Minuten arbeiten, seinen ganzen Teil aber nicht fertigstellen. Frühzeitige Nachricht, Übergabe verfügbarer Arbeit und Bitte um vereinbarte Änderung besprechen. Fakten von Prognosen trennen.'], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-boundary', 'Как ответить ясно и без унижения другого?', 'Wie antworten wir klar, ohne den anderen herabzusetzen?');
    } elseif ($n === 9) {
        $items = static fn ($texts) => array_map(static fn ($text, $j) => ['itemId' => 'step-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-repair', 'sequence', ['question' => 'Как начать исправление', 'items' => $items(['Обсудить исправление. Спроси, что поможет. Договоритесь о посильном действии.', 'Остановить вред. Прекрати пересылку и попроси получателей удалить фото.', 'Сделать и проверить. Выполни договор. Проверь результат; доверие может возвращаться постепенно.', 'Признать поступок. Скажи человеку: «Я переслал без твоего согласия». Не оправдывайся.']), 'emptyText' => '…'], ['question' => 'Mit Wiedergutmachung beginnen', 'items' => $items(['Wiedergutmachung besprechen. Fragen, was hilft; machbare Handlung vereinbaren.', 'Schaden stoppen. Nicht weiterleiten, Empfänger um Löschen bitten.', 'Tun und prüfen. Vereinbarung erfüllen, Ergebnis prüfen; Vertrauen kann allmählich zurückkehren.', 'Tat eingestehen. „Ich habe es ohne deine Zustimmung weitergeschickt.“ Nicht rechtfertigen.']), 'emptyText' => '…'], ['allowRepeat' => true]);
        $blocks[] = $make($id.'-repair-review', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => 'Некоторые шаги можно выполнять рядом по времени. Остановить дальнейшую пересылку, попросить получателей удалить; признать поступок перед человеком, спросить о нужной помощи, выполнить договор. Удаление своей копии не гарантирует исчезновения всех копий.'], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => 'Manche Schritte können zeitlich nebeneinander erfolgen. Weiterleiten stoppen, Empfänger um Löschen bitten; Tat eingestehen, nach benötigter Hilfe fragen, Vereinbarung erfüllen. Löschen der eigenen Kopie garantiert nicht, dass alle verschwinden.'], array_replace($base, ['kind' => 'reveal']));
    } elseif ($n === 10) {
        $blocks[] = $free($id.'-message', 'Что скажешь команде?', 'Was sagst du dem Team?');
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-choice', 'Назови партнёру один выбор героя и одно обстоятельство.', 'Nenne dem Partner eine Entscheidung der Figur und einen Umstand.');
        $blocks[] = $free($id.'-question', 'Перед решением я спрошу себя…', 'Vor einer Entscheidung frage ich mich…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Мой выбор затрагивает других.', 'text' => 'После ошибки я могу признать её и изменить следующий шаг; милость Бога открывает путь возвращения.', 'eyebrow' => 'Урок завершён', 'source' => 'Лк. 15:11–32', 'modes' => []], ['title' => 'Meine Entscheidung betrifft andere.', 'text' => 'Nach einem Fehler kann ich ihn eingestehen und den nächsten Schritt ändern; Gottes Barmherzigkeit eröffnet den Rückweg.', 'eyebrow' => 'Die Stunde ist beendet', 'source' => 'Lukas 15,11–32', 'modes' => []], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'plum'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'choices-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n14–16 Jahre · 45 Minuten · 8–20 Teilnehmende, Paare, Dreiergruppen und Viererteams.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['handout'];

return [
    'sourceRevision' => 'choices-ru-de-2026-10-03-v1', 'materialId' => 'c050a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e050a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'ya-imeyu-pravo-vybirat',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible', 'mercy'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Помочь подросткам связывать свободу выбора с ответственностью, учитывать последствия для других и видеть возможность покаяния и нового шага к Богу.'], 'materials' => ['Библия или полный текст Лк. 15:11–32 из пособия; PowerPoint, экран, карандаши, листы.', 'Шесть страниц иллюстрированной раздатки: роли, выбор и обстоятельства, четыре условия командной задачи, ситуации для пар, исправление и разговор, личный план.', 'Сумка для ухода, два условных места для дома и дальней страны. Роли можно читать.'], 'devices' => 'Пары и команды обсуждают и записывают на бумаге. Устройства подростков не обязательны; короткие ответы можно отправить через Enter.', 'conditions' => '14–16 лет. 45 минут. 8–20 человек, пары, тройки и команды по четыре. Личные семейные истории и ошибки подросток может оставить при себе. Личный лист остаётся у подростка. Можно молча слушать молитву.'], 'de' => ['goals' => ['Jugendlichen helfen, Entscheidungsfreiheit mit Verantwortung zu verbinden, Folgen für andere zu beachten und die Möglichkeit von Umkehr und einem neuen Schritt zu Gott zu sehen.'], 'materials' => ['Bibel oder vollständiger Lukas 15,11–32, PowerPoint, Bildschirm, Stifte, Papier.', 'Sechs illustrierte Seiten: Rollen, Entscheidungen und Umstände, vier Teambedingungen, Paarsituationen, Wiedergutmachung und Gespräch, persönlicher Plan.', 'Tasche für Weggang, zwei Plätze für Zuhause und fernes Land. Rollen können abgelesen werden.'], 'devices' => 'Paare und Teams sprechen und schreiben auf Papier. Eigene Geräte nicht erforderlich; kurze Antworten mit Enter möglich.', 'conditions' => '14–16 Jahre. 45 Minuten. 8–20 Menschen, Paare, Dreiergruppen und Viererteams. Familiengeschichten und Fehler dürfen privat bleiben. Persönliches Blatt bleibt beim Jugendlichen. Still zuhören beim Gebet möglich.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
