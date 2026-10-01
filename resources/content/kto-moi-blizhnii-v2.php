<?php

declare(strict_types=1);

// Reviewed additive revision. The original source and released snapshot stay unchanged.
$source = require __DIR__.'/kto-moi-blizhnii.php';
$source['sourceRevision'] = 'neighbor-ru-de-2026-10-01-v2';
$source['versionId'] = 'ce72dc58-f259-43b5-8493-6e782670c1e2';
$source['document']['id'] = $source['versionId'];
$source['document']['documentation'] = require __DIR__.'/neighbor-documentation.php';
$stages = &$source['document']['stages'];
foreach ($stages as &$stage) {
    $stage['config']['openTasks'] = true;
}
unset($stage);
$presentation = fn (string $id, string $kind, string $ru, string $de, ?string $review = null, array $sources = [], array $ruModes = [], array $deModes = []): array => [
    'id' => $id, 'type' => 'core.presentation', 'schemaVersion' => 1,
    'content' => ['ru' => ['text' => $ru, 'modes' => $ruModes], 'de' => ['text' => $de, 'modes' => $deModes]],
    'config' => ['kind' => $kind, 'reviewBlockId' => $review, 'sourceBlockIds' => $sources, 'maxItems' => 8],
];
$notes = function (int $index, string $ru, string $de) use (&$stages): void {
    $stages[$index]['content']['ru']['notes'] = $ru;
    $stages[$index]['content']['de']['notes'] = $de;
};
$text = function (int $stage, int $block, string $ru, string $de) use (&$stages): void {
    $stages[$stage]['blocks'][$block]['content']['ru']['text'] = $ru;
    $stages[$stage]['blocks'][$block]['content']['de']['text'] = $de;
};
$text(0, 1, "Притча о милосердном самарянине\nЛк 10:25–37", "Das Gleichnis vom barmherzigen Samariter\nLk 10,25–37");
$text(0, 2, 'Готовы начать путь?', 'Bereit, den Weg zu beginnen?');
$notes(0, 'Прочитайте Лк 10:25–37 целиком. После чтения предложите детям разыграть притчу.', 'Lesen Sie Lk 10,25–37 vollständig vor. Schlagen Sie den Kindern danach vor, das Gleichnis nachzuspielen.');
$text(1, 1, 'Путник шёл из Иерусалима в Иерихон. Разбойники ограбили и избили его. Он остался ждать помощи.', 'Ein Reisender ging von Jerusalem nach Jericho. Räuber beraubten und schlugen ihn. Er blieb zurück und wartete auf Hilfe.');
$text(1, 2, 'Какую роль вы готовы сыграть?', 'Welche Rolle möchtet ihr spielen?');
$stages[1]['blocks'][2]['content']['ru']['roles'][1]['text'] = 'Разбойник 1';
$stages[1]['blocks'][2]['content']['ru']['roles'][2]['text'] = 'Разбойник 2';
$stages[1]['blocks'][2]['content']['de']['roles'][1]['text'] = 'Räuber 1';
$stages[1]['blocks'][2]['content']['de']['roles'][2]['text'] = 'Räuber 2';
$notes(1, 'Выберите путешественника, двух разбойников, священника, левита и самарянина. Путешественник идёт с сумкой, разбойники забирают её и уходят. Пострадавший остаётся у дороги. Открывайте роли по одной; ученик может выбрать свободную роль.', 'Wählen Sie einen Reisenden, zwei Räuber, einen Priester, einen Leviten und einen Samariter. Der Reisende trägt eine Tasche; die Räuber nehmen sie weg und gehen. Der Verletzte bleibt am Weg. Decken Sie die Rollen einzeln auf; die Lernenden können eine freie Rolle wählen.');
$notes(2, 'Ученик видит пострадавшего и проходит мимо. Не подсказывайте ответ: важно, чтобы ребёнок сам придумал оправдание от лица героя.', 'Der Schüler sieht den Verletzten und geht vorbei. Geben Sie keine Antwort vor: Das Kind soll selbst eine Ausrede aus der Sicht seiner Figur finden.');
$notes(3, 'Второй ученик тоже проходит мимо и придумывает своё объяснение. Дайте классу услышать, насколько убедительно может звучать оправдание.', 'Auch der zweite Schüler geht vorbei und denkt sich eine eigene Erklärung aus. Lassen Sie die Klasse hören, wie überzeugend eine Ausrede klingen kann.');
$stages[4]['blocks'][] = [
    'id' => 'samaritan-question', 'type' => 'core.prompt', 'schemaVersion' => 1,
    'content' => ['ru' => ['text' => 'Ты тоже мог пройти мимо. Почему ты решил помочь?'], 'de' => ['text' => 'Du hättest auch vorbeigehen können. Warum hast du dich entschieden zu helfen?']],
    'config' => ['target' => 'class', 'kind' => 'discussion'],
];
$stages[4]['blocks'][] = $presentation('samaritan-reveal', 'reveal',
    'Он увидел беду, подошёл к человеку и сделал всё, что мог: перевязал раны, довёз до гостиницы и оплатил дальнейший уход.',
    'Er sah die Not, ging zu dem Mann und tat alles, was er konnte: Er verband seine Wunden, brachte ihn zur Herberge und bezahlte seine weitere Versorgung.', 'samaritan-motive');
$stages[4]['blocks'][1]['teacherNotes'] = ['ru' => 'Ты тоже мог пройти мимо. Почему ты решил помочь? Сначала выбор, затем откройте поступок героя и правильный ответ.', 'de' => 'Du hättest auch vorbeigehen können. Warum hast du dich entschieden zu helfen? Zuerst wird gewählt; danach decken Sie die Handlung und die richtige Antwort auf.'];
$stages[4]['blocks'] = [$stages[4]['blocks'][0], $stages[4]['blocks'][2], $stages[4]['blocks'][1], $stages[4]['blocks'][3]];
$notes(4, 'Ученик помогает пострадавшему подняться и ведёт его в гостиницу. После сценки спросите, почему он помог, хотя мог найти такое же оправдание.', 'Der Schüler hilft dem Verletzten aufzustehen und führt ihn zur Herberge. Fragen Sie nach der Szene, warum er geholfen hat, obwohl er dieselbe Ausrede hätte finden können.');
$stages[5]['blocks'][] = $presentation('excuses-board', 'response-board',
    'Нажмите на ответ класса и спросите: «Что всё-таки можно было сделать?» Мог ли самарянин найти себе такое же оправдание?',
    'Wählen Sie eine Antwort der Klasse und fragen Sie: „Was hätte man trotzdem tun können?“ Hätte der Samariter dieselbe Ausrede finden können?', sources: ['priest-excuse', 'levite-excuse']);
$stages[5]['blocks'][1]['teacherNotes'] = ['ru' => 'Нет правильного варианта. Общая доска показывает отдельно одобренные и анонимно опубликованные объяснения священника и левита. Отмечайте обсуждённые объяснения на доске.', 'de' => 'Es gibt keine richtige Option. Die gemeinsame Tafel zeigt einzeln geprüfte und anonym veröffentlichte Erklärungen des Priesters und des Leviten. Markieren Sie die besprochenen Erklärungen auf der Tafel.'];
$notes(5, 'Разберите придуманные объяснения. Обсудите, действительно ли они мешали помочь и бывает ли, что лень или эгоизм подсказывают нам удобную причину.', 'Besprechen Sie die ausgedachten Erklärungen. Haben sie wirklich am Helfen gehindert? Kommt es vor, dass Bequemlichkeit oder Egoismus uns einen passenden Grund liefern?');
// The OLD road uses the full canvas, without a second decorative image column.
$stages[6]['blocks'] = [$stages[6]['blocks'][1]];
$short = ['notice' => ['Увидел', 'Gesehen'], 'approach' => ['Подошёл', 'Hingegangen'], 'help' => ['Помог', 'Geholfen'], 'bring' => ['Привёз', 'Hingebracht'], 'continue-care' => ['Позаботился дальше', 'Weiter für ihn gesorgt']];
foreach (['ru' => 0, 'de' => 1] as $locale => $translation) {
    foreach ($stages[6]['blocks'][0]['content'][$locale]['items'] as &$item) {
        $item['text'] = $short[$item['itemId']][$translation];
    }
    unset($item);
}
$notes(6, 'Попросите детей восстановить действия по порядку. После завершения спросите, что герой сделал сам и как позаботился о человеке после отъезда. Выбирайте поступки один за другим; можно собрать заново и проверить вместе.', 'Bitten Sie die Kinder, die Handlungen in der richtigen Reihenfolge wiederzugeben. Fragen Sie anschließend, was der Samariter selbst getan hat und wie er nach seiner Abreise für den Mann gesorgt hat. Wählen Sie die Handlungen nacheinander; die Reihenfolge kann neu zusammengestellt und gemeinsam geprüft werden.');
$stages[7]['blocks'][] = $presentation('neighbor-reveal', 'reveal',
    "Самарянин стал ближним, потому что проявил милосердие делом.\n«Иди, и ты поступай так же» — Лк 10:37",
    "Der Samariter wurde zum Nächsten, weil er durch sein Handeln Barmherzigkeit zeigte.\n„Geh und handle genauso.“ — Lk 10,37", 'neighbor-choice');
$notes(7, 'Сопоставьте начальный вопрос «Кто мой ближний?» с вопросом Христа о том, кто оказался ближним пострадавшему. Подведите к мысли: ближним становятся через милосердный поступок.', 'Vergleichen Sie die Ausgangsfrage „Wer ist mein Nächster?“ mit der Frage Christi, wer dem Verletzten zum Nächsten geworden ist. Führen Sie zum Gedanken: Durch barmherziges Handeln wird man zum Nächsten.');
$situations = [
    [8, 'newcomer', 'Он сидит один. Что можно ему сказать или предложить?', 'Er sitzt allein. Was könnte man ihm sagen oder vorschlagen?',
        'Как можно убедить себя, что подходить не нужно? Почему это объяснение не решает проблему?', 'Wie könnte man sich einreden, dass man nicht hinzugehen braucht? Warum löst diese Erklärung das Problem nicht?',
        'Назовите первые слова, с которыми можно подойти. Что можно предложить сделать вместе?', 'Nennt die ersten Worte, mit denen man auf ihn zugehen kann. Was könnte man gemeinsam tun?'],
    [9, 'books', 'Младший ученик уронил книги. Как подойти и предложить помощь?', 'Ein jüngerer Schüler hat seine Bücher fallen lassen. Wie könnte man auf ihn zugehen und Hilfe anbieten?',
        'Какая удобная причина позволит пройти мимо? Действительно ли она мешает остановиться?', 'Welche passende Ausrede würde es erlauben, vorbeizugehen? Hindert sie wirklich daran, stehen zu bleiben?',
        'Как подойти, что спросить и чем помочь прямо сейчас?', 'Wie kann man auf ihn zugehen, was kann man fragen und wie kann man sofort helfen?'],
    [10, 'game', 'Одноклассника не берут в общую игру. Как помочь ему присоединиться?', 'Ein Mitschüler darf bei einem gemeinsamen Spiel nicht mitmachen. Wie kann man ihm helfen, sich anzuschließen?',
        'Почему можно решить, что это «не моё дело»? Что изменится, если все подумают так же?', 'Warum könnte man meinen: „Das geht mich nichts an“? Was ändert sich, wenn alle so denken?',
        'Что сказать участникам игры и самому однокласснику, чтобы включить его без ссоры?', 'Was kann man den Mitspielenden und dem Mitschüler sagen, damit er ohne Streit mitmachen kann?'],
];
foreach ($situations as [$index, $id, $ru, $de, $excuseRu, $excuseDe, $helpRu, $helpDe]) {
    // Preserve the actual pupil answer block while the presenter selects the shared question.
    $stages[$index]['blocks'] = [$stages[$index]['blocks'][0], $presentation($id.'-discussion', 'discussion', $ru, $de,
        ruModes: [['modeId' => 'excuse', 'label' => 'Как пройти мимо?', 'text' => $excuseRu], ['modeId' => 'help', 'label' => 'Как помочь?', 'text' => $helpRu]],
        deModes: [['modeId' => 'excuse', 'label' => 'Wie vorbeigehen?', 'text' => $excuseDe], ['modeId' => 'help', 'label' => 'Wie helfen?', 'text' => $helpDe]]), $stages[$index]['blocks'][3]];
}
$notes(8, 'Работа в парах: до 10 минут на три ситуации. Каждая пара выбирает одну ситуацию: сначала придумывает оправдание бездействию, затем конкретный поступок помощи. Дайте минуту на разговор, затем выслушайте несколько пар.', 'Partnerarbeit: insgesamt bis zu 10 Minuten für die drei Situationen. Jedes Paar wählt eine Situation: zuerst eine Ausrede für das Nichtstun, dann eine konkrete Hilfe. Geben Sie eine Minute zum Gespräch und hören Sie anschließend einige Paare an.');
$notes(9, 'Попросите назвать точные слова и конкретное действие: как подойти, что спросить, чем помочь. Не предлагайте готовый ответ заранее.', 'Bitten Sie um genaue Worte und eine konkrete Handlung: Wie kann man hingehen, was fragen und wie helfen? Geben Sie keine fertige Antwort vor.');
$notes(10, 'Ищите спокойный способ включить одноклассника в игру. Если ситуация опасна или человек серьёзно пострадал, помощь может состоять в том, чтобы позвать взрослого.', 'Suchen Sie einen ruhigen Weg, den Mitschüler ins Spiel einzubeziehen. In einer gefährlichen Situation oder bei einer schweren Verletzung kann Hilfe darin bestehen, einen Erwachsenen zu holen.');
$stages[11]['blocks'][] = $presentation('promise-board', 'response-board',
    'Каждый добрый поступок становится следующим шагом. «Иди, и ты поступай так же».',
    'Jede gute Tat wird zum nächsten Schritt. „Geh und handle genauso.“', sources: ['week-promise']);
$notes(11, 'Предложите каждому закончить фразу про себя или добавить один посильный поступок на общий путь. Не требуйте публичного ответа.', 'Bitten Sie alle, den Satz für sich zu vervollständigen oder eine mögliche Hilfe zum gemeinsamen Weg hinzuzufügen. Verlangen Sie keine öffentliche Antwort.');
$text(12, 1, "Не вопрос «кто достоин моей помощи?», а решение: «чьим ближним могу стать я?»\nУвидеть — заметить человека и его нужду.\nПодойти — не прятаться за удобным оправданием.\nПомочь — сделать конкретный посильный шаг.\n«Иди, и ты поступай так же» — Лк 10:37", "Nicht die Frage „Wer verdient meine Hilfe?“, sondern die Entscheidung: „Wem kann ich zum Nächsten werden?“\nSehen — den Menschen und seine Not bemerken.\nHingehen — sich nicht hinter einer passenden Ausrede verstecken.\nHelfen — einen konkreten Schritt tun, den man leisten kann.\n„Geh und handle genauso.“ — Lk 10,37");
$stages[12]['blocks'][1]['content']['ru']['title'] = 'Ближним становятся';
$stages[12]['blocks'][1]['content']['de']['title'] = 'Zum Nächsten wird man';
$notes(12, 'Коротко повторите три шага милосердия. Предложите ученикам одним словом назвать главный вывод, затем завершите урок.', 'Wiederholen Sie kurz die drei Schritte der Barmherzigkeit. Bitten Sie die Lernenden, den wichtigsten Gedanken mit einem Wort zu nennen, und beenden Sie dann die Stunde.');

return $source;
