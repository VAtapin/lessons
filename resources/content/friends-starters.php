<?php

declare(strict_types=1);

$source = require __DIR__.'/friends.php';
$raw = json_decode(file_get_contents(__DIR__.'/friends-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/friends-de.php';
$templates = [];
foreach ($source['document']['stages'] as $index => $stage) {
    foreach ($stage['blocks'] as $block) {
        if ($block['type'] === 'core.presentation' && in_array($block['config']['kind'], ['scene', 'closing'], true)) {
            continue;
        }
        if ($block['type'] === 'core.presentation' && $block['config']['kind'] === 'reveal') {
            // A reusable reveal is independent of the source lesson's sequence ID.
            $block['config']['reviewBlockId'] = null;
        }
        $block['teacherNotes'] = ['ru' => $raw['screens'][$index]['notes']."\n\nСначала полный рассказ Мк. 2:1–12, затем сценка. Христос прощает перед исцелением; друзья приносят человека к Нему. Болезнь не объясняется виной или недостатком веры. Личный лист и молитва добровольны; лист не собирается.", 'de' => $de['screens'][$index]['notes']."\n\nZuerst vollständiger Markus 2,1–12, danach das Spiel. Christus vergibt vor der Heilung; Freunde bringen den Mann zu ihm. Krankheit wird nicht durch Schuld oder mangelnden Glauben erklärt. Persönliches Blatt und Gebet freiwillig; das Blatt nicht einsammeln."];
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $suffix = $block['content'][$locale]['label'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['title'] ?? $block['content'][$locale]['alt'] ?? null;
            $title = $stage['content'][$locale]['title'];
            $labels[$locale] = ['title' => $suffix && $suffix !== $title ? $title.' · '.$suffix : $title, 'description' => mb_strlen($description) <= 200 ? $description : $title];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['friends', 'help', str_replace('core.', '', $block['type'])]]];
    }
}

$cards = [
    ['Пожелание друга', 'Саша хочет играть вместе со всеми. Ему нравится игра, в которой участники делают ходы по очереди.', 'Wunsch des Freundes', 'Sascha möchte mit allen spielen. Er mag ein Spiel, bei dem die Teilnehmenden abwechselnd ziehen.'],
    ['Что ему трудно', 'Саша пропустил встречу и пока не знает правил. Он хочет попробовать ходить сам, когда поймёт правила.', 'Was ihm schwerfällt', 'Sascha hat das Treffen versäumt und kennt die Regeln noch nicht. Wenn er sie verstanden hat, möchte er selbst ziehen.'],
    ['Что есть у команды', 'На столе есть простая настольная игра, фишки и кубик. Можно показать один пробный ход.', 'Was das Team hat', 'Auf dem Tisch liegen ein einfaches Brettspiel, Figuren und ein Würfel. Man kann einen Probezug zeigen.'],
    ['Сколько есть времени', 'До начала игры осталось три минуты. Длинное объяснение не поместится. После пробного хода спросите Сашу, понятно ли ему.', 'Wie viel Zeit bleibt', 'Bis zum Spielbeginn bleiben drei Minuten. Eine lange Erklärung passt nicht. Fragt Sascha nach dem Probezug, ob er verstanden hat.'],
    ['Пропустил урок', 'Лёша пропустил урок и не понял задание. Как предложить помощь, чтобы он смог решить его сам?', 'Unterricht versäumt', 'Ljoscha hat eine Stunde versäumt und die Aufgabe nicht verstanden. Wie bieten wir Hilfe an, damit er sie selbst lösen kann?'],
    ['Рассыпались рисунки', 'У Веры рассыпались рисунки. Она собирает их одна. Что ты скажешь и что предложишь сделать?', 'Zeichnungen heruntergefallen', 'Veras Zeichnungen sind heruntergefallen. Sie sammelt sie allein ein. Was sagst du, und was bietest du an?'],
    ['Хочет участвовать', 'Илья хочет играть с ребятами. Ему неудобно дотянуться до середины стола. Как выяснить, что поможет?', 'Möchte mitmachen', 'Ilja möchte mitspielen. Er erreicht die Tischmitte nicht bequem. Wie finden wir heraus, was hilft?'],
    ['Первый способ не подошёл', 'Саша говорит: «Я всё ещё не понял правила». Что можно изменить в объяснении?', 'Der erste Weg passte nicht', 'Sascha sagt: „Ich habe die Regeln immer noch nicht verstanden.“ Was können wir an der Erklärung ändern?'],
];
foreach ($cards as $i => [$title, $text, $titleDe, $textDe]) {
    $id = 'friends-independent-card-'.($i + 1);
    $notes = $i < 4 ? 5 : 7;
    $hintRu = $i < 4 ? "Раздайте четыре разных сведения по одному участнику команды. Не показывайте все сведения сразу: каждый должен передать свою часть.\n\n" : '';
    $hintDe = $i < 4 ? "Verteilt vier verschiedene Informationen auf vier Teammitglieder. Nicht alle zugleich zeigen: Jeder bringt seinen Teil ein.\n\n" : '';
    $templates[] = ['slug' => $id, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru',
        'block' => ['id' => $id, 'type' => 'core.prompt', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => $text], 'de' => ['text' => $textDe]], 'config' => ['kind' => 'instruction', 'target' => $i < 4 ? 'group' : 'pair'], 'teacherNotes' => ['ru' => $hintRu.$raw['screens'][$notes]['notes'], 'de' => $hintDe.$de['screens'][$notes]['notes']]],
        'labels' => ['ru' => ['title' => $title, 'description' => $text], 'de' => ['title' => $titleDe, 'description' => $textDe]], 'attribution' => ['title' => $title, 'tags' => ['friends', 'help', 'prompt']]];
}

return ['id' => 'friends-starter-v1', 'sourceRevision' => 'friends-ru-de-starters-2026-10-02-v1', 'templates' => $templates];
