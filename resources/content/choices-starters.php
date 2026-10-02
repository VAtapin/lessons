<?php

declare(strict_types=1);

$source = require __DIR__.'/choices.php';
$raw = json_decode(file_get_contents(__DIR__.'/choices-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/choices-de.php';
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
        $block['teacherNotes'] = ['ru' => $raw['screens'][$index]['notes']."\n\nМой выбор затрагивает других. Голод — внешнее обстоятельство. Отец принимает младшего; решение старшего после приглашения не названо. Не обещайте отмены последствий или немедленного доверия. Личный лист и молитва добровольны.", 'de' => $de['screens'][$index]['notes']."\n\nMeine Entscheidung betrifft andere. Hungersnot ist ein äußerer Umstand. Vater nimmt den jüngeren auf; nächste Entscheidung des älteren bleibt offen. Folgen und Vertrauensverlust nicht für aufgehoben erklären. Persönliches Blatt und Gebet freiwillig."];
        $labels = [];
        foreach (['ru', 'de'] as $locale) {
            $description = $block['content'][$locale]['alt'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['text'];
            $suffix = $block['content'][$locale]['label'] ?? $block['content'][$locale]['question'] ?? $block['content'][$locale]['title'] ?? $block['content'][$locale]['alt'] ?? null;
            $title = $stage['content'][$locale]['title'];
            $labels[$locale] = ['title' => $suffix && $suffix !== $title ? $title.' · '.$suffix : $title, 'description' => mb_strlen($description) <= 200 ? $description : $title];
        }
        $templates[] = ['slug' => $block['id'], 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $block, 'labels' => $labels, 'attribution' => ['title' => $labels['ru']['title'], 'tags' => ['choices', 'help', str_replace('core.', '', $block['type'])]]];
    }
}

$cards = [
    ['Просит свою долю и уходит.', 'Лк. 15:12–13', 'Bittet um seinen Anteil und geht.', 'Lukas 15,12–13'],
    ['Растрачивает имущество.', 'Лк. 15:13', 'Verbraucht den Besitz.', 'Lukas 15,13'],
    ['В стране наступает голод.', 'Лк. 15:14', 'Hungersnot im Land.', 'Lukas 15,14'],
    ['Решает вернуться к отцу.', 'Лк. 15:17–19', 'Beschließt zum Vater zurückzukehren.', 'Lukas 15,17–19'],
    ['Встаёт и идёт к отцу.', 'Лк. 15:20', 'Steht auf und geht zum Vater.', 'Lukas 15,20'],
    ['Старший не хочет войти.', 'Лк. 15:28', 'Älterer will nicht eintreten.', 'Lukas 15,28'],
    ['Срок', 'Сейчас 18:00. Часть Ильи нужна к 19:00. Команде нужно 10 минут на общую проверку.', 'Frist', 'Jetzt 18:00. Iljas Teil bis 19:00. Team braucht zehn Minuten gemeinsame Prüfung.'],
    ['Объём', 'Илья может закончить свою часть за 40 минут. Без неё макет неполный.', 'Umfang', 'Ilja schafft seinen Teil in 40 Minuten. Ohne ihn ist das Modell unvollständig.'],
    ['Материалы', 'Готовые детали и материалы пока у Ильи. Передача и сообщение команде займут 5 минут.', 'Material', 'Fertige Teile und Materialien bei Ilja. Übergabe und Nachricht dauern fünf Minuten.'],
    ['Другие люди', 'Команда рассчитывает на договор. Игра с друзьями начинается в 18:30; позже можно присоединиться, если договориться.', 'Andere Menschen', 'Team vertraut auf Vereinbarung. Spiel beginnt 18:30; späterer Anschluss nach Absprache möglich.'],
    ['Списать или разобраться', 'Друг просит ответы: «Иначе ты мне не друг». Как обозначить границу и предложить помощь?', 'Abschreiben oder verstehen', 'Freund fordert Antworten: „Sonst bist du kein Freund.“ Wie Grenze und Hilfe anbieten?'],
    ['Чужое фото', 'Тебя просят переслать личный снимок одноклассника без его согласия. Что скажешь и предложишь?', 'Fremdes Foto', 'Persönliches Foto eines Mitschülers ohne Zustimmung weiterleiten? Was sagen und anbieten?'],
    ['Обещанная работа', 'Хочется уйти играть, а твоя часть проекта не готова. Что сообщишь команде?', 'Versprochene Arbeit', 'Spielen gehen wollen, Projektteil nicht fertig. Was dem Team mitteilen?'],
    ['Отказаться от общего дела', 'Хочешь сменить занятие, но другие ждут твою работу сегодня. Как обсудишь изменение договора?', 'Gemeinsame Aufgabe verlassen', 'Tätigkeit wechseln wollen, andere erwarten Arbeit heute. Wie neuen Vertrag besprechen?'],
];
foreach ($cards as $i => [$title, $text, $titleDe, $textDe]) {
    $id = 'choices-independent-card-'.($i + 1);
    $group = $i >= 6 && $i < 10;
    $notes = $i < 6 ? 3 : ($group ? 5 : 7);
    $hintRu = $group ? "Раздайте четыре разных условия по одному участнику. Каждый сообщает свою часть; не показывайте все сведения сразу. Сначала первый план, новое обстоятельство только на следующем этапе.\n\n" : '';
    $hintDe = $group ? "Vier verschiedene Bedingungen auf vier Teammitglieder verteilen. Jeder berichtet seinen Teil; nicht alles zugleich zeigen. Zuerst erster Plan, neuer Umstand erst in der nächsten Phase.\n\n" : '';
    $templates[] = ['slug' => $id, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru',
        'block' => ['id' => $id, 'type' => 'core.prompt', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => $i < 6 ? $title.' '.$text : $text], 'de' => ['text' => $i < 6 ? $titleDe.' '.$textDe : $textDe]], 'config' => ['kind' => 'instruction', 'target' => $group ? 'group' : 'pair'], 'teacherNotes' => ['ru' => $hintRu.$raw['screens'][$notes]['notes'], 'de' => $hintDe.$de['screens'][$notes]['notes']]],
        'labels' => ['ru' => ['title' => $title, 'description' => $text], 'de' => ['title' => $titleDe, 'description' => $textDe]], 'attribution' => ['title' => $title, 'tags' => ['choices', 'responsibility', 'prompt']]];
}

return ['id' => 'choices-starter-v1', 'sourceRevision' => 'choices-ru-de-starters-2026-10-03-v1', 'templates' => $templates];
