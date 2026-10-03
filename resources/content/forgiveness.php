<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/forgiveness-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/forgiveness-de.php';
$version = 'd060a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 300]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-forgiveness-'.$n, 'versionId' => 'builtin-forgiveness-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => $text[2]."\n".$text[3], 'source' => $text[4]];
    }
    $title = array_shift($text);
    array_pop($text);
    $table = null;
    if ($n === 4) {
        $table = ['headers' => array_slice($text, 0, 2), 'rows' => [array_slice($text, 2, 2), array_slice($text, 4, 2)]];
        $text = array_slice($text, 6);
    }
    $source = null;
    foreach ($text as $i => $line) {
        if ($line === 'Мф. 18:21–35' || str_contains($line, 'Мф. 18:33')) {
            $source = $line;
            unset($text[$i]);
        }
    }

    return array_filter(['title' => $title, 'text' => implode("\n", array_filter($text, static fn ($v) => $v !== '')), 'source' => $source, 'table' => $table], static fn ($v) => $v !== null);
};
$items = static fn ($texts) => array_map(static fn ($text, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
$verses = static function (string $text, int $from, int $to): string {
    preg_match_all('/^(\d+) (.*)$/m', $text, $matches, PREG_SET_ORDER);

    return implode("\n", array_map(static fn ($v) => $v[0], array_filter($matches, static fn ($v) => (int) $v[1] >= $from && (int) $v[1] <= $to)));
};
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'forgiveness-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $tableRu = $ru['table'] ?? null;
    $tableDe = $german['table'] ?? null;
    unset($ru['table'], $german['table']);
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($n);
    $blocks[] = $picture;
    if ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для сценки', 'roles' => $roles(['Рассказчик', 'Царь', 'Первый должник', 'Товарищ-должник', 'Свидетель'])], ['text' => 'Rollen für das Spiel', 'roles' => $roles(['Erzähler', 'König', 'Erster Schuldner', 'Mitschuldner', 'Zeuge'])], ['capacities' => ['role-1' => 1, 'role-2' => 1, 'role-3' => 1, 'role-4' => 1, 'role-5' => 2]]);
        foreach ([[14, 23, 26, 'Начало расчёта', 'Beginn der Abrechnung'], [4, 27, 27, 'Царь прощает долг', 'Der König erlässt die Schuld'], [5, 28, 31, 'Отказ помиловать товарища', 'Verweigertes Erbarmen'], [13, 32, 35, 'Строгий ответ царя', 'Die ernste Antwort des Königs']] as [$pictureNumber, $from, $to, $label, $labelDe]) {
            $frame = $make($id.'-frame-'.$pictureNumber, 'presentation', ['title' => $label, 'label' => $label, 'hideLabel' => 'Скрыть кадр: '.$label, 'text' => $verses($raw['bible'], $from, $to), 'source' => 'Мф. 18:'.$from.'–'.$to], ['title' => $labelDe, 'label' => $labelDe, 'hideLabel' => 'Bild ausblenden: '.$labelDe, 'text' => $verses($de['bible'], $from, $to), 'source' => 'Matthäus 18,'.$from.'–'.$to], array_replace($base, ['kind' => 'reveal']));
            $frame['media'] = $image($pictureNumber);
            $blocks[] = $frame;
        }
    } elseif ($n === 4) {
        $blocks[] = $make($id.'-debts', 'presentation', $ru + ['label' => 'Сравните таблицу долгов', 'hideLabel' => 'Скрыть таблицу', 'table' => $tableRu], $german + ['label' => 'Schulden vergleichen', 'hideLabel' => 'Tabelle ausblenden', 'table' => $tableDe], array_replace($base, ['kind' => 'reveal']));
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок событий', 'items' => $items(['Получивший прощение требует долг и сажает товарища в темницу.', 'Царь прощает должнику весь долг.', 'Царь спрашивает должника о милости; звучит предупреждение Христа.', 'Царь начинает расчёт. Перед ним должник с огромным долгом.']), 'emptyText' => '…'], ['question' => 'Ereignisse ordnen', 'items' => $items(['Der Begnadigte fordert die Schuld und bringt den Mitschuldner ins Gefängnis.', 'Der König erlässt dem Schuldner die ganze Schuld.', 'Der König fragt nach Erbarmen; Christi Warnung erklingt.', 'Der König beginnt die Abrechnung. Vor ihm steht ein Mann mit großer Schuld.']), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-4', 'event-2', 'event-1', 'event-3']]);
        $blocks[] = $make($id.'-check', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => 'Сверьте по стихам.', 'items' => [['text' => 'Царь начинает расчёт. Перед ним должник с огромным долгом. · Мф. 18:23–25'], ['text' => 'Царь прощает должнику весь долг. · Мф. 18:26–27'], ['text' => 'Получивший прощение требует долг и сажает товарища в темницу. · Мф. 18:28–30'], ['text' => 'Царь спрашивает должника о милости; звучит предупреждение Христа. · Мф. 18:32–35']]], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => 'An den Versen prüfen.', 'items' => [['text' => 'Der König beginnt die Abrechnung. · Matthäus 18,23–25'], ['text' => 'Der König erlässt die ganze Schuld. · Matthäus 18,26–27'], ['text' => 'Der Begnadigte bringt den Mitschuldner ins Gefängnis. · Matthäus 18,28–30'], ['text' => 'Der König fragt nach Erbarmen; Christi Warnung. · Matthäus 18,32–35']]], array_replace($base, ['kind' => 'reveal', 'reviewBlockId' => $id.'-order']));
    } elseif ($n === 6) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Найдите чувство, шаги к прощению, восстановление отношений и месть. Объясните решение; обсудите случаи, которые связаны сразу с двумя понятиями.'], ['text' => 'Gefühl, Schritte zur Vergebung, Wiederherstellung der Beziehungen und Rache finden. Entscheidung erklären; Fälle mit Bezug zu zwei Begriffen besprechen.'], ['kind' => 'instruction', 'target' => 'pair']);
        $blocks[] = $make($id.'-concepts', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => 'Что можно выбрать, даже если боль пока остаётся?', 'items' => [['text' => 'Чувство: «Мне больно и я сержусь».'], ['text' => 'Прощение: «Я не хочу отвечать тебе унижением».'], ['text' => 'Доверие: «Посмотрю, выполняется ли наша договорённость».'], ['text' => 'Примирение: «Мы оба готовы разговаривать и менять поступки».'], ['text' => 'Месть: «Перешлю его промах, чтобы ему тоже было стыдно».'], ['text' => 'Помощь и границы: «Прошу прекратить насмешки и обращусь к учителю».']]], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => 'Was können wir wählen, wenn der Schmerz noch bleibt?', 'items' => [['text' => 'Gefühl: „Es tut mir weh und ich bin wütend.“'], ['text' => 'Vergebung: „Ich will dich nicht als Antwort demütigen.“'], ['text' => 'Vertrauen: „Ich schaue, ob unsere Absprache eingehalten wird.“'], ['text' => 'Versöhnung: „Wir beide sind bereit zu sprechen und Handlungen zu ändern.“'], ['text' => 'Rache: „Ich leite seinen Fehler weiter, damit er sich auch schämt.“'], ['text' => 'Hilfe und Grenzen: „Bitte beendet den Spott; ich wende mich an die Lehrkraft.“']]], array_replace($base, ['kind' => 'reveal']));
    } elseif ($n === 7) {
        $blocks[] = $make($id.'-team', 'prompt', ['text' => 'Как остановим распространение насмешки? Как узнаем, чего хочет Соня? У кого попросим помощь и о чём? Как уточним план после ответа Сони?'], ['text' => 'Wie stoppen wir die Verbreitung? Wie erfahren wir Sonjas Wunsch? Wen bitten wir um welche Hilfe? Wie ändern wir den Plan nach Sonjas Antwort?'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $make($id.'-sonya', 'presentation', ['label' => 'Ответ Сони', 'hideLabel' => 'Скрыть ответ Сони', 'text' => '«Мне важно, чтобы это перестали пересылать».', 'title' => 'Уточните его у Сони.'], ['label' => 'Sonjas Antwort', 'hideLabel' => 'Sonjas Antwort ausblenden', 'text' => '„Mir ist wichtig, dass das nicht mehr weitergeschickt wird.“', 'title' => 'Fragt Sonja, ob er passt.'], array_replace($base, ['kind' => 'reveal']));
    } elseif ($n === 8) {
        $blocks[] = $free($id.'-request', '«Когда ты…, мне было… Я прошу…»', '„Als du…, fühlte ich… Ich bitte dich…“');
    } elseif ($n === 9) {
        $blocks[] = $free($id.'-boundary', 'Как остановить вред? К кому обратиться?', 'Wie stoppen wir den Schaden? An wen wenden wir uns?');
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-parable', 'Что сделал царь? Как мог ответить его должник?', 'Was tat der König? Wie hätte sein Schuldner antworten können?');
        $blocks[] = $free($id.'-step', 'Назови один шаг к прощению сегодня.', 'Nenne einen Schritt zur Vergebung heute.');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Божия милость зовёт меня миловать ближнего.', 'text' => 'Я могу начать с отказа от мести, правдивого разговора и молитвы, даже когда обида ещё болит.', 'eyebrow' => 'Урок завершён', 'source' => 'Мф. 18:21–35'], ['title' => 'Gottes Barmherzigkeit ruft mich, mit meinem Nächsten Erbarmen zu haben.', 'text' => 'Ich kann mit dem Verzicht auf Rache, einem ehrlichen Gespräch und Gebet beginnen, auch wenn die Verletzung noch wehtut.', 'eyebrow' => 'Die Stunde ist beendet', 'source' => 'Matthäus 18,21–35'], array_replace($base, ['kind' => 'closing']));
    }
    foreach ($blocks as &$block) {
        foreach (['ru', 'de'] as $locale) {
            if (isset($block['content'][$locale]['items']) && $block['type'] === 'core.presentation') {
                $block['content'][$locale]['text'] .= "\n".implode("\n", array_column($block['content'][$locale]['items'], 'text'));
                unset($block['content'][$locale]['items']);
            }
        }
    }
    unset($block);
    $readingRu = in_array($n, [2, 3], true) ? "\n\n".$raw['bible'] : '';
    $readingDe = in_array($n, [2, 3], true) ? "\n\n".$de['bible'] : '';
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'copper'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'forgiveness-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–20 Teilnehmende, Paare und Viererteams.\nZiel: Gottes Barmherzigkeit und Vergebung gegenüber dem Nächsten verbinden, die Verletzung ehrlich benennen und einen ersten Schritt weg von Rache mit vernünftigen Grenzen wählen.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['bible']."\n\n".$de['handout'];

return [
    'sourceRevision' => 'forgiveness-ru-de-2026-10-03-v1', 'materialId' => 'c060a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e060a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'ya-ne-hochu-proshchat',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible', 'parables', 'mercy'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Помочь подросткам увидеть связь Божией милости и прощения ближнего, честно назвать обиду и выбрать первый шаг к отказу от мести с сохранением разумных границ.'], 'materials' => ['Библия или полный Мф. 18:21–35 из пособия, PowerPoint, экран, карандаши.', 'Шесть страниц иллюстрированной раздатки: пять ролей, четыре события, шесть понятий, четыре командных сведения, четыре ситуации для пар, личный шаг.', 'При отсутствии проектора используйте PNG как крупные распечатки.'], 'devices' => 'Пары и команды работают очно и на бумаге. Устройства не обязательны; короткие ответы отправляются через Enter.', 'conditions' => '12–15 лет. 45 минут. 8–20 участников, пары и команды по четыре. Личный лист остаётся у подростка. Личные истории и молитва добровольны. Не требуйте публичного объявления «я простил».'], 'de' => ['goals' => ['Gottes Barmherzigkeit und Vergebung gegenüber dem Nächsten verbinden, die Verletzung ehrlich benennen und einen ersten Schritt weg von Rache mit vernünftigen Grenzen wählen.'], 'materials' => ['Bibel oder vollständiger Matthäus 18,21–35, PowerPoint, Bildschirm, Stifte.', 'Sechs illustrierte Seiten: fünf Rollen, vier Ereignisse, sechs Begriffe, vier Teaminformationen, vier Paarsituationen, persönlicher Schritt.', 'Ohne Projektor PNG als große Ausdrucke verwenden.'], 'devices' => 'Paare und Teams arbeiten vor Ort und auf Papier. Geräte sind nicht erforderlich; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. 8–20 Teilnehmende, Paare und Viererteams. Persönliches Blatt bleibt beim Jugendlichen. Geschichten und Gebet freiwillig. Kein öffentliches „Ich habe vergeben“ verlangen.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
