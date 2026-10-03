<?php

declare(strict_types=1);

$raw = json_decode(file_get_contents(__DIR__.'/anger-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/anger-de.php';
$cards = require __DIR__.'/anger-cards.php';
$version = 'd090a417-3038-4092-9d8b-e72a0a84bf41';
$base = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$make = static fn ($id, $type, $ru, $german, $config = [], $solution = null) => array_filter(['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $type === 'presentation' ? $ru + ['modes' => []] : $ru, 'de' => $type === 'presentation' ? $german + ['modes' => []] : $german], 'config' => $config, 'solution' => $solution], static fn ($v) => $v !== null);
$free = static fn ($id, $ru, $german) => $make($id, 'free-response', ['question' => $ru, 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $german, 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 500]);
$image = static fn (int $n): array => ['image' => ['assetId' => 'builtin-anger-'.$n, 'versionId' => 'builtin-anger-'.$n.'-v1']];
$lines = static function (int $n) use ($raw): array {
    $text = $raw['slides'][$n - 1]['slide'];
    if ($n === 1) {
        return ['title' => $raw['title'], 'text' => implode("\n", array_slice($text, 2, 3)), 'source' => $text[5]];
    }
    $title = array_shift($text);
    array_pop($text);
    $source = null;
    foreach ($text as $i => $line) {
        if (str_starts_with($line, 'Быт. ')) {
            $source = $line;
            unset($text[$i]);
        }
    }

    return array_filter(['title' => $title, 'text' => implode("\n", $text), 'source' => $source], static fn ($v) => $v !== null);
};
$ruVerses = [];
preg_match_all('/\*\*(Быт\. 4:|Еф\. 4:)(\d+)\.\*\* (.*)/u', $raw['bible'], $ruMatches, PREG_SET_ORDER);
foreach ($ruMatches as $verse) {
    $ruVerses[($verse[1] === 'Быт. 4:' ? 'gen-' : 'eph-').$verse[2]] = str_replace('**', '', $verse[0]);
}
$deVerses = [];
preg_match_all('/^(Genesis|Epheser) 4,(\d+)\. (.*)$/m', $de['bible'], $deMatches, PREG_SET_ORDER);
foreach ($deMatches as $verse) {
    $deVerses[($verse[1] === 'Genesis' ? 'gen-' : 'eph-').$verse[2]] = $verse[0];
}
$excerpt = static fn ($verses, $numbers) => implode("\n", array_map(static fn ($n) => $verses[$n], $numbers));
$plain = static fn ($text) => preg_replace('/(?m)^#{1,6} /', '', str_replace('**', '', $text));
$stages = [];
foreach ($raw['screens'] as $i => $screen) {
    $n = $i + 1;
    $id = 'anger-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ru = $lines($n);
    $german = $de['slides'][$i];
    $blocks = [$make($id.'-scene', 'presentation', $ru, $german, $base + ['scene' => $n === 1 ? 'cover' : 'story'])];
    $picture = $make($id.'-image', 'image', ['alt' => $ru['title'], 'caption' => ''], ['alt' => $german['title'], 'caption' => ''], ['fit' => 'contain']);
    $picture['media'] = $image($n);
    $blocks[] = $picture;
    if ($n === 3) {
        $roles = static fn ($texts) => array_map(static fn ($text, $j) => ['roleId' => 'role-'.($j + 1), 'text' => $text], $texts, array_keys($texts));
        $blocks[] = $make($id.'-roles', 'roles', ['text' => 'Роли для сценки', 'roles' => $roles(['Рассказчик', 'Каин', 'Авель'])], ['text' => 'Rollen für das Spiel', 'roles' => $roles(['Erzähler', 'Kain', 'Abel'])], ['capacities' => ['role-1' => 1, 'role-2' => 1, 'role-3' => 1]]);
        foreach ([[2, ['gen-3', 'gen-4', 'gen-5'], 'Дары', 'Gaben', 'Быт. 4:3–5'], [3, ['gen-6', 'gen-7'], 'Предупреждение', 'Warnung', 'Быт. 4:6–7'], [13, ['gen-8'], 'Поле', 'Feld', 'Быт. 4:8'], [4, ['gen-9', 'gen-10', 'gen-11', 'gen-12', 'gen-13', 'gen-14', 'gen-15', 'gen-16'], 'Вопрос и последствия', 'Frage und Folgen', 'Быт. 4:9–16']] as [$pictureNumber, $numbers, $label, $labelDe, $reference]) {
            $frame = $make($id.'-frame-'.$pictureNumber, 'presentation', ['title' => $label, 'label' => $label, 'hideLabel' => 'Скрыть кадр: '.$label, 'text' => $excerpt($ruVerses, $numbers), 'source' => $reference], ['title' => $labelDe, 'label' => $labelDe, 'hideLabel' => 'Bild ausblenden: '.$labelDe, 'text' => $excerpt($deVerses, $numbers), 'source' => str_replace(['Быт. ', ':'], ['Genesis ', ','], $reference)], array_replace($base, ['kind' => 'reveal']));
            $frame['media'] = $image($pictureNumber);
            $blocks[] = $frame;
        }
    } elseif ($n === 4) {
        $items = static fn ($locale) => array_map(static fn ($c, $j) => ['itemId' => 'event-'.($j + 1), 'text' => $c[$locale === 'ru' ? 0 : 2].' · '.$c[$locale === 'ru' ? 1 : 3]], array_slice($cards, 3, 4), [0, 1, 2, 3]);
        $blocks[] = $make($id.'-order', 'sequence', ['question' => 'Восстановите порядок событий', 'items' => $items('ru'), 'emptyText' => '…'], ['question' => 'Reihenfolge der Ereignisse', 'items' => $items('de'), 'emptyText' => '…'], ['allowRepeat' => true], ['itemIds' => ['event-2', 'event-4', 'event-1', 'event-3']]);
        $review = static fn ($locale) => implode(' → ', array_map(static fn ($j) => $cards[$j][$locale === 'ru' ? 0 : 2], [4, 6, 3, 5]));
        $blocks[] = $make($id.'-check', 'presentation', ['label' => 'Проверяем вместе', 'hideLabel' => 'Скрыть разбор', 'text' => $review('ru')], ['label' => 'Gemeinsam prüfen', 'hideLabel' => 'Besprechung ausblenden', 'text' => $review('de')], array_replace($base, ['kind' => 'reveal', 'reviewBlockId' => $id.'-order']));
        $blocks[] = $make($id.'-reading', 'presentation', ['label' => 'Читаем Еф. 4:26–32', 'hideLabel' => 'Скрыть чтение', 'text' => $excerpt($ruVerses, array_map(static fn ($n) => 'eph-'.$n, range(26, 32))), 'source' => 'Еф. 4:26–32'], ['label' => 'Epheser 4,26–32 lesen', 'hideLabel' => 'Lesung ausblenden', 'text' => $excerpt($deVerses, array_map(static fn ($n) => 'eph-'.$n, range(26, 32))), 'source' => 'Epheser 4,26–32'], array_replace($base, ['kind' => 'reveal']));
    } elseif ($n === 5) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Шесть карточек на пару. Разложите по трём группам. Выберите сигнал, который можно заметить до поступка, и доступное действие вместо вреда. У каждого сигналы могут быть свои.'], ['text' => 'Sechs Karten pro Paar. In drei Gruppen ordnen. Ein vor der Handlung bemerkbares Signal und verfügbare Handlung statt Schaden wählen. Signale können bei jedem anders sein.'], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 6) {
        $blocks[] = $free($id.'-pause', 'Моя фраза паузы и время возвращения:', 'Mein Pausensatz und meine Rückkehrzeit:');
        $blocks[] = $free($id.'-action', 'Что я сделаю во время паузы:', 'Was ich während der Pause tue:');
    } elseif ($n === 7) {
        $blocks[] = $free($id.'-first-plan', 'Первый шаг, чтобы остановить ответный вред: Что известно и что нужно проверить: К кому обратимся и как предложим разговор:', 'Erster Schritt, um Schaden als Antwort zu stoppen: Was bekannt ist und was geprüft werden muss: An wen wir uns wenden und wie wir ein Gespräch vorschlagen:');
    } elseif ($n === 8) {
        $blocks[] = $make($id.'-pair', 'prompt', ['text' => 'Один читает контекст. Другой называет конкретное событие, своё чувство и просьбу. Собеседник: «Я услышал, что…». Затем поменяйтесь ролями и выберите ещё один случай.'], ['text' => 'Einer liest den Kontext. Der andere nennt konkretes Ereignis, eigenes Gefühl und Bitte. Gegenüber: „Ich habe gehört, dass…“. Dann Rollen wechseln und einen weiteren Fall wählen.'], ['kind' => 'instruction', 'target' => 'pair']);
    } elseif ($n === 9) {
        $blocks[] = $make($id.'-condition', 'prompt', ['text' => 'Новое условие: Рома признал: «Это я написал. Разозлился после проигрыша». Другой участник зовёт Мишу на драку. Что добавим в план?'], ['text' => 'Neue Bedingung: Roma gab zu: „Das habe ich geschrieben. Ich war nach der Niederlage wütend“. Ein anderer Teilnehmer ruft Mischa zu einer Schlägerei. Was ergänzen wir im Plan?'], ['kind' => 'instruction', 'target' => 'group']);
        $blocks[] = $free($id.'-second-plan', 'Второй план: граница, помощь и исправление вреда:', 'Zweiter Plan: Grenze, Hilfe und Wiedergutmachung des Schadens:');
    } elseif ($n === 12) {
        $blocks[] = $free($id.'-faith', 'Каин мог выбрать иной поступок, когда…', 'Kain hätte anders handeln können, als…');
        $blocks[] = $free($id.'-step', 'Когда я разозлюсь, мой первый шаг…', 'Wenn ich wütend werde, ist mein erster Schritt…');
        $blocks[] = $make($id.'-closing', 'presentation', ['title' => 'Гнев и мой следующий поступок', 'text' => 'Я отвечаю за свой поступок и могу начать добрый шаг.', 'eyebrow' => 'Урок завершён', 'source' => 'Быт. 4; Еф. 4:26–32'], ['title' => 'Wut und meine nächste Handlung', 'text' => 'Ich bin für meine Handlung verantwortlich und kann einen guten Schritt beginnen.', 'eyebrow' => 'Die Stunde ist beendet', 'source' => 'Genesis 4; Epheser 4,26–32'], array_replace($base, ['kind' => 'closing']));
    }
    $readingRu = in_array($n, [2, 3, 4], true) ? "\n\n".$raw['bible']."\n\n".$raw['roleplay'] : '';
    $readingDe = in_array($n, [2, 3, 4], true) ? "\n\n".$de['bible']."\n\n".$de['roleplay'] : '';
    $stageCards = array_filter($cards, static fn ($card) => $card[4] === $i);
    $cardsRu = implode("\n\n", array_map(static fn ($card) => $card[0]."\n".$card[1], $stageCards));
    $cardsDe = implode("\n\n", array_map(static fn ($card) => $card[2]."\n".$card[3], $stageCards));
    $stages[] = ['id' => $id, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $plain($screen['notes']."\n\n".$raw['teacherPreparation'].$readingRu."\n\n".$cardsRu)], 'de' => ['title' => $de['screens'][$i]['title'], 'notes' => $de['screens'][$i]['notes']."\n\n".$de['preparation'].$readingDe."\n\n".$cardsDe]], 'config' => ['layout' => 'material-above-task', 'durationSeconds' => $screen['durationMinutes'] * 60, 'openTasks' => true, 'theme' => 'graphite'], 'blocks' => $blocks];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'anger-file-')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}
$ruPlan = $raw['passport']."\n\n".$raw['plan']."\n\n".$raw['script']."\n\n".$raw['teacherPreparation']."\n\n".$raw['handout'];
$dePlan = $de['title']."\n12–15 Jahre · 45 Minuten · 8–24 Teilnehmende, Paare und Viererteams.\nZiel: Wut und Handlung unterscheiden; Kains Warnung und Verantwortung verstehen, Pause und Bitte üben und konkreten guten Schritt wählen.\n".implode("\n\n", array_map(static fn ($s) => $s['title']."\n".$s['notes'], $de['screens']))."\n\n".$de['preparation']."\n\n".$de['roleplay']."\n\n".$de['bible']."\n\n".(require __DIR__.'/anger-handout-de.php');

return [
    'sourceRevision' => 'anger-ru-de-2026-10-03-v1', 'materialId' => 'c090a417-3038-4092-9d8b-e72a0a84bf41', 'versionId' => $version, 'ownerKey' => 'e090a417-3038-4092-9d8b-e72a0a84bf41', 'slug' => 'ya-razozlilsya-chto-teper',
    'metadata' => ['translations' => ['ru' => ['title' => $raw['title'], 'description' => trim($raw['description'])], 'de' => ['title' => $de['title'], 'description' => $de['description']]], 'age' => ['11-14', '15+'], 'topic' => ['bible'], 'audience' => ['sunday-school', 'school', 'group', 'family'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45, 'cover' => $image(1)['image'],
        'details' => ['ru' => ['goals' => ['Различать гнев и поступок; объяснить предупреждение Каину и последствия его выбора; попробовать паузу и просьбу; выбрать шаг без вреда.'], 'materials' => ['Быт. 4:1–16 и Еф. 4:26–32 полностью из приложения, презентация, ножницы и ручки.', 'Шесть страниц раздатки: три роли, четыре события, шесть сигналов/действий, четыре командных сведения, четыре ситуации для пар и личный лист.', 'Корзинка с бумажными плодами, карточка овцы, стул; телефон с выключенным экраном или ручка.'], 'devices' => 'Пары и команды работают очно и на бумаге. Устройства не обязательны; короткие ответы отправляются через Enter.', 'conditions' => '12–15 лет. 45 минут. Пары и команды по четыре. Личный лист остаётся у подростка; личные случаи и молитва добровольны. Сначала Быт. 4:1–16, сразу сценка, затем обсуждение и Еф. 4:26–32. Авель погиб; последствия для Каина и защита от новой мести сохранены. Бог не изображён; вид знамения не придуман.'], 'de' => ['goals' => ['Wut und Handlung unterscheiden; Warnung an Kain und Folgen seiner Entscheidung erklären; Pause und Bitte üben; Schritt ohne Schaden wählen.'], 'materials' => ['Genesis 4,1–16 und Epheser 4,26–32 vollständig aus dem Anhang, Präsentation, Schere und Stifte.', 'Sechs Seiten: drei Rollen, vier Ereignisse, sechs Signale/Handlungen, vier Teaminformationen, vier Paarsituationen und persönliches Blatt.', 'Korb mit Papierfrüchten, Schafkarte, Stuhl; Telefon mit ausgeschaltetem Bildschirm oder Stift.'], 'devices' => 'Paare und Teams arbeiten vor Ort und auf Papier. Geräte sind nicht erforderlich; kurze Antworten mit Enter.', 'conditions' => '12–15 Jahre. 45 Minuten. Paare und Viererteams. Persönliches Blatt bleibt beim Jugendlichen; persönliche Fälle und Gebet freiwillig. Zuerst Genesis 4,1–16, sofort Spiel, dann Besprechung und Epheser 4,26–32. Abel starb; Folgen für Kain und Schutz vor neuer Rache bleiben. Gott wird nicht dargestellt; Aussehen des Zeichens wird nicht erfunden.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $raw['title']], 'de' => ['title' => $de['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => $ruPlan], 'de' => ['plan' => $dePlan]], 'files' => $files]],
];
