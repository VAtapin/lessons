<?php

declare(strict_types=1);

// Independent teaching routines: no story, named character, expected belief or linked block.
$rows = [
    ['opening', 'text', 'Начало встречи', 'Zum Beginn',
        "Сегодня мы будем изучать новую тему, задавать вопросы и обмениваться мыслями.\nСлушаем друг друга до конца. Можно попросить объяснение и не рассказывать о личном.",
        "Heute erkunden wir ein Thema, stellen Fragen und tauschen Gedanken aus.\nWir hören einander zu. Du darfst um eine Erklärung bitten und Persönliches für dich behalten.",
        'Назовите тему и цель встречи устно. Предложите участникам добавить одно правило общения.',
        'Nennen Sie Thema und Ziel der Begegnung. Lassen Sie die Gruppe eine Gesprächsregel ergänzen.'],
    ['observe', 'prompt', 'Наблюдение и вопрос', 'Beobachtung und Frage',
        'Рассмотрите предложенный материал. Что вы замечаете? Отделите наблюдение от предположения. Какой вопрос хочется задать?',
        'Betrachtet das vorgestellte Material. Was fällt euch auf? Unterscheidet Beobachtungen von Vermutungen. Welche Frage möchtet ihr stellen?',
        'Перед этим блоком покажите изображение, предмет или короткий текст по вашей теме. Принимайте разные вопросы, не подсказывайте единственный вывод.',
        'Zeigen Sie vorher ein Bild, einen Gegenstand oder einen kurzen Text zu Ihrem Thema. Lassen Sie verschiedene Fragen zu, ohne eine einzige Schlussfolgerung vorzugeben.'],
    ['pair', 'prompt', 'Обмен мыслями в паре', 'Gedankenaustausch zu zweit',
        'Сначала подумайте над вопросом ведущего самостоятельно. Затем по очереди поделитесь мыслью с партнёром. Найдите одно сходство и одно различие в ответах.',
        'Denkt zuerst allein über die Frage der Leitung nach. Tauscht dann nacheinander eure Gedanken aus. Findet eine Gemeinsamkeit und einen Unterschied.',
        'Сформулируйте один открытый вопрос по теме. Дайте время подумать и по минуте каждому. Можно работать самостоятельно; личный рассказ не обязателен.',
        'Stellen Sie eine offene Frage zum Thema. Geben Sie Zeit zum Nachdenken und jeweils eine Minute zum Sprechen. Einzelarbeit ist möglich; Persönliches muss niemand erzählen.'],
    ['explain', 'prompt', 'Объясни своими словами', 'Mit eigenen Worten erklären',
        'Выберите одну мысль из изученного материала. Объясните её своими словами и приведите пример. Что ещё нужно уточнить?',
        'Wählt einen Gedanken aus dem behandelten Material. Erklärt ihn mit eigenen Worten und nennt ein Beispiel. Was muss noch geklärt werden?',
        'Укажите материал, к которому относится задание. Помогайте уточнять смысл, не оценивайте красоту речи и не требуйте личных признаний.',
        'Benennen Sie das Material für diese Aufgabe. Helfen Sie beim Klären des Inhalts. Bewerten Sie weder sprachliche Gewandtheit noch persönliche Offenheit.'],
    ['questions', 'free-response', 'Вопрос ведущему', 'Eine Frage an die Leitung',
        'Какой вопрос по сегодняшней теме вы хотели бы задать? Не указывайте имена и личные сведения.',
        'Welche Frage möchtet ihr zum heutigen Thema stellen? Nennt keine Namen oder persönlichen Angaben.',
        'Откройте приём ответов. Прочитайте вопросы приватно, объедините похожие и обсудите их. Публикуйте только отдельно выбранную анонимную редакцию.',
        'Öffnen Sie die Antworten. Lesen Sie die Fragen zunächst privat, bündeln Sie ähnliche Fragen und besprechen Sie sie. Veröffentlichen Sie nur eine gesondert ausgewählte anonymisierte Fassung.'],
    ['reflection', 'free-response', 'Главная мысль', 'Ein wichtiger Gedanke',
        'Какую мысль из сегодняшней встречи вы хотите сохранить? Можно ответить одним предложением.',
        'Welchen Gedanken aus der heutigen Begegnung möchtet ihr behalten? Ein Satz genügt.',
        'Ответ добровольный, правильного варианта нет. Не требуйте обещаний. Для общего экрана отдельно подготовьте и опубликуйте анонимную редакцию.',
        'Die Antwort ist freiwillig; es gibt keine richtige Option. Verlangen Sie keine Versprechen. Bereiten Sie für die gemeinsame Ansicht gesondert eine anonymisierte Fassung vor.'],
    ['compare', 'prompt', 'Сравнить два подхода', 'Zwei Ansätze vergleichen',
        'Сравните два предложенных подхода. Что у них общего? Чем они отличаются? При каких условиях каждый может быть полезен?',
        'Vergleicht die beiden vorgestellten Ansätze. Was haben sie gemeinsam? Worin unterscheiden sie sich? Unter welchen Bedingungen kann jeder hilfreich sein?',
        'Сначала представьте два подхода по своей теме. Просите опираться на их условия и содержание. Не объявляйте один подход лучшим без обсуждения критериев.',
        'Stellen Sie zunächst zwei Ansätze zu Ihrem Thema vor. Achten Sie auf Bedingungen und Inhalt. Besprechen Sie die Kriterien, bevor Sie einen Ansatz bewerten.'],
    ['pause', 'text', 'Пауза для размышления', 'Zeit zum Nachdenken',
        "Сделаем короткую паузу.\nПодумайте, что уже понятно и к чему хочется вернуться. Можно записать мысль для себя.",
        "Wir machen eine kurze Pause.\nÜberlegt, was schon klar ist und worauf ihr zurückkommen möchtet. Ihr könnt einen Gedanken für euch selbst notieren.",
        'Дайте 30–60 секунд тишины. Личные записи не собирайте. После паузы предложите добровольно задать уточняющий вопрос.',
        'Geben Sie 30–60 Sekunden Ruhe. Sammeln Sie persönliche Notizen nicht ein. Laden Sie anschließend freiwillig zu klärenden Fragen ein.'],
];
$templates = [];
foreach ($rows as [$slug, $type, $ruTitle, $deTitle, $ru, $de, $ruNotes, $deNotes]) {
    $content = match ($type) {
        'text' => ['ru' => ['title' => $ruTitle, 'text' => $ru, 'source' => ''], 'de' => ['title' => $deTitle, 'text' => $de, 'source' => '']],
        'free-response' => ['ru' => ['question' => $ru], 'de' => ['question' => $de]],
        default => ['ru' => ['text' => $ru], 'de' => ['text' => $de]],
    };
    $config = match ($type) {
        'text' => ['presentation' => 'paragraphs'],
        'free-response' => ['allowRepeat' => true, 'maxLength' => 500],
        default => ['kind' => 'instruction', 'target' => $slug === 'pair' ? 'pair' : 'group'],
    };
    $templates[] = ['slug' => $slug, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru',
        'block' => ['id' => 'universal-'.$slug, 'type' => 'core.'.$type, 'schemaVersion' => $type === 'text' ? 2 : 1,
            'content' => $content, 'config' => $config, 'teacherNotes' => ['ru' => $ruNotes, 'de' => $deNotes]],
        'labels' => ['ru' => ['title' => $ruTitle, 'description' => $ru], 'de' => ['title' => $deTitle, 'description' => $de]],
        'attribution' => ['title' => $ruTitle, 'tags' => ['universal', $type], 'rightsBasis' => 'ai_generated',
            'author' => 'lessons.atapin.de', 'source' => 'Independent teaching routines, universal-starter-v1',
            'usageRights' => 'Reusable and adaptable teaching routines for lessons.atapin.de.']];
}

return ['id' => 'universal-starter-v1', 'sourceRevision' => 'universal-ru-de-2026-10-03-v1', 'templates' => $templates];
