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

$builtins[] = ['assetId' => 'builtin-sheep-1', 'versionId' => 'builtin-sheep-1-v1', 'file' => 'assets/library/poteryannaya-ovechka/01.png', 'mime' => 'image/png', 'labelKey' => 'media_sheep_1'];
$builtins[] = ['assetId' => 'builtin-sheep-2', 'versionId' => 'builtin-sheep-2-v1', 'file' => 'assets/library/poteryannaya-ovechka/02.png', 'mime' => 'image/png', 'labelKey' => 'media_sheep_2'];
$builtins[] = ['assetId' => 'builtin-sheep-3', 'versionId' => 'builtin-sheep-3-v1', 'file' => 'assets/library/poteryannaya-ovechka/03.png', 'mime' => 'image/png', 'labelKey' => 'media_sheep_3'];
$builtins[] = ['assetId' => 'builtin-sheep-4', 'versionId' => 'builtin-sheep-4-v1', 'file' => 'assets/library/poteryannaya-ovechka/04.png', 'mime' => 'image/png', 'labelKey' => 'media_sheep_4'];
$builtins[] = ['assetId' => 'builtin-sheep-5', 'versionId' => 'builtin-sheep-5-v1', 'file' => 'assets/library/poteryannaya-ovechka/05.png', 'mime' => 'image/png', 'labelKey' => 'media_sheep_5'];

$builtins[] = ['assetId' => 'builtin-talent-1', 'versionId' => 'builtin-talent-1-v1', 'file' => 'assets/library/talant/01.png', 'mime' => 'image/png', 'labelKey' => 'media_talent_1'];
$builtins[] = ['assetId' => 'builtin-talent-2', 'versionId' => 'builtin-talent-2-v1', 'file' => 'assets/library/talant/02.png', 'mime' => 'image/png', 'labelKey' => 'media_talent_2'];
$builtins[] = ['assetId' => 'builtin-talent-3', 'versionId' => 'builtin-talent-3-v1', 'file' => 'assets/library/talant/03.png', 'mime' => 'image/png', 'labelKey' => 'media_talent_3'];
$builtins[] = ['assetId' => 'builtin-talent-4', 'versionId' => 'builtin-talent-4-v1', 'file' => 'assets/library/talant/04.png', 'mime' => 'image/png', 'labelKey' => 'media_talent_4'];
$builtins[] = ['assetId' => 'builtin-talent-5', 'versionId' => 'builtin-talent-5-v1', 'file' => 'assets/library/talant/05.png', 'mime' => 'image/png', 'labelKey' => 'media_talent_5'];

$builtins[] = ['assetId' => 'builtin-fear-1', 'versionId' => 'builtin-fear-1-v1', 'file' => 'assets/library/strakh/01.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_1'];
$builtins[] = ['assetId' => 'builtin-fear-2', 'versionId' => 'builtin-fear-2-v1', 'file' => 'assets/library/strakh/02.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_2'];
$builtins[] = ['assetId' => 'builtin-fear-3', 'versionId' => 'builtin-fear-3-v1', 'file' => 'assets/library/strakh/03.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_3'];
$builtins[] = ['assetId' => 'builtin-fear-4', 'versionId' => 'builtin-fear-4-v1', 'file' => 'assets/library/strakh/04.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_4'];
$builtins[] = ['assetId' => 'builtin-fear-5', 'versionId' => 'builtin-fear-5-v1', 'file' => 'assets/library/strakh/05.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_5'];
$builtins[] = ['assetId' => 'builtin-fear-6', 'versionId' => 'builtin-fear-6-v1', 'file' => 'assets/library/strakh/06.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_6'];
$builtins[] = ['assetId' => 'builtin-fear-7', 'versionId' => 'builtin-fear-7-v1', 'file' => 'assets/library/strakh/07.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_7'];
$builtins[] = ['assetId' => 'builtin-fear-8', 'versionId' => 'builtin-fear-8-v1', 'file' => 'assets/library/strakh/08.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_8'];
$builtins[] = ['assetId' => 'builtin-fear-9', 'versionId' => 'builtin-fear-9-v1', 'file' => 'assets/library/strakh/09.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_9'];
$builtins[] = ['assetId' => 'builtin-fear-10', 'versionId' => 'builtin-fear-10-v1', 'file' => 'assets/library/strakh/10.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_10'];
$builtins[] = ['assetId' => 'builtin-fear-11', 'versionId' => 'builtin-fear-11-v1', 'file' => 'assets/library/strakh/11.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_11'];
$builtins[] = ['assetId' => 'builtin-fear-12', 'versionId' => 'builtin-fear-12-v1', 'file' => 'assets/library/strakh/12.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_12'];
$builtins[] = ['assetId' => 'builtin-fear-13', 'versionId' => 'builtin-fear-13-v1', 'file' => 'assets/library/strakh/13.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_13'];
$builtins[] = ['assetId' => 'builtin-fear-14', 'versionId' => 'builtin-fear-14-v1', 'file' => 'assets/library/strakh/14.png', 'mime' => 'image/png', 'labelKey' => 'media_fear_14'];

return $builtins;
