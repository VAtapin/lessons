<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/talant-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/talant-de.php';
$version = 'efcbe573-4b59-491e-b2c6-97191aa02d2c';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn (string $id, string $type, array $ru, array $german, array $config = [], ?array $solution = null): array => array_filter([
    'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $german], 'config' => $config, 'solution' => $solution,
], static fn ($v) => $v !== null);
$free = static fn (string $id, string $ru, string $german): array => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 300]);
$reveal = static fn (string $id, array $ru, array $german): array => $make($id, 'presentation', $ru + ['modes' => []], $german + ['modes' => []], array_replace($base, ['kind' => 'reveal']));
$imageNumbers = [1 => 5, 2 => 2, 5 => 3, 6 => 1, 7 => 4];
$alt = [1 => ['Разные способности', 'Unterschiedliche Fähigkeiten'], 2 => ['Хозяин доверяет средства', 'Der Herr vertraut Mittel an'], 3 => ['Слуга прячет серебро', 'Der Diener versteckt das Silber'], 4 => ['Помощь новому участнику', 'Hilfe für einen neuen Mitspieler'], 5 => ['Возможности для общего дела', 'Möglichkeiten für eine gemeinsame Aufgabe']];
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'talent-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $slide = $raw['slides'][$screen['slides'][0] - 1]['slide'];
    $german = $de['slides'][$screen['slides'][0] - 1];
    $ru = ['title' => $slide[0], 'text' => implode("\n", array_slice($slide, 1)), 'modes' => []];
    if ($n === 1) {
        $ru = ['title' => $raw['title'], 'text' => '11–13 лет   45 минут', 'modes' => []];
    }
    $german['modes'] = [];
    if (isset($german['question'])) {
        $ru['subtitle'] = array_pop($slide);
        $ru['text'] = implode("\n", array_slice($slide, 1));
        $german['subtitle'] = $german['question'];
        unset($german['question']);
    }
    if (isset($german['source'])) {
        $ru['source'] = array_pop($slide);
        $ru['text'] = implode("\n", array_slice($slide, 1));
    }
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    if (isset($imageNumbers[$n])) {
        $img = $imageNumbers[$n];
        $image = $make($id.'-image', 'image', ['alt' => $alt[$img][0], 'caption' => ''], ['alt' => $alt[$img][1], 'caption' => ''], ['fit' => 'contain']);
        $image['media'] = ['image' => ['assetId' => 'builtin-talent-'.$img, 'versionId' => 'builtin-talent-'.$img.'-v1']];
        $blocks[] = $image;
    }
    if ($n === 2) {
        $roles = static fn (array $texts): array => array_map(static fn ($t, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $t], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для сценки', 'roles' => $roles(['Хозяин', 'Первый слуга', 'Второй слуга', 'Третий слуга'])], ['text' => 'Rollen für das Spiel', 'roles' => $roles(['Herr', 'Erster Diener', 'Zweiter Diener', 'Dritter Diener'])], ['capacities' => ['role-1' => 1, 'role-2' => 1, 'role-3' => 1, 'role-4' => 1]]);
        $blocks[] = $reveal($id.'-table', ['label' => 'Показать таблицу после ответов', 'hideLabel' => 'Скрыть таблицу', 'text' => 'Что слуги принесли к отчёту?', 'source' => 'До решения хозяина о третьем слуге.', 'table' => ['headers' => ['Слуга', 'Получил', 'При отчёте'], 'rows' => [['Первый', '5', '10'], ['Второй', '2', '4'], ['Третий', '1', '1']]]], ['label' => 'Tabelle nach den Antworten zeigen', 'hideLabel' => 'Tabelle ausblenden', 'text' => $de['slides'][2]['title'], 'source' => $de['slides'][2]['text'], 'table' => $de['slides'][2]['table']]);
    } elseif ($n === 3) {
        $blocks[] = $make($id.'-cards', 'prompt', ['text' => "В притче / В нашей жизни\nХозяин дал пять талантов\nСлуга закопал серебро\nЯ умею рисовать\nЯ умею слушать"], ['text' => "Im Gleichnis / In unserem Leben\nDer Herr gab fünf Talente\nDer Diener vergrub Silber\nIch kann zeichnen\nIch kann zuhören"], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 4) {
        $blocks[] = $free($id.'-answer', 'За что хозяин хвалит обоих?', $de['slides'][4]['question']);
    } elseif ($n === 5) {
        $blocks[] = $free($id.'-request', 'Какая конкретная просьба поможет героине начать?', 'Welche konkrete Bitte hilft der Figur anzufangen?');
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-possibilities', 'prompt', ['text' => "Понятно объясняю\nРисую или оформляю\nВнимательно слушаю\nЗамечаю детали\nМогу организовать\nХочу учиться"], ['text' => "Ich erkläre verständlich\nIch zeichne oder gestalte\nIch höre aufmerksam zu\nIch bemerke Details\nIch kann organisieren\nIch möchte lernen"], ['kind' => 'reflection', 'target' => 'pair']);
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-dialogue', 'prompt', ['text' => "НОВИЧОК: Хочу понять правила игры.\nНОВИЧОК: Хочу познакомиться с ребятами.\nПомощник спрашивает: «Что поможет тебе присоединиться?» После ответа предлагает одно действие."], ['text' => "NEUER MITSPIELER: Ich möchte die Spielregeln verstehen.\nNEUER MITSPIELER: Ich möchte die anderen kennenlernen.\nDer Helfer fragt „Was hilft dir, mitzumachen?“ und bietet nach der Antwort eine Handlung an."], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 8) {
        $blocks[] = $make($id.'-workshop', 'prompt', ['text' => 'Возможные роли: встретить; объяснить; оформить; проверить понятность. В команде из трёх участников две роли можно совместить.'], ['text' => 'Mögliche Rollen: empfangen, erklären, gestalten, Verständlichkeit prüfen. Bei drei Personen können zwei Rollen verbunden werden.'], ['kind' => 'instruction', 'target' => 'group']);
    } elseif ($n === 9) {
        $blocks[] = $free($id.'-helped', 'Мне помогло…', 'Mir hat geholfen …');
        $blocks[] = $free($id.'-clarify', 'Хочу уточнить…', 'Ich möchte noch wissen …');
    } elseif ($n === 10) {
        $blocks[] = $reveal($id.'-example', ['label' => 'Пример маленького дела', 'hideLabel' => 'Скрыть пример', 'text' => implode("\n", $raw['slides'][11]['slide'])], ['label' => $de['slides'][11]['title'], 'hideLabel' => 'Beispiel ausblenden', 'text' => $de['slides'][11]['title']."\n".$de['slides'][11]['text']]);
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-meaning', 'Талант в притче — это…', 'Ein Talent im Gleichnis ist …');
        $blocks[] = $free($id.'-step', 'Мой первый шаг — …', 'Mein erster Schritt — …');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Доверенное для добра', 'text' => '«Служите друг другу, каждый тем даром, какой получил…»', 'source' => '1 Пет. 4:10', 'eyebrow' => 'Урок завершён', 'modes' => []], ['title' => 'Anvertraut, um Gutes zu tun', 'text' => $de['slides'][13]['text'], 'source' => '1. Petrus 4,10', 'eyebrow' => 'Die Stunde ist beendet', 'modes' => []], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation']], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'terracotta'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'talent-file-') && str_ends_with($id, '-v1')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['plan']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['number'].' '.$s['title']."\n".$s['notes'], $raw['screens']))."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n11–13 Jahre · 45 Minuten · 6–20 Teilnehmende.\n".implode("\n", $de['goals'])."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['handout'];

return [
    'sourceRevision' => 'talent-ru-de-2026-10-02-v1', 'materialId' => 'ac00e09d-a38d-49ae-8687-321435c32136', 'versionId' => $version, 'ownerKey' => '667548ab-425a-4301-9698-bd7c4ad40b19', 'slug' => 'chto-delat-so-svoim-talantom',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => explode("\n\n", trim($raw['description']))[1]], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14'], 'topic' => ['bible', 'parables', 'mercy'], 'audience' => ['school', 'sunday-school', 'family', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => ['assetId' => 'builtin-talent-5', 'versionId' => 'builtin-talent-5-v1'],
        'details' => ['ru' => ['goals' => ['Объяснить, что талант в притче — денежная мера, и отличить сюжет от применения к способностям.', 'Сравнить действия трёх слуг и найти одинаковую похвалу первым двум.', 'Сделать полезную вещь для новичка и уточнить её после отзыва.', 'Составить посильный план помощи с получателем, первым шагом, сроком и поддержкой.'], 'materials' => ['Пять страниц раздатки: личный лист, карточки, роли и задание мастерской.', 'Конверт, бумага, карандаши или фломастеры; знакомая простая игра.', 'Экран и Библия: Мф. 25:14–30; 1 Пет. 4:10.'], 'devices' => 'Очное занятие с экраном, сценкой, карточками, изготовлением приглашения и пробой помощи. Материалы позволяют провести урок с распечатками вместо экрана. Личный лист остаётся у участника.', 'conditions' => '11–13 лет. 45 минут. 6–20 участников. Пары и команды по 3–4 человека. Роли и молитва добровольны. Талант в притче — денежная мера; применение к способностям поясняется отдельно.'], 'de' => ['goals' => $de['goals'], 'materials' => ['Fünf Seiten Kopiervorlagen: persönliches Blatt, Karten, Rollen und Werkstattaufgabe.', 'Umschlag, Papier, Stifte und ein bekanntes einfaches Spiel.', 'Bildschirm und Bibel: Matthäus 25,14–30; 1. Petrus 4,10.'], 'devices' => 'Präsenzunterricht mit Spiel, Karten, Einladung und Erprobung. Ausdrucke können den Bildschirm ersetzen. Das persönliche Blatt bleibt bei der Person.', 'conditions' => '11–13 Jahre. 45 Minuten. 6–20 Teilnehmende, Paare und Teams von 3–4. Rollen und Gebet freiwillig. Talent bezeichnet im Gleichnis Geld; die Anwendung auf Fähigkeiten wird getrennt erklärt.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
