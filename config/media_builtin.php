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

$builtins[] = ['assetId' => 'builtin-thanks-1', 'versionId' => 'builtin-thanks-1-v1', 'file' => 'assets/library/spasibo/01.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_1'];
$builtins[] = ['assetId' => 'builtin-thanks-2', 'versionId' => 'builtin-thanks-2-v1', 'file' => 'assets/library/spasibo/02.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_2'];
$builtins[] = ['assetId' => 'builtin-thanks-3', 'versionId' => 'builtin-thanks-3-v1', 'file' => 'assets/library/spasibo/03.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_3'];
$builtins[] = ['assetId' => 'builtin-thanks-4', 'versionId' => 'builtin-thanks-4-v1', 'file' => 'assets/library/spasibo/04.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_4'];
$builtins[] = ['assetId' => 'builtin-thanks-5', 'versionId' => 'builtin-thanks-5-v1', 'file' => 'assets/library/spasibo/05.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_5'];
$builtins[] = ['assetId' => 'builtin-thanks-6', 'versionId' => 'builtin-thanks-6-v1', 'file' => 'assets/library/spasibo/06.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_6'];
$builtins[] = ['assetId' => 'builtin-thanks-7', 'versionId' => 'builtin-thanks-7-v1', 'file' => 'assets/library/spasibo/07.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_7'];
$builtins[] = ['assetId' => 'builtin-thanks-8', 'versionId' => 'builtin-thanks-8-v1', 'file' => 'assets/library/spasibo/08.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_8'];
$builtins[] = ['assetId' => 'builtin-thanks-9', 'versionId' => 'builtin-thanks-9-v1', 'file' => 'assets/library/spasibo/09.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_9'];
$builtins[] = ['assetId' => 'builtin-thanks-10', 'versionId' => 'builtin-thanks-10-v1', 'file' => 'assets/library/spasibo/10.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_10'];
$builtins[] = ['assetId' => 'builtin-thanks-11', 'versionId' => 'builtin-thanks-11-v1', 'file' => 'assets/library/spasibo/11.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_11'];
$builtins[] = ['assetId' => 'builtin-thanks-12', 'versionId' => 'builtin-thanks-12-v1', 'file' => 'assets/library/spasibo/12.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_12'];
$builtins[] = ['assetId' => 'builtin-thanks-13', 'versionId' => 'builtin-thanks-13-v1', 'file' => 'assets/library/spasibo/13.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_13'];
$builtins[] = ['assetId' => 'builtin-thanks-14', 'versionId' => 'builtin-thanks-14-v1', 'file' => 'assets/library/spasibo/14.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_14'];
$builtins[] = ['assetId' => 'builtin-thanks-15', 'versionId' => 'builtin-thanks-15-v1', 'file' => 'assets/library/spasibo/15.png', 'mime' => 'image/png', 'labelKey' => 'media_thanks_15'];

$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-1', 'versionId' => 'builtin-illustrated-neighbor-1-v1', 'file' => 'assets/library/illustrated/neighbor/01.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_1'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-2', 'versionId' => 'builtin-illustrated-neighbor-2-v1', 'file' => 'assets/library/illustrated/neighbor/02.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_2'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-3', 'versionId' => 'builtin-illustrated-neighbor-3-v1', 'file' => 'assets/library/illustrated/neighbor/03.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_3'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-4', 'versionId' => 'builtin-illustrated-neighbor-4-v1', 'file' => 'assets/library/illustrated/neighbor/04.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_4'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-5', 'versionId' => 'builtin-illustrated-neighbor-5-v1', 'file' => 'assets/library/illustrated/neighbor/05.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_5'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-6', 'versionId' => 'builtin-illustrated-neighbor-6-v1', 'file' => 'assets/library/illustrated/neighbor/06.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_6'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-7', 'versionId' => 'builtin-illustrated-neighbor-7-v1', 'file' => 'assets/library/illustrated/neighbor/07.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_7'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-8', 'versionId' => 'builtin-illustrated-neighbor-8-v1', 'file' => 'assets/library/illustrated/neighbor/08.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_8'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-9', 'versionId' => 'builtin-illustrated-neighbor-9-v1', 'file' => 'assets/library/illustrated/neighbor/09.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_9'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-10', 'versionId' => 'builtin-illustrated-neighbor-10-v1', 'file' => 'assets/library/illustrated/neighbor/10.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_10'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-11', 'versionId' => 'builtin-illustrated-neighbor-11-v1', 'file' => 'assets/library/illustrated/neighbor/11.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_11'];
$builtins[] = ['assetId' => 'builtin-illustrated-neighbor-12', 'versionId' => 'builtin-illustrated-neighbor-12-v1', 'file' => 'assets/library/illustrated/neighbor/12.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_neighbor_12'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-1', 'versionId' => 'builtin-illustrated-words-1-v1', 'file' => 'assets/library/illustrated/words/01.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_1'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-2', 'versionId' => 'builtin-illustrated-words-2-v1', 'file' => 'assets/library/illustrated/words/02.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_2'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-3', 'versionId' => 'builtin-illustrated-words-3-v1', 'file' => 'assets/library/illustrated/words/03.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_3'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-4', 'versionId' => 'builtin-illustrated-words-4-v1', 'file' => 'assets/library/illustrated/words/04.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_4'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-5', 'versionId' => 'builtin-illustrated-words-5-v1', 'file' => 'assets/library/illustrated/words/05.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_5'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-6', 'versionId' => 'builtin-illustrated-words-6-v1', 'file' => 'assets/library/illustrated/words/06.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_6'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-7', 'versionId' => 'builtin-illustrated-words-7-v1', 'file' => 'assets/library/illustrated/words/07.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_7'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-8', 'versionId' => 'builtin-illustrated-words-8-v1', 'file' => 'assets/library/illustrated/words/08.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_8'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-9', 'versionId' => 'builtin-illustrated-words-9-v1', 'file' => 'assets/library/illustrated/words/09.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_9'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-10', 'versionId' => 'builtin-illustrated-words-10-v1', 'file' => 'assets/library/illustrated/words/10.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_10'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-11', 'versionId' => 'builtin-illustrated-words-11-v1', 'file' => 'assets/library/illustrated/words/11.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_11'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-12', 'versionId' => 'builtin-illustrated-words-12-v1', 'file' => 'assets/library/illustrated/words/12.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_12'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-13', 'versionId' => 'builtin-illustrated-words-13-v1', 'file' => 'assets/library/illustrated/words/13.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_13'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-14', 'versionId' => 'builtin-illustrated-words-14-v1', 'file' => 'assets/library/illustrated/words/14.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_14'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-15', 'versionId' => 'builtin-illustrated-words-15-v1', 'file' => 'assets/library/illustrated/words/15.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_15'];
$builtins[] = ['assetId' => 'builtin-illustrated-words-16', 'versionId' => 'builtin-illustrated-words-16-v1', 'file' => 'assets/library/illustrated/words/16.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_words_16'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-1', 'versionId' => 'builtin-illustrated-zakkhei-1-v1', 'file' => 'assets/library/illustrated/zakkhei/01.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_1'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-2', 'versionId' => 'builtin-illustrated-zakkhei-2-v1', 'file' => 'assets/library/illustrated/zakkhei/02.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_2'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-3', 'versionId' => 'builtin-illustrated-zakkhei-3-v1', 'file' => 'assets/library/illustrated/zakkhei/03.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_3'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-4', 'versionId' => 'builtin-illustrated-zakkhei-4-v1', 'file' => 'assets/library/illustrated/zakkhei/04.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_4'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-5', 'versionId' => 'builtin-illustrated-zakkhei-5-v1', 'file' => 'assets/library/illustrated/zakkhei/05.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_5'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-6', 'versionId' => 'builtin-illustrated-zakkhei-6-v1', 'file' => 'assets/library/illustrated/zakkhei/06.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_6'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-7', 'versionId' => 'builtin-illustrated-zakkhei-7-v1', 'file' => 'assets/library/illustrated/zakkhei/07.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_7'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-8', 'versionId' => 'builtin-illustrated-zakkhei-8-v1', 'file' => 'assets/library/illustrated/zakkhei/08.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_8'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-9', 'versionId' => 'builtin-illustrated-zakkhei-9-v1', 'file' => 'assets/library/illustrated/zakkhei/09.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_9'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-10', 'versionId' => 'builtin-illustrated-zakkhei-10-v1', 'file' => 'assets/library/illustrated/zakkhei/10.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_10'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-11', 'versionId' => 'builtin-illustrated-zakkhei-11-v1', 'file' => 'assets/library/illustrated/zakkhei/11.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_11'];
$builtins[] = ['assetId' => 'builtin-illustrated-zakkhei-12', 'versionId' => 'builtin-illustrated-zakkhei-12-v1', 'file' => 'assets/library/illustrated/zakkhei/12.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_zakkhei_12'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-1', 'versionId' => 'builtin-illustrated-judge-1-v1', 'file' => 'assets/library/illustrated/judge/01.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_1'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-2', 'versionId' => 'builtin-illustrated-judge-2-v1', 'file' => 'assets/library/illustrated/judge/02.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_2'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-3', 'versionId' => 'builtin-illustrated-judge-3-v1', 'file' => 'assets/library/illustrated/judge/03.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_3'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-4', 'versionId' => 'builtin-illustrated-judge-4-v1', 'file' => 'assets/library/illustrated/judge/04.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_4'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-5', 'versionId' => 'builtin-illustrated-judge-5-v1', 'file' => 'assets/library/illustrated/judge/05.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_5'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-6', 'versionId' => 'builtin-illustrated-judge-6-v1', 'file' => 'assets/library/illustrated/judge/06.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_6'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-7', 'versionId' => 'builtin-illustrated-judge-7-v1', 'file' => 'assets/library/illustrated/judge/07.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_7'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-8', 'versionId' => 'builtin-illustrated-judge-8-v1', 'file' => 'assets/library/illustrated/judge/08.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_8'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-9', 'versionId' => 'builtin-illustrated-judge-9-v1', 'file' => 'assets/library/illustrated/judge/09.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_9'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-10', 'versionId' => 'builtin-illustrated-judge-10-v1', 'file' => 'assets/library/illustrated/judge/10.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_10'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-11', 'versionId' => 'builtin-illustrated-judge-11-v1', 'file' => 'assets/library/illustrated/judge/11.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_11'];
$builtins[] = ['assetId' => 'builtin-illustrated-judge-12', 'versionId' => 'builtin-illustrated-judge-12-v1', 'file' => 'assets/library/illustrated/judge/12.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_judge_12'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-1', 'versionId' => 'builtin-illustrated-sheep-1-v1', 'file' => 'assets/library/illustrated/sheep/01.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_1'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-2', 'versionId' => 'builtin-illustrated-sheep-2-v1', 'file' => 'assets/library/illustrated/sheep/02.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_2'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-3', 'versionId' => 'builtin-illustrated-sheep-3-v1', 'file' => 'assets/library/illustrated/sheep/03.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_3'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-4', 'versionId' => 'builtin-illustrated-sheep-4-v1', 'file' => 'assets/library/illustrated/sheep/04.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_4'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-5', 'versionId' => 'builtin-illustrated-sheep-5-v1', 'file' => 'assets/library/illustrated/sheep/05.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_5'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-6', 'versionId' => 'builtin-illustrated-sheep-6-v1', 'file' => 'assets/library/illustrated/sheep/06.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_6'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-7', 'versionId' => 'builtin-illustrated-sheep-7-v1', 'file' => 'assets/library/illustrated/sheep/07.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_7'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-8', 'versionId' => 'builtin-illustrated-sheep-8-v1', 'file' => 'assets/library/illustrated/sheep/08.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_8'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-9', 'versionId' => 'builtin-illustrated-sheep-9-v1', 'file' => 'assets/library/illustrated/sheep/09.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_9'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-10', 'versionId' => 'builtin-illustrated-sheep-10-v1', 'file' => 'assets/library/illustrated/sheep/10.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_10'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-11', 'versionId' => 'builtin-illustrated-sheep-11-v1', 'file' => 'assets/library/illustrated/sheep/11.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_11'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-12', 'versionId' => 'builtin-illustrated-sheep-12-v1', 'file' => 'assets/library/illustrated/sheep/12.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_12'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-13', 'versionId' => 'builtin-illustrated-sheep-13-v1', 'file' => 'assets/library/illustrated/sheep/13.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_13'];
$builtins[] = ['assetId' => 'builtin-illustrated-sheep-14', 'versionId' => 'builtin-illustrated-sheep-14-v1', 'file' => 'assets/library/illustrated/sheep/14.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_sheep_14'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-1', 'versionId' => 'builtin-illustrated-talent-1-v1', 'file' => 'assets/library/illustrated/talent/01.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_1'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-2', 'versionId' => 'builtin-illustrated-talent-2-v1', 'file' => 'assets/library/illustrated/talent/02.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_2'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-3', 'versionId' => 'builtin-illustrated-talent-3-v1', 'file' => 'assets/library/illustrated/talent/03.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_3'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-4', 'versionId' => 'builtin-illustrated-talent-4-v1', 'file' => 'assets/library/illustrated/talent/04.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_4'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-5', 'versionId' => 'builtin-illustrated-talent-5-v1', 'file' => 'assets/library/illustrated/talent/05.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_5'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-6', 'versionId' => 'builtin-illustrated-talent-6-v1', 'file' => 'assets/library/illustrated/talent/06.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_6'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-7', 'versionId' => 'builtin-illustrated-talent-7-v1', 'file' => 'assets/library/illustrated/talent/07.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_7'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-8', 'versionId' => 'builtin-illustrated-talent-8-v1', 'file' => 'assets/library/illustrated/talent/08.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_8'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-9', 'versionId' => 'builtin-illustrated-talent-9-v1', 'file' => 'assets/library/illustrated/talent/09.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_9'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-10', 'versionId' => 'builtin-illustrated-talent-10-v1', 'file' => 'assets/library/illustrated/talent/10.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_10'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-11', 'versionId' => 'builtin-illustrated-talent-11-v1', 'file' => 'assets/library/illustrated/talent/11.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_11'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-12', 'versionId' => 'builtin-illustrated-talent-12-v1', 'file' => 'assets/library/illustrated/talent/12.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_12'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-13', 'versionId' => 'builtin-illustrated-talent-13-v1', 'file' => 'assets/library/illustrated/talent/13.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_13'];
$builtins[] = ['assetId' => 'builtin-illustrated-talent-14', 'versionId' => 'builtin-illustrated-talent-14-v1', 'file' => 'assets/library/illustrated/talent/14.jpeg', 'mime' => 'image/jpeg', 'labelKey' => 'media_illustrated_talent_14'];

$builtins[] = ['assetId' => 'builtin-friends-1', 'versionId' => 'builtin-friends-1-v1', 'file' => 'assets/library/friends/01.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_1'];
$builtins[] = ['assetId' => 'builtin-friends-2', 'versionId' => 'builtin-friends-2-v1', 'file' => 'assets/library/friends/02.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_2'];
$builtins[] = ['assetId' => 'builtin-friends-3', 'versionId' => 'builtin-friends-3-v1', 'file' => 'assets/library/friends/03.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_3'];
$builtins[] = ['assetId' => 'builtin-friends-4', 'versionId' => 'builtin-friends-4-v1', 'file' => 'assets/library/friends/04.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_4'];
$builtins[] = ['assetId' => 'builtin-friends-5', 'versionId' => 'builtin-friends-5-v1', 'file' => 'assets/library/friends/05.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_5'];
$builtins[] = ['assetId' => 'builtin-friends-6', 'versionId' => 'builtin-friends-6-v1', 'file' => 'assets/library/friends/06.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_6'];
$builtins[] = ['assetId' => 'builtin-friends-7', 'versionId' => 'builtin-friends-7-v1', 'file' => 'assets/library/friends/07.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_7'];
$builtins[] = ['assetId' => 'builtin-friends-8', 'versionId' => 'builtin-friends-8-v1', 'file' => 'assets/library/friends/08.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_8'];
$builtins[] = ['assetId' => 'builtin-friends-9', 'versionId' => 'builtin-friends-9-v1', 'file' => 'assets/library/friends/09.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_9'];
$builtins[] = ['assetId' => 'builtin-friends-10', 'versionId' => 'builtin-friends-10-v1', 'file' => 'assets/library/friends/10.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_10'];
$builtins[] = ['assetId' => 'builtin-friends-11', 'versionId' => 'builtin-friends-11-v1', 'file' => 'assets/library/friends/11.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_11'];
$builtins[] = ['assetId' => 'builtin-friends-12', 'versionId' => 'builtin-friends-12-v1', 'file' => 'assets/library/friends/12.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_12'];
$builtins[] = ['assetId' => 'builtin-friends-13', 'versionId' => 'builtin-friends-13-v1', 'file' => 'assets/library/friends/13.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_13'];
$builtins[] = ['assetId' => 'builtin-friends-14', 'versionId' => 'builtin-friends-14-v1', 'file' => 'assets/library/friends/14.png', 'mime' => 'image/png', 'labelKey' => 'media_friends_14'];

$builtins[] = ['assetId' => 'builtin-choices-1', 'versionId' => 'builtin-choices-1-v1', 'file' => 'assets/library/choices/01.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_1'];
$builtins[] = ['assetId' => 'builtin-choices-2', 'versionId' => 'builtin-choices-2-v1', 'file' => 'assets/library/choices/02.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_2'];
$builtins[] = ['assetId' => 'builtin-choices-3', 'versionId' => 'builtin-choices-3-v1', 'file' => 'assets/library/choices/03.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_3'];
$builtins[] = ['assetId' => 'builtin-choices-4', 'versionId' => 'builtin-choices-4-v1', 'file' => 'assets/library/choices/04.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_4'];
$builtins[] = ['assetId' => 'builtin-choices-5', 'versionId' => 'builtin-choices-5-v1', 'file' => 'assets/library/choices/05.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_5'];
$builtins[] = ['assetId' => 'builtin-choices-6', 'versionId' => 'builtin-choices-6-v1', 'file' => 'assets/library/choices/06.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_6'];
$builtins[] = ['assetId' => 'builtin-choices-7', 'versionId' => 'builtin-choices-7-v1', 'file' => 'assets/library/choices/07.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_7'];
$builtins[] = ['assetId' => 'builtin-choices-8', 'versionId' => 'builtin-choices-8-v1', 'file' => 'assets/library/choices/08.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_8'];
$builtins[] = ['assetId' => 'builtin-choices-9', 'versionId' => 'builtin-choices-9-v1', 'file' => 'assets/library/choices/09.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_9'];
$builtins[] = ['assetId' => 'builtin-choices-10', 'versionId' => 'builtin-choices-10-v1', 'file' => 'assets/library/choices/10.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_10'];
$builtins[] = ['assetId' => 'builtin-choices-11', 'versionId' => 'builtin-choices-11-v1', 'file' => 'assets/library/choices/11.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_11'];
$builtins[] = ['assetId' => 'builtin-choices-12', 'versionId' => 'builtin-choices-12-v1', 'file' => 'assets/library/choices/12.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_12'];
$builtins[] = ['assetId' => 'builtin-choices-13', 'versionId' => 'builtin-choices-13-v1', 'file' => 'assets/library/choices/13.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_13'];
$builtins[] = ['assetId' => 'builtin-choices-14', 'versionId' => 'builtin-choices-14-v1', 'file' => 'assets/library/choices/14.png', 'mime' => 'image/png', 'labelKey' => 'media_choices_14'];

$builtins[] = ['assetId' => 'builtin-forgiveness-1', 'versionId' => 'builtin-forgiveness-1-v1', 'file' => 'assets/library/forgiveness/01.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_1'];
$builtins[] = ['assetId' => 'builtin-forgiveness-2', 'versionId' => 'builtin-forgiveness-2-v1', 'file' => 'assets/library/forgiveness/02.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_2'];
$builtins[] = ['assetId' => 'builtin-forgiveness-3', 'versionId' => 'builtin-forgiveness-3-v1', 'file' => 'assets/library/forgiveness/03.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_3'];
$builtins[] = ['assetId' => 'builtin-forgiveness-4', 'versionId' => 'builtin-forgiveness-4-v1', 'file' => 'assets/library/forgiveness/04.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_4'];
$builtins[] = ['assetId' => 'builtin-forgiveness-5', 'versionId' => 'builtin-forgiveness-5-v1', 'file' => 'assets/library/forgiveness/05.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_5'];
$builtins[] = ['assetId' => 'builtin-forgiveness-6', 'versionId' => 'builtin-forgiveness-6-v1', 'file' => 'assets/library/forgiveness/06.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_6'];
$builtins[] = ['assetId' => 'builtin-forgiveness-7', 'versionId' => 'builtin-forgiveness-7-v1', 'file' => 'assets/library/forgiveness/07.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_7'];
$builtins[] = ['assetId' => 'builtin-forgiveness-8', 'versionId' => 'builtin-forgiveness-8-v1', 'file' => 'assets/library/forgiveness/08.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_8'];
$builtins[] = ['assetId' => 'builtin-forgiveness-9', 'versionId' => 'builtin-forgiveness-9-v1', 'file' => 'assets/library/forgiveness/09.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_9'];
$builtins[] = ['assetId' => 'builtin-forgiveness-10', 'versionId' => 'builtin-forgiveness-10-v1', 'file' => 'assets/library/forgiveness/10.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_10'];
$builtins[] = ['assetId' => 'builtin-forgiveness-11', 'versionId' => 'builtin-forgiveness-11-v1', 'file' => 'assets/library/forgiveness/11.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_11'];
$builtins[] = ['assetId' => 'builtin-forgiveness-12', 'versionId' => 'builtin-forgiveness-12-v1', 'file' => 'assets/library/forgiveness/12.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_12'];
$builtins[] = ['assetId' => 'builtin-forgiveness-13', 'versionId' => 'builtin-forgiveness-13-v1', 'file' => 'assets/library/forgiveness/13.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_13'];
$builtins[] = ['assetId' => 'builtin-forgiveness-14', 'versionId' => 'builtin-forgiveness-14-v1', 'file' => 'assets/library/forgiveness/14.png', 'mime' => 'image/png', 'labelKey' => 'media_forgiveness_14'];

return $builtins;
