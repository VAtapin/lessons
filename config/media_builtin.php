<?php

$builtins = [
    [
        'assetId' => 'builtin-conversation',
        'versionId' => 'builtin-conversation-v1',
        'file' => 'UI-Design/1.png',
        'mime' => 'image/png',
        'labelKey' => 'media_conversation',
    ],
    [
        'assetId' => 'builtin-mutual-help',
        'versionId' => 'builtin-mutual-help-v1',
        'file' => 'assets/library/mutual-help-v1.png',
        'mime' => 'image/png',
        'labelKey' => 'media_mutual_help',
        'attribution' => [
            'author' => 'OpenAI image generation',
            'source' => 'Generated for lessons.atapin.de; assets/library/README.md',
            'rightsBasis' => 'ai_generated',
            'usageRights' => 'Reusable illustration for the lessons.atapin.de platform.',
        ],
    ],
];

// Existing project illustrations are pinned by file and version; UI mockups
// and the logo are intentionally not offered as lesson content.
foreach ([2, 3, 4, 5, 6, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22] as $number) {
    $builtins[] = [
        'assetId' => 'builtin-design-'.$number,
        'versionId' => 'builtin-design-'.$number.'-v1',
        'file' => 'UI-Design/'.$number.'.png',
        'mime' => 'image/png',
        'labelKey' => 'media_design_'.$number,
        'attribution' => [
            'author' => 'Creator not recorded; supplied by the project owner.',
            'source' => 'UI-Design/'.$number.'.png',
            'rightsBasis' => 'permission',
            'usageRights' => 'Project owner requested reuse in lessons.atapin.de.',
        ],
    ];
}

foreach (['road', 'wounded', 'priest', 'levite', 'samaritan', 'newcomer', 'books', 'game'] as $scene) {
    $builtins[] = [
        'assetId' => 'builtin-neighbor-'.$scene,
        'versionId' => 'builtin-neighbor-'.$scene.'-v1',
        'file' => 'assets/library/neighbor/scene-'.$scene.'.webp',
        'mime' => 'image/webp',
        'labelKey' => 'media_neighbor_'.$scene,
        'attribution' => [
            'author' => 'Creator not recorded; supplied by the project owner.',
            'source' => 'OLD/kto-moi-blizhnii/assets/scene-'.$scene.'.webp',
            'rightsBasis' => 'permission',
            'usageRights' => 'Project owner explicitly approved reuse in lessons.atapin.de.',
        ],
    ];
}

$builtins[] = ['assetId' => 'builtin-words-cover', 'versionId' => 'builtin-words-cover-v1', 'file' => 'assets/library/slova-ranyat/01-cover.png', 'mime' => 'image/png', 'labelKey' => 'media_words_cover'];
$builtins[] = ['assetId' => 'builtin-words-message', 'versionId' => 'builtin-words-message-v1', 'file' => 'assets/library/slova-ranyat/02-message.png', 'mime' => 'image/png', 'labelKey' => 'media_words_message'];
$builtins[] = ['assetId' => 'builtin-words-spark', 'versionId' => 'builtin-words-spark-v1', 'file' => 'assets/library/slova-ranyat/03-spark.png', 'mime' => 'image/png', 'labelKey' => 'media_words_spark'];
$builtins[] = ['assetId' => 'builtin-words-team', 'versionId' => 'builtin-words-team-v1', 'file' => 'assets/library/slova-ranyat/04-team.png', 'mime' => 'image/png', 'labelKey' => 'media_words_team'];
$builtins[] = ['assetId' => 'builtin-words-pause', 'versionId' => 'builtin-words-pause-v1', 'file' => 'assets/library/slova-ranyat/05-pause.png', 'mime' => 'image/png', 'labelKey' => 'media_words_pause'];
$builtins[] = ['assetId' => 'builtin-words-support', 'versionId' => 'builtin-words-support-v1', 'file' => 'assets/library/slova-ranyat/06-support.png', 'mime' => 'image/png', 'labelKey' => 'media_words_support'];
$builtins[] = ['assetId' => 'builtin-words-repair', 'versionId' => 'builtin-words-repair-v1', 'file' => 'assets/library/slova-ranyat/07-repair.png', 'mime' => 'image/png', 'labelKey' => 'media_words_repair'];
$builtins[] = ['assetId' => 'builtin-words-good-word', 'versionId' => 'builtin-words-good-word-v1', 'file' => 'assets/library/slova-ranyat/08-good-word.png', 'mime' => 'image/png', 'labelKey' => 'media_words_good_word'];

$builtins[] = ['assetId' => 'builtin-zakkhei-1', 'versionId' => 'builtin-zakkhei-1-v1', 'file' => 'assets/library/zakkhei/01.png', 'mime' => 'image/png', 'labelKey' => 'media_zakkhei_1'];
$builtins[] = ['assetId' => 'builtin-zakkhei-2', 'versionId' => 'builtin-zakkhei-2-v1', 'file' => 'assets/library/zakkhei/02.png', 'mime' => 'image/png', 'labelKey' => 'media_zakkhei_2'];
$builtins[] = ['assetId' => 'builtin-zakkhei-3', 'versionId' => 'builtin-zakkhei-3-v1', 'file' => 'assets/library/zakkhei/03.png', 'mime' => 'image/png', 'labelKey' => 'media_zakkhei_3'];
$builtins[] = ['assetId' => 'builtin-zakkhei-4', 'versionId' => 'builtin-zakkhei-4-v1', 'file' => 'assets/library/zakkhei/04.png', 'mime' => 'image/png', 'labelKey' => 'media_zakkhei_4'];

$builtins[] = ['assetId' => 'builtin-judge-1', 'versionId' => 'builtin-judge-1-v1', 'file' => 'assets/library/ne-speshi-sudit/01.png', 'mime' => 'image/png', 'labelKey' => 'media_judge_1'];
$builtins[] = ['assetId' => 'builtin-judge-2', 'versionId' => 'builtin-judge-2-v1', 'file' => 'assets/library/ne-speshi-sudit/02.png', 'mime' => 'image/png', 'labelKey' => 'media_judge_2'];
$builtins[] = ['assetId' => 'builtin-judge-3', 'versionId' => 'builtin-judge-3-v1', 'file' => 'assets/library/ne-speshi-sudit/03.png', 'mime' => 'image/png', 'labelKey' => 'media_judge_3'];
$builtins[] = ['assetId' => 'builtin-judge-4', 'versionId' => 'builtin-judge-4-v1', 'file' => 'assets/library/ne-speshi-sudit/04.png', 'mime' => 'image/png', 'labelKey' => 'media_judge_4'];

return $builtins;
