<?php

declare(strict_types=1);

// A new immutable release: wording from OLD, with the original teacher plan kept private.
$source = require __DIR__.'/kto-moi-blizhnii-v2.php';
$source['sourceRevision'] = 'neighbor-ru-de-2026-10-01-v3';
$source['versionId'] = 'a87bb188-0d90-4f52-a9c8-23f30189e64a';
$source['document']['id'] = $source['versionId'];
$stages = &$source['document']['stages'];
$presentation = fn (string $id, string $kind, array $ru, array $de): array => [
    'id' => $id, 'type' => 'core.presentation', 'schemaVersion' => 1,
    'content' => ['ru' => $ru + ['modes' => []], 'de' => $de + ['modes' => []]],
    'config' => ['kind' => $kind, 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8],
];
$sceneContent = [
    [
        ['title' => 'Кто мой ближний?', 'eyebrow' => 'Интерактивный урок · 45 минут', 'text' => 'Притча о милосердном самарянине', 'source' => 'Лк 10:25–37', 'label' => 'Начать урок'],
        ['title' => 'Wer ist mein Nächster?', 'eyebrow' => 'Interaktiver Unterricht · 45 Minuten', 'text' => 'Das Gleichnis vom barmherzigen Samariter', 'source' => 'Lk 10,25–37', 'label' => 'Unterricht beginnen'],
    ], [
        ['title' => 'Человек на дороге', 'eyebrow' => 'Сценка · Лк 10:30', 'text' => 'Путник шёл из Иерусалима в Иерихон. Разбойники ограбили и избили его. Он остался ждать помощи.', 'label' => 'Роли', 'subtitle' => 'Кого позовём первым?', 'actionLabel' => 'Следующая роль', 'resetLabel' => 'Сбросить роли', 'restartLabel' => 'Первая роль', 'resetText' => 'Начинаем новый набор ролей'],
        ['title' => 'Ein Mensch am Wegrand', 'eyebrow' => 'Rollenspiel · Lk 10,30', 'text' => 'Ein Reisender ging von Jerusalem nach Jericho. Räuber beraubten und schlugen ihn. Er blieb zurück und wartete auf Hilfe.', 'label' => 'Rollen', 'subtitle' => 'Wen rufen wir zuerst?', 'actionLabel' => 'Nächste Rolle', 'resetLabel' => 'Rollen zurücksetzen', 'restartLabel' => 'Erste Rolle', 'resetText' => 'Wir beginnen mit neuen Rollen'],
    ], [
        ['title' => 'Священник', 'text' => 'Какую причину можно придумать, чтобы пройти мимо?'],
        ['title' => 'Der Priester', 'text' => 'Welchen Grund könnte man sich ausdenken, um vorbeizugehen?'],
    ], [
        ['title' => 'Левит', 'text' => 'Как ты объяснишь, почему не остановился?'],
        ['title' => 'Der Levit', 'text' => 'Wie erklärst du, warum du nicht stehen geblieben bist?'],
    ], [
        ['title' => 'Самарянин', 'text' => 'Ты тоже мог пройти мимо. Почему ты решил помочь?'],
        ['title' => 'Der Samariter', 'text' => 'Du hättest auch vorbeigehen können. Warum hast du dich entschieden zu helfen?'],
    ], [
        ['title' => 'Что мешает помочь?', 'eyebrow' => 'Обсуждение', 'text' => 'Нажмите на ответ класса и спросите: «Что всё-таки можно было сделать?»', 'feedback' => 'Мог ли самарянин найти себе такое же оправдание?'],
        ['title' => 'Was hindert uns am Helfen?', 'eyebrow' => 'Gespräch', 'text' => 'Wählen Sie eine Antwort der Klasse und fragen Sie: „Was hätte man trotzdem tun können?“', 'feedback' => 'Hätte der Samariter dieselbe Ausrede finden können?'],
    ], [
        ['title' => 'Путь помощи', 'eyebrow' => 'Соберите по порядку', 'text' => 'Выбирайте поступки самарянина один за другим.', 'feedback' => 'Первый шаг начинается с внимания.'],
        ['title' => 'Der Weg der Hilfe', 'eyebrow' => 'In die richtige Reihenfolge bringen', 'text' => 'Wählt die Handlungen des Samariters eine nach der anderen.', 'feedback' => 'Der erste Schritt beginnt mit Aufmerksamkeit.'],
    ], [
        ['title' => 'Кто оказался ближним?', 'eyebrow' => 'Лк 10:36–37', 'text' => 'Выберите героя и объясните ответ его поступками.', 'feedback' => 'По каким поступкам мы это понимаем?'],
        ['title' => 'Wer wurde zum Nächsten?', 'eyebrow' => 'Lk 10,36–37', 'text' => 'Wählt die Figur und begründet eure Antwort mit ihren Handlungen.', 'feedback' => 'An welchen Handlungen erkennen wir das?'],
    ], [
        ['title' => 'Новенький на перемене', 'eyebrow' => 'Школьная ситуация 1', 'text' => 'Он сидит один. Что можно ему сказать или предложить?'],
        ['title' => 'Neu in der Pause', 'eyebrow' => 'Schulsituation 1', 'text' => 'Er sitzt allein. Was könnte man ihm sagen oder vorschlagen?'],
    ], [
        ['title' => 'Рассыпавшиеся книги', 'eyebrow' => 'Школьная ситуация 2', 'text' => 'Младший ученик уронил книги. Как подойти и предложить помощь?'],
        ['title' => 'Heruntergefallene Bücher', 'eyebrow' => 'Schulsituation 2', 'text' => 'Ein jüngerer Schüler hat seine Bücher fallen lassen. Wie könnte man auf ihn zugehen und Hilfe anbieten?'],
    ], [
        ['title' => 'Не берут в игру', 'eyebrow' => 'Школьная ситуация 3', 'text' => 'Одноклассник остался в стороне. Как помочь ему присоединиться, не начиная ссоры?'],
        ['title' => 'Nicht mitspielen dürfen', 'eyebrow' => 'Schulsituation 3', 'text' => 'Ein Mitschüler steht abseits. Wie kann man ihm helfen mitzumachen, ohne einen Streit anzufangen?'],
    ], [
        ['title' => 'На этой неделе я могу помочь…', 'eyebrow' => 'Личное решение', 'text' => 'Один посильный поступок. Поделиться решением можно по желанию.', 'quote' => '«Иди, и ты поступай так же»'],
        ['title' => 'Diese Woche kann ich helfen …', 'eyebrow' => 'Eine eigene Entscheidung', 'text' => 'Eine Handlung, die ihr selbst leisten könnt. Wer möchte, darf seine Entscheidung teilen.', 'quote' => '„Geh und handle genauso.“'],
    ],
];
// Replace expository duplicates, keeping the original interactive identities and answers.
$remove = ['intro-text', 'traveler-text', 'samaritan-question', 'barriers-discussion', 'week-reflection'];
foreach ($stages as $index => &$stage) {
    $stage['blocks'] = array_values(array_filter($stage['blocks'], fn (array $block): bool => ! in_array($block['id'], $remove, true)));
    if ($index < 12) {
        $scene = $presentation('neighbor-scene-'.($index + 1), 'scene', ...$sceneContent[$index]);
        $scene['config']['scene'] = ['cover', 'story', 'question', 'question', 'question', 'discussion', 'journey', 'choice', 'scenario', 'scenario', 'scenario', 'decision'][$index];
        if (in_array($index, [1, 2, 3, 4, 8, 9, 10], true)) {
            $scene['config']['imageSide'] = in_array($index, [1, 3, 9], true) ? 'right' : 'left';
        }
        array_unshift($stage['blocks'], $scene);
    }
}
unset($stage);
$update = function (string $id, array $ru, array $de) use (&$stages): void {
    foreach ($stages as &$stage) {
        foreach ($stage['blocks'] as &$block) {
            if ($block['id'] === $id) {
                $block['content']['ru'] = array_replace($block['content']['ru'], $ru);
                $block['content']['de'] = array_replace($block['content']['de'], $de);
            }
        }
        unset($block);
    }
    unset($stage);
};
$update('intro-signals', ['readyLabel' => 'Готов ✦', 'questionLabel' => 'Есть вопрос'], ['readyLabel' => 'Bereit ✦', 'questionLabel' => 'Ich habe eine Frage']);
$update('priest-excuse', ['label' => 'Ответ класса', 'placeholder' => 'Запишите ответ своими словами', 'submitLabel' => 'Добавить'], ['label' => 'Antwort der Klasse', 'placeholder' => 'Schreibt die Antwort mit eigenen Worten', 'submitLabel' => 'Hinzufügen']);
$update('levite-excuse', ['label' => 'Ответ класса', 'placeholder' => 'Запишите ещё одну причину', 'submitLabel' => 'Добавить'], ['label' => 'Antwort der Klasse', 'placeholder' => 'Schreibt einen weiteren Grund', 'submitLabel' => 'Hinzufügen']);
$update('samaritan-reveal', ['label' => 'Открыть поступок героя', 'hideLabel' => 'Скрыть поступок героя'], ['label' => 'Die Handlung der Figur aufdecken', 'hideLabel' => 'Die Handlung der Figur verbergen']);
$update('barriers-poll', ['question' => 'Что чаще всего мешает помочь?'], ['question' => 'Was hindert uns am häufigsten am Helfen?']);
$update('excuses-board', ['text' => 'Нажмите на ответ класса и спросите: «Что всё-таки можно было сделать?»', 'emptyText' => 'Здесь появятся объяснения священника и левита.', 'feedback' => 'Мог ли самарянин найти себе такое же оправдание?'], ['text' => 'Wählen Sie eine Antwort der Klasse und fragen Sie: „Was hätte man trotzdem tun können?“', 'emptyText' => 'Hier erscheinen die Erklärungen des Priesters und des Leviten.', 'feedback' => 'Hätte der Samariter dieselbe Ausrede finden können?']);
$update('help-sequence', ['question' => 'Выбирайте поступки самарянина один за другим.', 'emptyText' => '…', 'feedback' => 'Первый шаг начинается с внимания.', 'label' => 'Собрать заново', 'submitLabel' => 'Проверяем вместе'], ['question' => 'Wählt die Handlungen des Samariters eine nach der anderen.', 'emptyText' => '…', 'feedback' => 'Der erste Schritt beginnt mit Aufmerksamkeit.', 'label' => 'Neu zusammenstellen', 'submitLabel' => 'Gemeinsam prüfen']);
$update('help-sequence', [
    'feedbackFirstWrong' => 'С чего начинается помощь? Сначала нужно заметить человека.',
    'feedbackWrong' => 'Этот поступок будет позже. Что сделал самарянин перед ним?',
    'feedbackCorrect' => 'Верно. Теперь шаг {step}.',
    'feedbackComplete' => 'Помощь продолжилась даже после отъезда самарянина.',
    'reviewLabel' => 'Идёт общая проверка',
], [
    'feedbackFirstWrong' => 'Womit beginnt Hilfe? Zuerst muss man den Menschen bemerken.',
    'feedbackWrong' => 'Diese Handlung kommt später. Was tat der Samariter davor?',
    'feedbackCorrect' => 'Richtig. Jetzt Schritt {step}.',
    'feedbackComplete' => 'Die Hilfe ging auch nach der Abreise des Samariters weiter.',
    'reviewLabel' => 'Die gemeinsame Prüfung läuft',
]);
$original = require __DIR__.'/kto-moi-blizhnii.php';
$stages[6]['blocks'][] = $original['document']['stages'][6]['blocks'][0];
foreach ($stages[6]['blocks'] as &$block) {
    if ($block['type'] === 'core.sequence') {
        foreach (['ru', 'de'] as $locale) {
            foreach ($block['content'][$locale]['items'] as &$item) {
                $item['icon'] = ['notice' => '◉', 'approach' => '●', 'help' => '+', 'bring' => '⌂', 'continue-care' => '∞'][$item['itemId']];
            }
            unset($item);
        }
    }
}
unset($block);
$update('neighbor-choice', ['question' => 'Кто стал ближним?'], ['question' => 'Wer wurde zum Nächsten?']);
$update('neighbor-reveal', ['text' => 'Самарянин стал ближним, потому что проявил милосердие делом.', 'quote' => '«Иди, и ты поступай так же»', 'source' => 'Лк 10:37', 'feedback' => 'Он увидел пострадавшего, но прошёл мимо. Кто остановился и помог?'], ['text' => 'Der Samariter wurde zum Nächsten, weil er durch sein Handeln Barmherzigkeit zeigte.', 'quote' => '„Geh und handle genauso.“', 'source' => 'Lk 10,37', 'feedback' => 'Er sah den Verletzten, ging aber vorbei. Wer blieb stehen und half?']);
foreach (['newcomer', 'books', 'game'] as $index => $id) {
    $update($id.'-words', ['placeholder' => 'Напишите точную фразу', 'submitLabel' => 'Отправить'], ['placeholder' => 'Schreibt die genauen Worte', 'submitLabel' => 'Senden']);
    $update($id.'-discussion', ['text' => $sceneContent[8 + $index][0]['text'], 'label' => 'Запустить 1 минуту для пары'], ['text' => $sceneContent[8 + $index][1]['text'], 'label' => 'Eine Minute für die Partnerarbeit starten']);
    foreach ($stages[8 + $index]['blocks'] as &$block) {
        if ($block['id'] === $id.'-discussion') {
            foreach (['ru', 'de'] as $locale) {
                $block['content'][$locale]['modes'][0]['label'] = $locale === 'ru' ? 'Как пройти мимо?' : 'Wie vorbeigehen?';
                $block['content'][$locale]['modes'][1]['label'] = $locale === 'ru' ? 'Как помочь?' : 'Wie helfen?';
                $block['content'][$locale]['modes'][0]['title'] = $locale === 'ru' ? 'Оправдание бездействия' : 'Eine Ausrede für das Nichtstun';
                $block['content'][$locale]['modes'][1]['title'] = $locale === 'ru' ? 'Конкретная помощь' : 'Konkrete Hilfe';
            }
        }
    }
    unset($block);
}
$update('week-promise', ['label' => 'Моё решение', 'placeholder' => 'Например: приглашу новенького играть вместе', 'submitLabel' => 'Добавить на путь'], ['label' => 'Meine Entscheidung', 'placeholder' => 'Zum Beispiel: Ich lade das neue Kind zum Mitspielen ein', 'submitLabel' => 'Zum Weg hinzufügen']);
$update('promise-board', ['text' => 'Каждый добрый поступок становится следующим шагом.', 'emptyText' => 'Каждый добрый поступок становится следующим шагом.'], ['text' => 'Jede gute Tat wird zum nächsten Schritt.', 'emptyText' => 'Jede gute Tat wird zum nächsten Schritt.']);
$update('summary-word', ['placeholder' => 'Например: внимание', 'submitLabel' => 'Отправить'], ['placeholder' => 'Zum Beispiel: Aufmerksamkeit', 'submitLabel' => 'Senden']);
$stages[12]['blocks'] = array_values(array_filter($stages[12]['blocks'], fn (array $block): bool => $block['id'] !== 'summary-text'));
array_unshift($stages[12]['blocks'], $presentation('summary-takeaway', 'summary', [
    'eyebrow' => 'Итог урока', 'title' => 'Ближним становятся', 'text' => 'Не вопрос «кто достоин моей помощи?», а решение: «чьим ближним могу стать я?»',
    'items' => [['itemId' => 'notice', 'label' => 'Увидеть', 'text' => 'заметить человека и его нужду'], ['itemId' => 'approach', 'label' => 'Подойти', 'text' => 'не прятаться за удобным оправданием'], ['itemId' => 'help', 'label' => 'Помочь', 'text' => 'сделать конкретный посильный шаг']],
    'quote' => '«Иди, и ты поступай так же»', 'source' => 'Лк 10:37', 'subtitle' => 'Назовите одним словом, что вы уносите с этого урока.',
], [
    'eyebrow' => 'Was nehmen wir mit?', 'title' => 'Zum Nächsten wird man', 'text' => 'Nicht die Frage „Wer verdient meine Hilfe?“, sondern die Entscheidung: „Wem kann ich zum Nächsten werden?“',
    'items' => [['itemId' => 'notice', 'label' => 'Sehen', 'text' => 'den Menschen und seine Not bemerken'], ['itemId' => 'approach', 'label' => 'Hingehen', 'text' => 'sich nicht hinter einer passenden Ausrede verstecken'], ['itemId' => 'help', 'label' => 'Helfen', 'text' => 'einen konkreten Schritt tun, den man leisten kann']],
    'quote' => '„Geh und handle genauso.“', 'source' => 'Lk 10,37', 'subtitle' => 'Nennt mit einem Wort, was ihr aus dieser Stunde mitnehmt.',
]));
$stages[12]['blocks'][] = $presentation('lesson-closing', 'closing', [
    'eyebrow' => 'Урок завершён', 'title' => 'Милосердие начинается с первого шага', 'text' => 'Спасибо за честные ответы, внимание друг к другу и готовность помочь.',
    'quote' => '«Иди, и ты поступай так же»', 'source' => 'Лк 10:37', 'label' => 'Вернуться к началу',
], [
    'eyebrow' => 'Der Unterricht ist beendet', 'title' => 'Barmherzigkeit beginnt mit dem ersten Schritt', 'text' => 'Danke für eure ehrlichen Antworten, eure Aufmerksamkeit füreinander und eure Bereitschaft zu helfen.',
    'quote' => '„Geh und handle genauso.“', 'source' => 'Lk 10,37', 'label' => 'Zum Anfang zurückkehren',
]);
// Notes quote the plan attached to this release, retaining its full methodical instructions.
$plan = $source['document']['documentation']['content'];
$mapping = [1, 2, 3, 3, 4, 5, 6, 7, 8, 8, 8, 8, 8];
foreach (['ru', 'de'] as $locale) {
    $sections = preg_split('/\n(?=[1-8]\. )/u', $plan[$locale]['plan']);
    foreach ($mapping as $index => $section) {
        $stages[$index]['content'][$locale]['notes'] = $stages[$index]['content'][$locale]['notes']."\n\n".trim($sections[$section]);
    }
}
unset($stages);

return $source;
