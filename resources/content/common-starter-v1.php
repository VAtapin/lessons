<?php

declare(strict_types=1);

// Editorial starters, not a complete lesson or a new block engine.
$block = function (string $id, string $type, array $ru, array $de, array $config = [], ?array $solution = null, ?array $notes = null, int $schema = 1): array {
    $result = ['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => $schema, 'content' => ['ru' => $ru, 'de' => $de], 'config' => $config];
    if ($solution !== null) {
        $result['solution'] = $solution;
    }
    if ($notes !== null) {
        $result['teacherNotes'] = ['ru' => $notes[0], 'de' => $notes[1]];
    }

    return $result;
};
$entry = fn (string $slug, array $titles, array $descriptions, array $tags, array $content): array => [
    'slug' => $slug, 'locales' => ['ru', 'de'], 'defaultLocale' => 'ru', 'block' => $content,
    'labels' => ['ru' => ['title' => $titles[0], 'description' => $descriptions[0]], 'de' => ['title' => $titles[1], 'description' => $descriptions[1]]],
    'attribution' => ['title' => $titles[0], 'tags' => ['starter', ...$tags],
        'author' => 'Original editorial text prepared with OpenAI for lessons.atapin.de.',
        'source' => 'resources/content/common-starter-v1.php; project owner requested reusable RU/DE starters.',
        'rightsBasis' => 'ai_generated', 'usageRights' => 'Reusable editorial starter for the lessons.atapin.de platform; adapt to your group.'],
];
$options = fn (array $rows, int $locale): array => array_map(fn ($row) => ['optionId' => $row[0], 'text' => $row[$locale]], $rows);
$items = fn (array $rows, int $locale): array => array_map(fn ($row) => ['itemId' => $row[0], 'text' => $row[$locale]], $rows);
$safe = [['adult', 'Позвать взрослого и остаться в безопасном месте', 'Einen Erwachsenen holen und an einem sicheren Ort bleiben'],
    ['alone', 'Самому пойти в опасное место', 'Allein an den gefährlichen Ort gehen'], ['ignore', 'Никому не сказать', 'Niemandem etwas sagen']];
$barriers = [['hurry', 'Спешка', 'Zeitdruck'], ['fear', 'Страх', 'Angst'], ['uncertainty', 'Не знаю, как помочь', 'Ich weiß nicht, wie ich helfen kann'], ['attention', 'Не замечаю нужду другого', 'Ich bemerke die Not des anderen nicht']];
$steps = [['notice', 'Заметить нужду', 'Die Not bemerken'], ['ask', 'Подойти безопасно и спросить', 'Sicher hingehen und fragen'], ['help', 'Предложить посильную помощь', 'Mögliche Hilfe anbieten']];
$templates = [
    $entry('welcome', ['Начало встречи', 'Zum Beginn'], ['Короткое приветствие и договорённость о спокойном обсуждении.', 'Eine Begrüßung und eine Vereinbarung für das gemeinsame Gespräch.'], ['welcome', 'text'],
        $block('starter-welcome', 'text', ['title' => 'Добро пожаловать!', 'text' => "Сегодня будем замечать, думать и помогать.\nСначала каждый может ответить сам, затем обсудим вместе. Можно задать вопрос или отказаться от личного рассказа.", 'source' => ''], ['title' => 'Willkommen!', 'text' => "Heute wollen wir aufmerksam sein, nachdenken und helfen.\nZuerst darf jede und jeder selbst antworten, danach sprechen wir gemeinsam. Fragen sind willkommen; niemand muss Persönliches erzählen.", 'source' => ''], schema: 2,
            notes: ['Назовите тему встречи и объясните порядок ответов. Не требуйте раскрывать личные истории. Приветствие можно адаптировать без изменения других заготовок.', 'Nennen Sie das Thema und erklären Sie, wie geantwortet wird. Verlangen Sie keine persönlichen Geschichten. Passen Sie die Begrüßung an Ihre Gruppe an.'])),
    $entry('mercy-discussion', ['Милосердие в обычной жизни', 'Barmherzigkeit im Alltag'], ['Вопрос для общего разговора о внимании и посильной помощи.', 'Eine Gesprächsfrage über Aufmerksamkeit und mögliche Hilfe.'], ['discussion', 'mercy'],
        $block('starter-mercy-discussion', 'prompt', ['text' => 'Как заметить, что другому нужна помощь? Назовите небольшой поступок, который может поддержать человека.'], ['text' => 'Wie merken wir, dass jemand Hilfe braucht? Nennt eine kleine Handlung, die einen Menschen unterstützen kann.'], ['kind' => 'discussion', 'target' => 'class'],
            notes: ['Обсуждайте вымышленные примеры без имён. Различайте внимание, согласие человека на помощь и безопасность. При серьёзной опасности нужна помощь взрослого.', 'Besprechen Sie erfundene Beispiele ohne Namen. Achten Sie auf Aufmerksamkeit, Zustimmung und Sicherheit. Bei ernster Gefahr braucht es einen Erwachsenen.'])),
    $entry('concrete-help', ['Какие слова помогают?', 'Welche Worte helfen?'], ['Личный письменный ответ с конкретным предложением помощи.', 'Eine eigene schriftliche Antwort mit einem konkreten Hilfsangebot.'], ['free-response', 'mercy'],
        $block('starter-concrete-help', 'free-response', ['question' => 'Человек рядом затрудняется. Что вы скажете, чтобы предложить посильную помощь?'], ['question' => 'Jemand neben dir hat Schwierigkeiten. Was sagst du, um mögliche Hilfe anzubieten?'], ['allowRepeat' => true, 'maxLength' => 300],
            notes: ['Откройте ответы и дайте время подумать. Сначала текст видит ведущий. Одобрение не публикует его: для общего экрана отдельно выберите анонимную редакцию и публикацию. Не оценивайте ребёнка по формулировке.', 'Öffnen Sie die Antworten und geben Sie Zeit. Zuerst sieht nur die Leitung den Text. Freigabe und Veröffentlichung sind getrennt; wählen Sie für den gemeinsamen Bildschirm eine anonyme Fassung. Bewerten Sie nicht das Kind.'])),
    $entry('safe-help', ['Помощь без опасности', 'Sicher helfen'], ['Выбор первого безопасного действия в опасной ситуации.', 'Die erste sichere Handlung in einer gefährlichen Situation auswählen.'], ['single-choice', 'safety'],
        $block('starter-safe-help', 'single-choice', ['question' => 'Ребёнок видит, что помощь нужна в опасном месте. Что сделать сначала?', 'options' => $options($safe, 1)], ['question' => 'Ein Kind sieht, dass an einem gefährlichen Ort Hilfe nötig ist. Was soll es zuerst tun?', 'options' => $options($safe, 2)], solution: ['optionId' => 'adult'],
            notes: ['Сначала личный выбор, затем close/reveal. Поясните: помогать не значит рисковать собой. Позвать взрослого — настоящее действие помощи. При необходимости обсудите принятый у вас порядок вызова экстренной помощи.', 'Erst selbst wählen, dann schließen und aufdecken. Erklären Sie: Helfen bedeutet nicht, sich selbst zu gefährden. Einen Erwachsenen holen ist echte Hilfe. Besprechen Sie bei Bedarf den örtlichen Ablauf für einen Notruf.'])),
    $entry('help-barriers', ['Что мешает остановиться?', 'Was hält uns vom Helfen ab?'], ['Опрос мнений без правильного варианта и автоматической оценки.', 'Eine Meinungsumfrage ohne richtige Option oder automatische Bewertung.'], ['poll', 'reflection'],
        $block('starter-help-barriers', 'poll', ['question' => 'Что чаще мешает заметить нужду другого и помочь?', 'options' => $options($barriers, 1)], ['question' => 'Was hindert uns häufig daran, die Not anderer zu bemerken und zu helfen?', 'options' => $options($barriers, 2)], ['allowRepeat' => true],
            notes: ['У опроса нет правильного ответа. После close/reveal обсуждайте только общий результат; не просите участников объяснять свой выбор публично.', 'Es gibt keine richtige Antwort. Besprechen Sie nach dem Aufdecken nur das Gruppenergebnis; niemand muss die eigene Wahl öffentlich erklären.'])),
    $entry('pair-conversation', ['Минута разговора в парах', 'Eine Minute zu zweit'], ['Инструкция для обмена конкретными словами и предложениями.', 'Eine Anleitung zum Austausch konkreter Worte und Vorschläge.'], ['instruction', 'pair'],
        $block('starter-pair-conversation', 'prompt', ['text' => 'Обсудите вдвоём: как можно предложить помощь? Один предлагает первые слова, другой добавляет посильное действие. Затем поменяйтесь ролями.'], ['text' => 'Besprecht zu zweit: Wie könnt ihr Hilfe anbieten? Eine Person schlägt erste Worte vor, die andere ergänzt eine mögliche Handlung. Danach tauscht ihr die Rollen.'], ['kind' => 'instruction', 'target' => 'pair'],
            notes: ['Запустите общий таймер на 60 секунд. Объединяйте детей добровольно; можно подумать самостоятельно. После минуты пригласите несколько добровольных ответов, без сравнения пар.', 'Starten Sie den allgemeinen Timer für 60 Sekunden. Die Zusammenarbeit ist freiwillig; allein nachdenken ist möglich. Laden Sie danach zu einigen freiwilligen Antworten ein, ohne die Paare zu vergleichen.'])),
    $entry('help-steps', ['От внимания к помощи', 'Von Aufmerksamkeit zu Hilfe'], ['Восстановление простой последовательности безопасной помощи.', 'Eine einfache Reihenfolge sicherer Hilfe wiederherstellen.'], ['sequence', 'mercy'],
        $block('starter-help-steps', 'sequence', ['question' => 'В каком порядке можно предложить помощь в обычной безопасной ситуации?', 'items' => $items([$steps[2], $steps[0], $steps[1]], 1)], ['question' => 'In welcher Reihenfolge können wir in einer normalen sicheren Situation Hilfe anbieten?', 'items' => $items([$steps[2], $steps[0], $steps[1]], 2)], ['allowRepeat' => true], ['itemIds' => ['notice', 'ask', 'help']],
            ['Откройте задание. Порядок можно менять до закрытия. После reveal обсудите: внимание помогает понять нужду, вопрос учитывает желание человека, действие должно быть посильным. В опасной ситуации сначала нужна безопасность и взрослый.', 'Öffnen Sie die Aufgabe. Bis zum Schließen kann die Reihenfolge geändert werden. Besprechen Sie nach dem Aufdecken: Aufmerksamkeit erkennt die Not, eine Frage respektiert den Wunsch, die Handlung muss möglich sein. Bei Gefahr kommen Sicherheit und ein Erwachsener zuerst.'])),
    $entry('closing-word', ['Слово в конце встречи', 'Ein Wort zum Schluss'], ['Короткая добровольная рефлексия без автоматической оценки.', 'Eine kurze freiwillige Reflexion ohne automatische Bewertung.'], ['free-response', 'reflection'],
        $block('starter-closing-word', 'free-response', ['question' => 'Одно слово или короткая мысль, которую вы уносите с встречи.'], ['question' => 'Ein Wort oder ein kurzer Gedanke, den ihr aus der Begegnung mitnehmt.'], ['allowRepeat' => true, 'maxLength' => 100],
            notes: ['Ответ и его публикация добровольны. Не сравнивайте детей и не требуйте обещания. Для общего экрана публикуйте только отдельно одобренную анонимную редакцию.', 'Antwort und Veröffentlichung sind freiwillig. Vergleichen Sie die Kinder nicht und verlangen Sie kein Versprechen. Veröffentlichen Sie nur eine separat freigegebene anonyme Fassung.'])),
];
$scenes = [
    ['road', 'Дорога и путешественник', 'Der Weg und der Reisende', 'Дорога среди гор и путник вдали', 'Eine Straße im Gebirge und ein Reisender in der Ferne'],
    ['wounded', 'Человек у дороги', 'Ein Mensch am Wegrand', 'Пострадавший сидит у дороги, двое уходят', 'Ein verletzter Mann sitzt am Weg; zwei Männer gehen fort'],
    ['priest', 'Священник проходит мимо', 'Der Priester geht vorbei', 'Священник проходит мимо пострадавшего', 'Ein Priester geht an dem Verletzten vorbei'],
    ['levite', 'Левит проходит мимо', 'Der Levit geht vorbei', 'Левит проходит мимо человека у дороги', 'Ein Levit geht an dem Mann am Weg vorbei'],
    ['samaritan', 'Самарянин помогает', 'Der Samariter hilft', 'Самарянин перевязывает руку пострадавшему, рядом осёл', 'Ein Samariter verbindet den Arm des Verletzten; daneben steht ein Esel'],
    ['newcomer', 'Новенький на перемене', 'Neu in der Pause', 'Ребёнок один на скамье, остальные общаются', 'Ein Kind sitzt allein auf einer Bank, während die anderen sich unterhalten'],
    ['books', 'Рассыпавшиеся книги', 'Heruntergefallene Bücher', 'Ребёнок собирает книги с пола, рядом стоят другие дети', 'Ein Kind sammelt Bücher vom Boden; andere Kinder stehen daneben'],
    ['game', 'Не берут в игру', 'Nicht mitspielen dürfen', 'Дети играют, один ребёнок стоит в стороне', 'Kinder spielen; ein Kind steht abseits'],
];
foreach ($scenes as [$scene, $ruTitle, $deTitle, $ruAlt, $deAlt]) {
    $image = $block('starter-image-'.$scene, 'image', ['alt' => $ruAlt, 'caption' => ''], ['alt' => $deAlt, 'caption' => ''], ['fit' => 'contain']);
    $image['media'] = ['image' => ['assetId' => 'builtin-neighbor-'.$scene, 'versionId' => 'builtin-neighbor-'.$scene.'-v1']];
    $template = $entry('image-'.$scene, [$ruTitle, $deTitle], ['Готовый блок с закреплённой акварельной иллюстрацией из темы «Кто мой ближний?».', 'Ein fertiger Block mit einer versionierten Aquarellillustration aus „Wer ist mein Nächster?“'], ['image', 'neighbor'], $image);
    $template['attribution'] = ['title' => $ruTitle, 'tags' => ['starter', 'image', 'neighbor'],
        'author' => 'Creator not recorded; supplied by the project owner.',
        'source' => 'OLD/kto-moi-blizhnii/assets/scene-'.$scene.'.webp', 'rightsBasis' => 'permission',
        'usageRights' => 'Project owner explicitly approved reuse in lessons.atapin.de.'];
    $templates[] = $template;
}

return ['id' => 'common-starter-v1', 'sourceRevision' => 'common-starter-ru-de-2026-10-01-v1', 'templates' => $templates];
