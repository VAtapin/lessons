<?php

declare(strict_types=1);

// The supplied content specification is data, never executable import instructions.
$raw = json_decode(file_get_contents(__DIR__.'/slova-ranyat-source.json'), true, flags: JSON_THROW_ON_ERROR);
$de = require __DIR__.'/slova-ranyat-de.php';
$lesson = $raw['lesson'];
$version = '8637a2e4-5b46-4ec0-995f-498842ca5d29';
$presentationConfig = ['kind' => 'scene', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
$block = fn (string $id, string $type, array $ru, array $translated, array $config = [], ?array $solution = null): array => array_filter([
    'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $ru, 'de' => $translated], 'config' => $config, 'solution' => $solution,
], fn ($value) => $value !== null);
$notes = static function (array $screen, string $locale) use ($lesson, $de): string {
    $labels = $locale === 'ru' ? ['Участники', 'Ведущий', 'Вопрос для обсуждения', 'Примеры, не единственные правильные ответы', 'Вывод', 'Правила занятия'] : ['Teilnehmende', 'Leitung', 'Gesprächsfrage', 'Beispiele, nicht die einzig richtigen Antworten', 'Fazit', 'Vereinbarungen'];
    $parts = [$labels[0].":\n".$screen['student_task'], $labels[1].":\n".$screen['teacher_actions']];
    if ($screen['discussion_question'] !== '') {
        $parts[] = $labels[2].":\n".$screen['discussion_question'];
    }
    if ($screen['sample_responses'] !== []) {
        $parts[] = $labels[3].":\n".implode("\n", $screen['sample_responses']);
    }
    $parts[] = $labels[4].":\n".$screen['conclusion'];
    $parts[] = $labels[5].":\n".implode("\n", $locale === 'ru' ? $lesson['teacher_notes'] : $de['lesson']['teacher_notes']);

    return implode("\n\n", $parts);
};
$images = [
    'cover' => ['Двое подростков разговаривают на школьной скамье', 'Zwei Jugendliche sprechen auf einer Bank im Schulhof'],
    'message' => ['Подросток читает сообщение на телефоне', 'Ein Jugendlicher liest eine Nachricht auf dem Handy'],
    'spark' => ['Небольшая искра как образ начала ссоры', 'Ein kleiner Funke als Bild für den Anfang eines Streits'],
    'team' => ['Подростки вместе работают над школьным проектом', 'Jugendliche arbeiten gemeinsam an einem Schulprojekt'],
    'pause' => ['Подросток делает паузу перед ответом', 'Ein Jugendlicher hält vor einer Antwort inne'],
    'support' => ['Подруга поддерживает расстроенного одноклассника', 'Eine Freundin unterstützt einen enttäuschten Mitschüler'],
    'repair' => ['Двое подростков разговаривают после ссоры', 'Zwei Jugendliche sprechen nach einem Streit'],
    'good-word' => ['Подростки внимательно слушают друг друга', 'Jugendliche hören einander aufmerksam zu'],
];
$stages = [];
$answerSeconds = [2 => 20, 3 => 15, 4 => 15, 7 => 45, 8 => 45, 9 => 25, 11 => 40, 12 => 20, 13 => 30, 14 => 20];
foreach ($raw['screens'] as $index => $screen) {
    $number = $screen['number'];
    $translated = $de['screens'][$index];
    $prefix = 'words-'.$screen['id'];
    $blocks = [];
    $scene = $block($prefix.'-scene', 'presentation', ['title' => $screen['title'], 'text' => $screen['type'] === 'poll_series' ? $screen['student_task'] : $screen['screen_text'], 'eyebrow' => $number === 1 ? 'Интерактивный урок · 12–14 лет · 45 минут' : 'Слова ранят. Слова лечат · '.$number.' / 16', 'modes' => []], ['title' => $translated['title'], 'text' => $screen['type'] === 'poll_series' ? $translated['student_task'] : $translated['screen_text'], 'eyebrow' => $number === 1 ? 'Interaktiver Unterricht · 12–14 Jahre · 45 Minuten' : 'Worte verletzen. Worte heilen · '.$number.' / 16', 'modes' => []], $presentationConfig + ['scene' => $number === 1 || $number === 16 ? 'cover' : 'story']);
    $blocks[] = $scene;
    if ($screen['image'] !== null) {
        $slug = substr(pathinfo($screen['image'], PATHINFO_FILENAME), 3);
        $image = $block($prefix.'-image', 'image', ['alt' => $images[$slug][0], 'caption' => ''], ['alt' => $images[$slug][1], 'caption' => ''], ['fit' => 'contain']);
        $image['media'] = ['image' => ['assetId' => 'builtin-words-'.$slug, 'versionId' => 'builtin-words-'.$slug.'-v1']];
        $blocks[] = $image;
    }
    $options = fn (string $locale) => array_map(fn ($option, $i) => ['optionId' => $option['id'], 'text' => $locale === 'ru' ? $option['label'] : $translated['options'][$i]], $screen['options'], array_keys($screen['options']));
    if ($screen['type'] === 'poll_series') {
        $phrases = explode("\n", $screen['screen_text']);
        $germanPhrases = explode("\n", $translated['screen_text']);
        foreach ($phrases as $i => $phrase) {
            $blocks[] = $block($prefix.'-phrase-'.($i + 1), 'poll', ['question' => $phrase, 'options' => $options('ru')], ['question' => $germanPhrases[$i], 'options' => $options('de')], ['allowRepeat' => true]);
        }
    } elseif ($screen['type'] === 'poll' || $screen['type'] === 'choice') {
        $blocks[] = $block($prefix.'-answer', $screen['type'] === 'poll' ? 'poll' : 'single-choice', ['question' => $screen['student_task'], 'options' => $options('ru')], ['question' => $translated['student_task'], 'options' => $options('de')], ['allowRepeat' => true], $screen['type'] === 'choice' ? ['optionId' => $raw['interaction_contract']['teacher_only_keys'][$screen['id']][0]] : null);
    } elseif ($screen['type'] === 'multi_select') {
        $blocks[] = $block($prefix.'-answer', 'multiple-choice', ['question' => $screen['student_task'], 'options' => $options('ru')], ['question' => $translated['student_task'], 'options' => $options('de')], ['allowRepeat' => true, 'minSelections' => 1, 'maxSelections' => 2]);
    } elseif ($screen['type'] === 'free_text') {
        $blocks[] = $block($prefix.'-answer', 'free-response', ['question' => $screen['student_task'], 'label' => 'Ваш ответ', 'placeholder' => 'Напишите одну фразу', 'submitLabel' => 'Отправить'], ['question' => $translated['student_task'], 'label' => 'Deine Antwort', 'placeholder' => 'Schreibe einen Satz', 'submitLabel' => 'Senden'], ['allowRepeat' => true, 'maxLength' => 220]);
    } elseif ($screen['type'] === 'sequence') {
        $sequence = fn (string $locale) => array_map(fn ($option, $i) => ['itemId' => $option['id'], 'text' => $locale === 'ru' ? $option['label'] : $translated['options'][$i]], $screen['options'], array_keys($screen['options']));
        $blocks[] = $block($prefix.'-answer', 'sequence', ['question' => $screen['student_task'], 'items' => $sequence('ru')], ['question' => $translated['student_task'], 'items' => $sequence('de')], ['allowRepeat' => true], ['itemIds' => $raw['interaction_contract']['sequence']['expected_order']]);
    } elseif ($screen['type'] === 'personal_choice') {
        $modes = fn (string $locale) => array_map(fn ($option, $i) => ['modeId' => $option['id'], 'text' => $locale === 'ru' ? $option['label'] : $translated['options'][$i]], $screen['options'], array_keys($screen['options']));
        $blocks[] = $block($prefix.'-private', 'presentation', ['text' => $screen['student_task'], 'modes' => $modes('ru')], ['text' => $translated['student_task'], 'modes' => $modes('de')], array_replace($presentationConfig, ['kind' => 'personal-choice']));
    } elseif ($screen['type'] === 'intro') {
        $blocks[] = $block($prefix.'-signals', 'signals', ['text' => $screen['student_task']], ['text' => $translated['student_task']]);
    } elseif ($screen['type'] !== 'closing') {
        $blocks[] = $block($prefix.'-instruction', 'prompt', ['text' => $screen['student_task']], ['text' => $translated['student_task']], ['kind' => 'discussion', 'target' => 'class']);
    }
    if ($screen['type'] === 'closing') {
        $blocks[] = $block($prefix.'-closing', 'presentation', ['title' => $screen['title'], 'eyebrow' => 'Урок завершён', 'text' => $screen['screen_text'], 'source' => 'Еф. 4:29', 'modes' => []], ['title' => $translated['title'], 'eyebrow' => 'Die Stunde ist beendet', 'text' => $translated['screen_text'], 'source' => 'Epheser 4,29', 'modes' => []], array_replace($presentationConfig, ['kind' => 'closing']));
    }
    $stageNotes = ['ru' => $notes($screen, 'ru'), 'de' => $notes($translated, 'de')];
    $config = ['layout' => 'material-above-task', 'durationSeconds' => $screen['duration_minutes'] * 60, 'openTasks' => true];
    if (isset($answerSeconds[$number])) {
        $config += ['answerSeconds' => $answerSeconds[$number], 'closeOnTimer' => true];
    }
    if ($screen['type'] === 'poll_series') {
        $config['sequentialTasks'] = true;
    }
    $stages[] = ['id' => $prefix, 'content' => ['ru' => ['title' => $screen['title'], 'notes' => $stageNotes['ru']], 'de' => ['title' => $translated['title'], 'notes' => $stageNotes['de']]], 'config' => $config, 'blocks' => $blocks];
}
$plan = [];
foreach (['ru', 'de'] as $locale) {
    $info = $locale === 'ru' ? $lesson : $de['lesson'];
    $parts = [$info['title'], $info['goal'], $info['main_idea'], implode("\n", $info['outcomes']), implode("\n", $info['materials']), implode("\n", $info['teacher_notes'])];
    foreach ($raw['lesson']['plan'] as $i => $row) {
        $parts[] = $row['time_range_minutes'].' · '.($locale === 'ru' ? $row['title']."\n".$row['activity'] : $de['lesson']['plan'][$i]."\n".$de['lesson']['plan_activities'][$i]);
    }
    foreach ($stages as $i => $stage) {
        $parts[] = ($i + 1).' · '.$stage['content'][$locale]['title']."\n".$stage['content'][$locale]['notes'];
    }
    $plan[$locale] = ['plan' => implode("\n\n", $parts)];
}
$files = [];
foreach (config('lesson-files') as $id => $file) {
    if (str_starts_with($id, 'words-file-') && str_ends_with($id, '-v1')) {
        $files[] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'ru'];
    }
}

return [
    'sourceRevision' => 'words-ru-de-2026-10-01-v1', 'materialId' => 'c5ab4a4a-9154-4535-a391-bcac40883d06', 'versionId' => $version, 'ownerKey' => 'eeea9060-3b41-42d1-9fef-f7c530ce8b8c', 'slug' => $lesson['slug'],
    'metadata' => ['translations' => ['ru' => ['title' => $lesson['title'], 'description' => $lesson['short_description']], 'de' => ['title' => $de['lesson']['title'], 'description' => $de['lesson']['short_description']]],
        'age' => ['11-14'], 'topic' => ['bible'], 'audience' => ['school', 'sunday-school', 'group'], 'format' => ['lesson', 'interactive'], 'durationMinutes' => 45,
        'cover' => ['assetId' => 'builtin-words-cover', 'versionId' => 'builtin-words-cover-v1'],
        'details' => ['ru' => ['goals' => $lesson['outcomes'], 'materials' => $lesson['materials'], 'devices' => $lesson['preparation'], 'conditions' => '12–14 лет. 45 минут. '.$lesson['format']], 'de' => ['goals' => $de['lesson']['outcomes'], 'materials' => $de['lesson']['materials'], 'devices' => 'Vorkenntnisse sind nicht nötig.', 'conditions' => '12–14 Jahre. 45 Minuten. Gespräch, Abstimmungen, kurze Antworten und Dialoge.']]],
    'document' => ['id' => $version, 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => ['ru' => ['title' => $lesson['title']], 'de' => ['title' => $de['lesson']['title']]], 'stages' => $stages, 'documentation' => ['schemaVersion' => 1, 'content' => $plan, 'files' => $files]],
];
