<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/lazarus-source.json'), true, flags: JSON_THROW_ON_ERROR);
$version = 'd100a422-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $content, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => $type === 'presentation' ? array_map(static fn ($c) => $c + ['modes' => []], $content) : $content, 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$both = static fn ($ru, $de, $key = 'text') => ['ru' => [$key => $ru], 'de' => [$key => $de]];
$plain = static fn ($text) => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $text));
$image = static fn ($n) => ['image' => ['assetId' => 'builtin-lazarus-'.$n, 'versionId' => 'builtin-lazarus-'.$n.'-v1']];
$free = static fn ($id, $ru, $de) => $make($id, 'free-response', ['ru' => ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], 'de' => ['question' => $de, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden']], ['allowRepeat' => true, 'maxLength' => 1000]);
$reveal = static fn ($id, $ru, $de, $textRu, $textDe, $extra = []) => $make($id, 'presentation', ['ru' => ['title' => $ru, 'label' => $ru, 'hideLabel' => 'Скрыть: '.$ru, 'text' => $plain($textRu)], 'de' => ['title' => $de, 'label' => $de, 'hideLabel' => 'Ausblenden: '.$de, 'text' => $textDe]], array_replace($base, ['kind' => 'reveal'], $extra));
$cardText = static fn ($index, $locale) => $raw['cards'][$index][$locale]['title']."\n".$raw['cards'][$index][$locale]['text'];
$stages = [];
foreach ($raw['ru']['stages'] as $i => $stage) {
    $n = $i + 1;
    $id = 'lazarus-step-'.sprintf('%02d', $n);
    $scene = $notes = [];
    foreach (['ru', 'de'] as $locale) {
        $slide = $raw[$locale]['slides'][$i];
        $lines = $locale === 'ru' ? array_slice($slide['text'], $n === 1 ? 3 : 1, $n === 1 ? null : -1) : [$slide['text']];
        $scene[$locale] = ['title' => $raw[$locale]['stages'][$i]['title'], 'text' => implode("\n", $lines)];
        $notes[$locale] = $plain($raw[$locale]['stages'][$i]['notes']."\n\n".$raw[$locale]['preparation']);
        if (in_array($n, [2, 3], true)) {
            $notes[$locale] .= "\n\n".$plain($raw[$locale]['bible']."\n\n".$raw[$locale]['roleplay']);
        }
        foreach ($raw['cards'] as $card) {
            if ($card['stage'] === $i) {
                $notes[$locale] .= "\n\n".$card[$locale]['title']."\n".$card[$locale]['text'];
            }
        }
    }
    $blocks = [$make($id.'-scene', 'presentation', $scene, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['ru' => ['alt' => $scene['ru']['title'], 'caption' => ''], 'de' => ['alt' => $scene['de']['title'], 'caption' => '']], ['fit' => 'contain']);
    $picture['media'] = $image($raw['ru']['slides'][$i]['image']);
    $blocks[] = $picture;
    if ($n === 2) {
        $ruVerses = preg_split('/(?=\*\*Ин\. 11:\d+\.\*\*)/u', $raw['ru']['bible']);
        $deVerses = preg_split('/(?=Johannes 11,\d+\. )/u', $raw['de']['bible']);
        foreach (range(1, 45, 8) as $from) {
            $to = min($from + 7, 45);
            $blocks[] = $reveal($id.'-reading-'.$from, 'Ин. 11:'.$from.'–'.$to, 'Johannes 11,'.$from.'–'.$to, $ruVerses[0].implode("\n", array_slice($ruVerses, $from, $to - $from + 1)), $deVerses[0].implode("\n", array_slice($deVerses, $from, $to - $from + 1)));
        }
    } elseif ($n === 3) {
        $roles = [];
        foreach (['ru', 'de'] as $locale) {
            $roles[$locale] = ['text' => $locale === 'ru' ? 'Шесть ролей; помощник представляет присутствующих.' : 'Sechs Rollen; Helfer vertritt die Anwesenden.', 'roles' => array_map(static fn ($j) => ['roleId' => 'role-'.($j + 1), 'text' => $raw['cards'][$j][$locale]['title']], range(0, 5))];
        }
        $blocks[] = $make($id.'-roles', 'roles', $roles, ['capacities' => array_fill_keys(array_map(static fn ($j) => 'role-'.$j, range(1, 6)), 1)]);
        foreach ($raw['ru']['frames'] as $j => $frame) {
            $deFrame = $raw['de']['frames'][$j];
            $block = $reveal($id.'-frame-'.($j + 1), $frame['title'], $deFrame['title'], $frame['text'], $deFrame['text']);
            $block['media'] = $image([2, 3, 4, 5, 6, 8][$j]);
            $blocks[] = $block;
        }
    } elseif ($n === 4) {
        $blocks[] = $free($id.'-christ', 'Что любовь, слёзы и слова Христа открывают о Нём?', 'Was offenbaren Christi Liebe, Tränen und Worte über Ihn?');
    } elseif ($n === 5) {
        $content = [];
        foreach (['ru', 'de'] as $locale) {
            $content[$locale] = ['question' => $locale === 'ru' ? 'Восстановите порядок рассказа' : 'Reihenfolge wiederherstellen', 'items' => array_map(static fn ($j) => ['itemId' => 'event-'.($j - 5), 'text' => $cardText($j, $locale)], range(6, 9)), 'emptyText' => '…'];
        }
        $blocks[] = $make($id.'-order', 'sequence', $content, ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-4', 'event-3', 'event-1']]);
        $blocks[] = $reveal($id.'-check', 'Проверяем рассказ', 'Geschichte prüfen', 'Просьба сестёр → Два дня и дорога → Встреча и печаль → Молитва и выход Лазаря', 'Bitte der Schwestern → Zwei Tage und der Weg → Begegnung und Trauer → Gebet und Lazarus kommt heraus', ['reviewBlockId' => $id.'-order']);
        $blocks[] = $free($id.'-days', 'К чему относятся два дня и четыре дня?', 'Worauf beziehen sich zwei und vier Tage?');
        $blocks[] = $free($id.'-christ', 'Что слова и действия Христа открывают о Нём?', 'Was offenbaren Christi Worte und Taten über Ihn?');
    } elseif (in_array($n, [6, 7, 8], true)) {
        $target = $n === 8 ? 'group' : 'pair';
        $blocks[] = $make($id.'-instruction', 'prompt', $both($n === 8 ? 'Четыре сведения: услышьте каждого и сохраните первый план. Новое сообщение пока у ведущего.' : 'Читайте все условия, обсудите слова поддержки и смените роли. Не обещайте неизвестный результат.', $n === 8 ? 'Vier Informationen: alle hören, ersten Plan behalten. Neue Nachricht zunächst nur bei Leitung.' : 'Alle Bedingungen lesen, Unterstützung besprechen, Rollen wechseln. Unbekannten Ausgang nicht versprechen.'), ['kind' => 'instruction', 'target' => $target]);
        $range = match ($n) {
            6 => range(14, 19), 7 => range(10, 13), 8 => range(20, 23)
        };
        foreach ($range as $j) {
            $card = $raw['cards'][$j];
            $blocks[] = $reveal($id.'-card-'.$j, $card['ru']['title'], $card['de']['title'], $card['ru']['text'], $card['de']['text']);
        }
        if ($n === 8) {
            $blocks[] = $free($id.'-first-plan', 'Первый план: известное, неизвестное, поддержка сегодня и то, что не можем обещать', 'Erster Plan: Bekanntes, Unbekanntes, Unterstützung heute, was wir nicht garantieren können');
        }
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', $both('Новое сообщение Даниила: «Мне одиноко. Давай поговорим сегодня после уроков. Поможешь попросить учителя поддержать меня?» Сохраните первый план.', 'Neue Nachricht von Daniil: Mir ist einsam. Reden wir heute nach der Schule? Hilfst du mir, den Lehrer um Unterstützung zu bitten? Ersten Plan behalten.'), ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план: время, согласие, помощь учителя, ответ без гарантии результата', 'Zweiter Plan: Zeit, Zustimmung, Lehrerhilfe, Antwort ohne Ergebnisgarantie');
        $support = $make($id.'-support', 'image', ['ru' => ['alt' => 'Обращение за поддержкой', 'caption' => ''], 'de' => ['alt' => 'Unterstützung suchen', 'caption' => '']], ['fit' => 'contain']);
        $support['media'] = $image(13);
        $blocks[] = $support;
    } elseif ($n === 10) {
        $blocks[] = $free($id.'-help', 'Мой честный ответ другу и время посильной помощи', 'Meine ehrliche Antwort und Zeit für machbare Hilfe');
        $blocks[] = $free($id.'-prayer', 'Моя просьба к Богу без назначенного срока', 'Meine Bitte an Gott ohne gesetzte Frist');
    } elseif ($n === 11) {
        $blocks[] = $make($id.'-personal', 'prompt', $both('Личный лист остаётся у тебя. Можно выбрать вымышленный случай. Не нужно публично рассказывать об утрате.', 'Persönliches Blatt bleibt bei dir. Erfundener Fall möglich. Keine öffentliche Verlustgeschichte nötig.'), ['kind' => 'reflection', 'target' => 'class']);
        $blocks[] = $reveal($id.'-prayer', 'Молитва', 'Gebet', $raw['ru']['prayer']."\nАвторская молитва. Можно молча слушать.", $raw['de']['prayer']."\nEigens verfasstes Gebet. Still zuhören ist möglich.");
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-christ', 'В истории Лазаря я увидел во Христе…', 'In Lazarus Geschichte sehe ich an Christus …');
        $blocks[] = $free($id.'-step', 'В ожидании я могу…', 'Während des Wartens kann ich …');
        $blocks[] = $make($id.'-closing', 'presentation', ['ru' => ['title' => $scene['ru']['title'], 'text' => 'Христос любит, разделяет скорбь и говорит о воскресении и жизни.', 'source' => 'Ин. 11:1–45'], 'de' => ['title' => $scene['de']['title'], 'text' => 'Christus liebt, teilt Trauer und spricht von Auferstehung und Leben.', 'source' => 'Johannes 11,1–45']], array_replace($base, ['kind' => 'closing']));
    }
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $stage['title'], 'notes' => $notes['ru']], 'de' => ['title' => $raw['de']['stages'][$i]['title'], 'notes' => $notes['de']]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $stage['minutes'] * 60, 'openTasks' => true, 'theme' => 'ocean'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'lazarus-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => $file['locale']];
    }
}
$plans = [];
foreach (['ru', 'de'] as $locale) {
    $data = $raw[$locale];
    $handout = $locale === 'ru' ? $data['handout'] : implode("\n\n", array_map(static fn ($page) => $page['title']."\n".$page['instruction']."\n".implode("\n\n", array_map(static fn ($c) => $c['title']."\n".$c['text'], $page['cards']))."\n".implode("\n", $page['fields'])."\n".$page['prayer'], $data['handout']));
    $plans[$locale] = ['plan' => $data['passport']."\n\n".$data['preparation']."\n\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $data['stages']))."\n\n".$data['roleplay']."\n\n".$data['bible']."\n\n".$handout];
}

return [
    'sourceRevision' => 'lazarus-ru-de-2026-10-04-v1', 'materialId' => 'c100a422-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e100a422-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'pochemu-bog-ne-otvechaet-srazu',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['ru']['title'], 'description' => trim($raw['ru']['description'])], 'de' => ['title' => $raw['de']['title'], 'description' => $raw['de']['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'], 'details' => ['ru' => ['goals' => ['Увидеть любовь Христа, Его слёзы и власть над смертью; молиться и поддерживать в ожидании без выдуманного срока ответа.'], 'materials' => ['Полное Ин. 11:1–45, 12 слайдов, шесть страниц раздатки, ручки, свободная полоса ткани и условный камень.', 'Шесть ролей, четыре события, четыре фразы, шесть случаев, четыре командных сведения, личный лист.'], 'devices' => 'Пары и команды работают на бумаге. Устройства необязательны.', 'conditions' => '12–15 лет. 45 минут. Полное чтение, сразу сценка, затем обсуждение. Личный лист остаётся у подростка, молитва добровольна.'], 'de' => ['goals' => ['Christi Liebe, Tränen und Macht über den Tod sehen; beten und unterstützen ohne erfundene Antwortfrist.'], 'materials' => ['Vollständiges Johannes 11,1–45, 12 Folien, sechs Seiten Arbeitsmaterial, Stifte, lose Stoffbahn und symbolischer Stein.', 'Sechs Rollen, vier Ereignisse, vier Sätze, sechs Fälle, vier Teaminformationen, persönliches Blatt.'], 'devices' => 'Paare und Teams auf Papier. Geräte freiwillig.', 'conditions' => '12–15 Jahre, 45 Minuten. Lesung, sofort Rollenspiel, danach Gespräch. Persönliches Blatt bleibt bei Jugendlichen, Gebet freiwillig.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['ru']['title']], 'de' => ['title' => $raw['de']['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => $plans, 'files' => $files]],
];
