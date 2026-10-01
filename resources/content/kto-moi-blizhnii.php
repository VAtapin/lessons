<?php

declare(strict_types=1);

// Content only: ordinary registry blocks, with an original RU/DE retelling of Luke 10:25–37.
// No legacy executable code or external full Bible translation is imported.
$translations = fn (array $ru, array $de): array => ['ru' => $ru, 'de' => $de];
$block = function (string $id, string $type, array $ru, array $de, array $config = [], ?array $solution = null, ?array $notes = null, int $schema = 1) use ($translations): array {
    $result = ['id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => $schema, 'content' => $translations($ru, $de), 'config' => $config];
    if ($solution !== null) {
        $result['solution'] = $solution;
    }
    if ($notes !== null) {
        $result['teacherNotes'] = ['ru' => $notes[0], 'de' => $notes[1]];
    }

    return $result;
};
$text = fn (string $id, string $ru, string $de, string $presentation = 'paragraphs'): array => $block($id, 'text',
    ['title' => '', 'text' => $ru, 'source' => 'Лк 10:25–37; авторское изложение'],
    ['title' => '', 'text' => $de, 'source' => 'Lukas 10,25–37; eigene Nacherzählung'], ['presentation' => $presentation], schema: 2);
$image = function (string $id, string $scene, string $ru, string $de) use ($block): array {
    $result = $block($id, 'image', ['alt' => $ru, 'caption' => ''], ['alt' => $de, 'caption' => '']);
    $result['media'] = ['image' => ['assetId' => 'builtin-neighbor-'.$scene, 'versionId' => 'builtin-neighbor-'.$scene.'-v1']];

    return $result;
};
$prompt = fn (string $id, string $ru, string $de, string $target = 'class', string $kind = 'discussion'): array => $block($id, 'prompt', ['text' => $ru], ['text' => $de], ['target' => $target, 'kind' => $kind]);
$free = fn (string $id, string $ru, string $de, array $notes, int $maxLength = 500): array => $block($id, 'free-response', ['question' => $ru], ['question' => $de], ['allowRepeat' => true, 'maxLength' => $maxLength], notes: $notes);
$items = function (array $rows, string $key, int $locale): array {
    return array_map(fn ($row) => [$key => $row[0], 'text' => $row[$locale]], $rows);
};
$stage = fn (string $id, string $ru, string $de, int $seconds, array $blocks, array $notes): array => [
    'id' => $id, 'content' => ['ru' => ['title' => $ru, 'notes' => $notes[0]], 'de' => ['title' => $de, 'notes' => $notes[1]]],
    'config' => ['durationSeconds' => $seconds, 'layout' => 'material-above-task'], 'blocks' => $blocks,
];
$roles = [['traveler', 'Путешественник', 'Reisender'], ['robber_1', 'Первый разбойник', 'Erster Räuber'],
    ['robber_2', 'Второй разбойник', 'Zweiter Räuber'], ['priest', 'Священник', 'Priester'],
    ['levite', 'Левит', 'Levit'], ['samaritan', 'Самарянин', 'Samariter']];
$motives = [['compassion', 'Сострадание', 'Mitgefühl'], ['reward', 'Награда', 'Belohnung'], ['curiosity', 'Любопытство', 'Neugier']];
$barriers = [['hurry', 'Спешка', 'Zeitdruck'], ['fear', 'Страх', 'Angst'], ['awkwardness', 'Неловкость', 'Unsicherheit'], ['indifference', 'Равнодушие', 'Gleichgültigkeit']];
$sequence = [['notice', 'Увидел нужду', 'Die Not sehen'], ['approach', 'Подошёл', 'Hingehen'], ['help', 'Помог', 'Helfen'],
    ['bring', 'Привёз в гостиницу', 'Zur Herberge bringen'], ['continue-care', 'Позаботился дальше', 'Für weitere Hilfe sorgen']];
$neighbors = [['priest', 'Священник', 'Priester'], ['levite', 'Левит', 'Levit'], ['samaritan', 'Самарянин', 'Samariter']];

return [
    'sourceRevision' => 'neighbor-ru-de-2026-10-01-v1',
    'materialId' => '59119d43-f464-469c-a18a-0a5c4a414fa1',
    'versionId' => '7b4c695a-1eb4-45e1-ae16-1f1cde8bdcb1',
    'ownerKey' => 'c8b3c4cd-a2cb-4895-9248-5f61bfd7b8de',
    'slug' => 'kto-moi-blizhnii',
    'metadata' => [
        'translations' => [
            'ru' => ['title' => 'Кто мой ближний?', 'description' => 'Притча о добром самарянине: прожить историю, сравнить оправдания с состраданием и найти конкретный способ помочь в школьной жизни. 13 этапов, роли, личные ответы и обсуждение.'],
            'de' => ['title' => 'Wer ist mein Nächster?', 'description' => 'Das Gleichnis vom barmherzigen Samariter: die Geschichte erleben, Ausreden und Mitgefühl vergleichen und konkrete Hilfe im Schulalltag finden. 13 Schritte mit Rollen, eigenen Antworten und Gespräch.'],
        ],
        // Editorial age guidance for this adaptation, not an assertion about the OLD source.
        'age' => ['8-10', '11-14'], 'topic' => ['bible', 'parables', 'mercy'], 'audience' => ['school', 'sunday-school', 'family', 'group', 'children'],
        'format' => ['lesson', 'interactive'], 'durationMinutes' => 45,
        'cover' => ['assetId' => 'builtin-neighbor-road', 'versionId' => 'builtin-neighbor-road-v1'],
        'details' => [
            'ru' => ['goals' => ['Различать оправдание и сострадание.', 'Восстановить последовательность помощи.', 'Предложить безопасный конкретный поступок в школьной жизни.'], 'materials' => ['Библия для чтения Лк 10:25–37 по желанию.', 'Сумка для условной сценки по желанию.'], 'devices' => 'Экран ведущего; проектор по желанию. Устройства учеников удобны для личных ответов, но обсуждение и роли возможны без них.', 'conditions' => '45 минут. Роли и публикация ответов добровольны. Возраст 8–14 лет — редакционная рекомендация этой адаптации; ведущий подбирает примеры для своей группы.'],
            'de' => ['goals' => ['Ausreden und Mitgefühl unterscheiden.', 'Die Reihenfolge der Hilfe nachvollziehen.', 'Eine sichere konkrete Hilfe im Schulalltag vorschlagen.'], 'materials' => ['Optional eine Bibel zum Lesen von Lukas 10,25–37.', 'Optional eine Tasche für die symbolische Szene.'], 'devices' => 'Ein Bildschirm für die Leitung; ein Projektor ist optional. Geräte der Lernenden sind für eigene Antworten hilfreich; Gespräch und Rollen sind auch ohne Geräte möglich.', 'conditions' => '45 Minuten. Rollen und Veröffentlichung sind freiwillig. 8–14 Jahre ist eine redaktionelle Empfehlung für diese Bearbeitung; die Leitung passt Beispiele an ihre Gruppe an.'],
        ],
    ],
    'document' => [
        'id' => '7b4c695a-1eb4-45e1-ae16-1f1cde8bdcb1', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'],
        'content' => ['ru' => ['title' => 'Кто мой ближний?'], 'de' => ['title' => 'Wer ist mein Nächster?']],
        'stages' => [
            $stage('neighbor-intro', 'Кто мой ближний?', 'Wer ist mein Nächster?', 180, [
                $image('intro-road', 'road', 'Дорога среди гор и путник вдали', 'Eine Straße im Gebirge und ein Reisender in der Ferne'),
                $text('intro-text', 'Один человек спросил Иисуса, кто его ближний. В ответ Иисус рассказал историю о путешественнике на дороге из Иерусалима в Иерихон. Сегодня мы попробуем прожить эту историю и понять, как стать ближним для другого.', 'Ein Mann fragte Jesus, wer sein Nächster sei. Jesus erzählte von einem Reisenden auf dem Weg von Jerusalem nach Jericho. Heute erleben wir diese Geschichte und überlegen, wie wir für einen anderen Menschen zum Nächsten werden können.'),
                $block('intro-signals', 'signals', ['text' => 'Готовы начать? Если нужен вопрос или помощь, подайте сигнал.'], ['text' => 'Bereit? Wenn ihr eine Frage habt oder Hilfe braucht, gebt ein Signal.']),
            ], ['Прочитайте Лк 10:25–37 из привычного для вашей группы перевода или используйте авторское изложение этапов. Объясните: сначала личный ответ, затем обсуждение. Не называйте верный ответ заранее.', 'Lesen Sie Lukas 10,25–37 in einer für Ihre Gruppe geeigneten Bibelübersetzung oder nutzen Sie die Nacherzählung. Erklären Sie: erst eine eigene Antwort, dann das Gespräch. Nehmen Sie die Lösung nicht vorweg.']),
            $stage('neighbor-traveler', 'Человек на дороге', 'Ein Mensch am Wegrand', 420, [
                $image('traveler-image', 'wounded', 'Пострадавший сидит у дороги, двое уходят', 'Ein verletzter Mann sitzt am Weg; zwei Männer gehen fort'),
                $text('traveler-text', 'На путешественника напали разбойники. Они забрали его вещи, избили и оставили у дороги. Он не мог продолжить путь сам. Кто заметит его и остановится?', 'Räuber überfielen den Reisenden. Sie nahmen seine Sachen, schlugen ihn und ließen ihn am Weg liegen. Allein konnte er nicht weitergehen. Wer würde ihn bemerken und stehen bleiben?'),
                $block('traveler-roles', 'roles', ['text' => 'Распределим роли для короткой сценки.', 'roles' => $items($roles, 'roleId', 1)], ['text' => 'Wir verteilen die Rollen für eine kurze Szene.', 'roles' => $items($roles, 'roleId', 2)],
                    ['capacities' => array_fill_keys(array_column($roles, 0), 1)], notes: ['Шесть добровольцев; остальные наблюдают. Путешественник идёт с сумкой. Разбойники условно забирают сумку, без ударов и физического контакта; человек садится ждать помощи. Никого не заставляйте играть роль пострадавшего.', 'Sechs Freiwillige; die anderen beobachten. Der Reisende trägt eine Tasche. Die Räuber nehmen sie nur symbolisch, ohne Schläge oder Körperkontakt. Der Reisende setzt sich und wartet. Niemand muss den Verletzten spielen.']),
            ], ['На роли отведите не больше двух минут, на сценку и наблюдение — пять. Сцена безопасна и условна. Герои выходят по одному на следующих этапах.', 'Höchstens zwei Minuten für die Rollen, fünf für Szene und Beobachtung. Spielen Sie sicher und symbolisch. Die Helfer treten in den nächsten Schritten einzeln auf.']),
            $stage('neighbor-priest', 'Священник', 'Der Priester', 180, [
                $image('priest-image', 'priest', 'Священник проходит мимо пострадавшего', 'Ein Priester geht an dem Verletzten vorbei'),
                $free('priest-excuse', 'Какую причину можно придумать, чтобы пройти мимо?', 'Welche Ausrede könnte man finden, um vorbeizugehen?', ['Откройте ответы. Пусть сначала появится личное правдоподобное объяснение. При необходимости одобрите текст, а анонимную публикацию выполните отдельной командой. Не публикуйте имена и личные случаи.', 'Öffnen Sie die Antworten. Lassen Sie zunächst eine eigene plausible Erklärung entstehen. Prüfen Sie den Text; anonyme Veröffentlichung ist eine separate Entscheidung. Keine Namen oder persönlichen Fälle veröffentlichen.']),
            ], ['Священник увидел человека, но прошёл мимо. Запишите объяснения без насмешек: сейчас важно услышать ход мысли героя.', 'Der Priester sah den Mann und ging vorbei. Sammeln Sie Erklärungen ohne Spott; zunächst geht es um die Gedanken des Handelnden.']),
            $stage('neighbor-levite', 'Левит', 'Der Levit', 180, [
                $image('levite-image', 'levite', 'Левит проходит мимо человека у дороги', 'Ein Levit geht an dem Mann am Weg vorbei'),
                $free('levite-excuse', 'Как ты объяснишь, почему не остановился?', 'Wie würdest du erklären, warum du nicht stehen geblieben bist?', ['Дайте время на личный ответ и затем сравните с первым объяснением. Убедительность оправдания не делает бездействие правильным.', 'Geben Sie Zeit für eine eigene Antwort und vergleichen Sie dann mit der ersten Erklärung. Eine überzeugende Ausrede macht Untätigkeit nicht richtig.']),
            ], ['Кратко объясните: левит служил при храме. И он увидел человека, но прошёл мимо. Не сводите разговор к осуждению профессии или народа.', 'Erklären Sie kurz: ein Levit diente am Tempel. Auch er sah den Mann und ging vorbei. Machen Sie daraus kein Urteil über einen Beruf oder ein Volk.']),
            $stage('neighbor-samaritan', 'Самарянин', 'Der Samariter', 240, [
                $image('samaritan-image', 'samaritan', 'Самарянин перевязывает руку пострадавшему, рядом осёл', 'Ein Samariter verbindet den Arm des Verletzten; daneben steht ein Esel'),
                $block('samaritan-motive', 'single-choice', ['question' => 'Что повело самарянина к человеку?', 'options' => $items($motives, 'optionId', 1)], ['question' => 'Was bewegte den Samariter, zu dem Mann zu gehen?', 'options' => $items($motives, 'optionId', 2)], solution: ['optionId' => 'compassion'], notes: ['Сначала выбор, затем close/reveal. После раскрытия скажите: самарянин сжалился, подошёл, перевязал раны, отвёз человека в гостиницу и оплатил дальнейший уход. Не превращайте выбор в оценку ребёнка.', 'Erst wählen, dann schließen und aufdecken. Danach erzählen: Der Samariter hatte Mitgefühl, ging hin, verband die Wunden, brachte den Mann zur Herberge und bezahlte seine weitere Versorgung. Bewerten Sie damit nicht das Kind.']),
            ], ['В истории помощь приходит от человека, которого слушатели могли считать чужим. При инсценировке изображайте помощь жестами, без контакта.', 'Die Hilfe kommt von einem Menschen, den die Zuhörer als fremd ansehen konnten. Stellen Sie die Hilfe mit Gesten ohne Körperkontakt dar.']),
            $stage('neighbor-barriers', 'Что мешает помочь?', 'Was hindert uns am Helfen?', 240, [
                $prompt('barriers-discussion', 'Что всё-таки можно было сделать? Мог ли самарянин найти себе такое же оправдание?', 'Was hätte man trotzdem tun können? Hätte der Samariter dieselbe Ausrede finden können?'),
                $block('barriers-poll', 'poll', ['question' => 'Что чаще мешает нам остановиться и помочь?', 'options' => $items($barriers, 'optionId', 1)], ['question' => 'Was hält uns häufig davon ab, stehen zu bleiben und zu helfen?', 'options' => $items($barriers, 'optionId', 2)], notes: ['Нет правильного варианта. После голосования закройте и раскройте агрегат класса. При обсуждении прежних объяснений прочитайте их из пульта или вернитесь на предыдущий этап; общей межэтапной доски здесь нет.', 'Es gibt keine richtige Option. Nach der Abstimmung schließen und das Gruppenergebnis aufdecken. Lesen Sie frühere Erklärungen im Pult oder gehen Sie zum vorherigen Schritt zurück; es gibt keine gemeinsame Tafel über mehrere Schritte.']),
            ], ['Помощь должна быть безопасной и посильной. Можно позвать взрослого или вызвать помощь. Не требуйте рисковать собой.', 'Hilfe muss sicher und zumutbar sein. Man kann einen Erwachsenen oder professionelle Hilfe holen. Verlangen Sie kein persönliches Risiko.']),
            $stage('neighbor-help-path', 'Путь помощи', 'Der Weg der Hilfe', 240, [
                $image('path-road', 'road', 'Дорога к далёкому городу', 'Der Weg zu einer Stadt in der Ferne'),
                $block('help-sequence', 'sequence', ['question' => 'Восстановите порядок поступков самарянина.', 'items' => $items([$sequence[2], $sequence[0], $sequence[4], $sequence[1], $sequence[3]], 'itemId', 1)], ['question' => 'Bringt die Schritte des Samariters in die richtige Reihenfolge.', 'items' => $items([$sequence[2], $sequence[0], $sequence[4], $sequence[1], $sequence[3]], 'itemId', 2)], ['allowRepeat' => true], ['itemIds' => array_column($sequence, 0)], ['Сначала личный порядок. Разрешены изменения до закрытия; после reveal сброса попытки нет. Обсудите: помощь начинается с внимания и продолжается после первого действия.', 'Zuerst eine eigene Reihenfolge. Änderungen sind bis zum Schließen möglich; nach dem Aufdecken gibt es keinen Neustart des Versuchs. Besprechen Sie: Hilfe beginnt mit Aufmerksamkeit und geht über die erste Handlung hinaus.']),
            ], ['Откройте задание, дайте время, закройте ответы и раскройте решение. Проверка оценивает весь порядок, а не количество верных позиций.', 'Öffnen Sie die Aufgabe, geben Sie Zeit, schließen Sie die Antworten und decken Sie die Lösung auf. Geprüft wird die gesamte Reihenfolge, nicht die Zahl einzelner richtiger Positionen.']),
            $stage('neighbor-question', 'Кто оказался ближним?', 'Wer wurde zum Nächsten?', 180, [
                $block('neighbor-choice', 'single-choice', ['question' => 'Кто стал ближним для пострадавшего?', 'options' => $items($neighbors, 'optionId', 1)], ['question' => 'Wer wurde für den Verletzten zum Nächsten?', 'options' => $items($neighbors, 'optionId', 2)], solution: ['optionId' => 'samaritan'], notes: ['После close/reveal сравните вопросы: «Кто мой ближний?» и «Чьим ближним могу стать я?». Ответ связан с милосердным действием, а не с происхождением человека. Прочитайте заключение Лк 10:37 из вашей Библии.', 'Vergleichen Sie nach dem Aufdecken: „Wer ist mein Nächster?“ und „Für wen kann ich zum Nächsten werden?“. Entscheidend ist barmherziges Handeln, nicht die Herkunft. Lesen Sie den Schluss aus Lukas 10,37 in Ihrer Bibel.']),
            ], ['Не показывайте вывод до личного выбора. После раскрытия можно вывести его через общее сообщение: «Ближним становятся делом».', 'Zeigen Sie den Schluss erst nach der eigenen Wahl. Danach kann eine gemeinsame Nachricht lauten: „Zum Nächsten werden wir durch unser Handeln.“']),
            $stage('neighbor-newcomer', 'Новенький на перемене', 'Neu in der Pause', 180, [
                $image('newcomer-image', 'newcomer', 'Ребёнок один на скамье, остальные общаются', 'Ein Kind sitzt allein auf einer Bank, während die anderen sich unterhalten'),
                $prompt('newcomer-excuse', 'Как можно убедить себя, что подходить не нужно? Почему это не решает проблему?', 'Welche Ausrede könnte man finden, um nicht hinzugehen? Warum löst sie das Problem nicht?', 'pair'),
                $prompt('newcomer-help', 'Назовите первые слова, с которыми можно подойти. Что предложить сделать вместе?', 'Mit welchen ersten Worten könnt ihr hingehen? Was könnt ihr gemeinsam machen?', 'pair'),
                $free('newcomer-words', 'Какие первые слова вы скажете новенькому?', 'Welche ersten Worte sagt ihr dem neuen Kind?', ['Запустите минуту парной работы общим таймером. Затем предложите записать точные слова. Публикация добровольна, только анонимная одобренная редакция.', 'Starten Sie eine Minute Partnerarbeit mit dem allgemeinen Timer. Danach konkrete Worte aufschreiben lassen. Veröffentlichung ist freiwillig und nur als geprüfte anonyme Fassung.']),
            ], ['Два вопроса обсуждаются последовательно; переход между ними задаёт ведущий устно. Не просите назвать одинокого ребёнка вашего класса.', 'Besprechen Sie beide Fragen nacheinander; Sie steuern den Wechsel mündlich. Fragen Sie nicht nach dem Namen eines einsamen Kindes in Ihrer Gruppe.']),
            $stage('neighbor-books', 'Рассыпавшиеся книги', 'Heruntergefallene Bücher', 180, [
                $image('books-image', 'books', 'Ребёнок собирает книги с пола, рядом стоят другие дети', 'Ein Kind sammelt Bücher vom Boden; andere Kinder stehen daneben'),
                $prompt('books-excuse', 'Какая удобная причина позволит пройти мимо? Действительно ли она мешает остановиться?', 'Welche bequeme Ausrede lässt uns vorbeigehen? Hindert sie uns wirklich daran, stehen zu bleiben?', 'pair'),
                $prompt('books-help', 'Как подойти, что спросить и чем помочь прямо сейчас?', 'Wie könnt ihr hingehen, was fragen und sofort helfen?', 'pair'),
                $free('books-words', 'Как вы предложите собрать книги?', 'Wie bietet ihr Hilfe beim Aufheben der Bücher an?', ['Минута в парах. Ищите слова и посильное действие: подойти, спросить, собрать вместе. Абстрактное «надо помогать» дополните конкретным предложением.', 'Eine Minute zu zweit. Suchen Sie Worte und eine mögliche Handlung: hingehen, fragen, gemeinsam aufheben. Ergänzen Sie „Man muss helfen“ durch ein konkretes Angebot.']),
            ], ['Не используйте реальные имена и не инсценируйте падение вещей. Достаточно изображения и короткого разговора.', 'Keine echten Namen und kein Fallenlassen von Gegenständen. Bild und kurzes Gespräch reichen aus.']),
            $stage('neighbor-game', 'Не берут в игру', 'Nicht mitspielen dürfen', 180, [
                $image('game-image', 'game', 'Дети играют, один ребёнок стоит в стороне', 'Kinder spielen; ein Kind steht abseits'),
                $prompt('game-excuse', 'Почему можно решить, что это «не моё дело»? Что изменится, если все подумают так?', 'Warum könnte man denken: „Das geht mich nichts an“? Was passiert, wenn alle so denken?', 'pair'),
                $prompt('game-help', 'Что сказать участникам игры и однокласснику, чтобы включить его без ссоры?', 'Was könnt ihr den Mitspielenden und dem Kind sagen, damit es ohne Streit mitspielen kann?', 'pair'),
                $free('game-words', 'Как пригласить одноклассника в игру?', 'Wie ladet ihr ein Kind zum Mitspielen ein?', ['Минута в парах. Предложите спокойные слова включения. При угрозе, травле или серьёзном вреде нужен взрослый; ребёнок не обязан решать опасный конфликт один.', 'Eine Minute zu zweit. Suchen Sie ruhige einladende Worte. Bei Drohungen, Mobbing oder ernstem Schaden braucht es einen Erwachsenen; ein Kind muss einen gefährlichen Konflikt nicht allein lösen.']),
            ], ['Не превращайте сцену в публичное расследование конфликтов класса. Обсуждайте вымышленный пример и безопасную помощь.', 'Machen Sie daraus keine öffentliche Untersuchung von Konflikten der Gruppe. Besprechen Sie ein erfundenes Beispiel und sichere Hilfe.']),
            $stage('neighbor-week', 'Кому я могу помочь?', 'Wem kann ich helfen?', 120, [
                $prompt('week-reflection', 'Подумайте о небольшом посильном поступке на этой неделе. Можно оставить его только для себя.', 'Denkt an eine kleine Hilfe, die ihr diese Woche leisten könnt. Ihr könnt den Gedanken auch für euch behalten.', kind: 'reflection'),
                $free('week-promise', 'На этой неделе я могу помочь…', 'Diese Woche kann ich helfen …', ['Не требуйте обещания, имени получателя или публикации. Ответ до 100 знаков. Прочитайте несколько добровольных анонимных идей, отдельно одобрив и опубликовав их.', 'Verlangen Sie kein Versprechen, keinen Namen und keine Veröffentlichung. Höchstens 100 Zeichen. Lesen Sie einige freiwillige anonyme Ideen nach Prüfung und separater Veröffentlichung.'], 100),
            ], ['Не оценивайте размер поступка и не собирайте чувствительные семейные сведения. Поддержите небольшой реальный шаг.', 'Bewerten Sie nicht die Größe der Hilfe und sammeln Sie keine sensiblen Familieninformationen. Unterstützen Sie einen kleinen realistischen Schritt.']),
            $stage('neighbor-summary', 'Итог урока', 'Was nehmen wir mit?', 180, [
                $image('summary-road', 'road', 'Дорога, по которой можно сделать следующий шаг', 'Eine Straße, auf der der nächste Schritt möglich ist'),
                $text('summary-text', "Увидеть нужду другого.\nНе спрятаться за оправданием.\nПодойти и сделать посильный шаг помощи.", "Die Not eines anderen sehen.\nSich nicht hinter einer Ausrede verstecken.\nHingehen und einen möglichen Schritt der Hilfe tun.", 'list'),
                $free('summary-word', 'Одно слово, которое вы уносите с урока', 'Ein Wort, das ihr aus der Stunde mitnehmt', ['Дайте место нескольким добровольным словам. Напомните: ближним становятся делом. Завершите занятие общей командой finish; новый класс получает новый запуск.', 'Geben Sie einigen freiwilligen Worten Raum. Erinnern Sie: Zum Nächsten werden wir durch unser Handeln. Beenden Sie mit dem allgemeinen Abschluss; eine neue Gruppe erhält einen neuen Start.'], 100),
            ], ['Подведите итог без сравнения детей и их ответов. Общая длительность всех 13 этапов — ровно 45 минут; это ориентир ведущему, а не автоматический переход.', 'Schließen Sie ab, ohne Kinder oder Antworten zu vergleichen. Alle 13 Schritte ergeben genau 45 Minuten; dies ist ein Richtwert für die Leitung, kein automatischer Wechsel.']),
        ],
    ],
];
