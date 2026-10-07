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

$builtins[] = ['assetId' => 'builtin-pressure-1', 'versionId' => 'builtin-pressure-1-v1', 'file' => 'assets/library/pressure/01.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_1'];
$builtins[] = ['assetId' => 'builtin-pressure-2', 'versionId' => 'builtin-pressure-2-v1', 'file' => 'assets/library/pressure/02.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_2'];
$builtins[] = ['assetId' => 'builtin-pressure-3', 'versionId' => 'builtin-pressure-3-v1', 'file' => 'assets/library/pressure/03.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_3'];
$builtins[] = ['assetId' => 'builtin-pressure-4', 'versionId' => 'builtin-pressure-4-v1', 'file' => 'assets/library/pressure/04.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_4'];
$builtins[] = ['assetId' => 'builtin-pressure-5', 'versionId' => 'builtin-pressure-5-v1', 'file' => 'assets/library/pressure/05.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_5'];
$builtins[] = ['assetId' => 'builtin-pressure-6', 'versionId' => 'builtin-pressure-6-v1', 'file' => 'assets/library/pressure/06.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_6'];
$builtins[] = ['assetId' => 'builtin-pressure-7', 'versionId' => 'builtin-pressure-7-v1', 'file' => 'assets/library/pressure/07.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_7'];
$builtins[] = ['assetId' => 'builtin-pressure-8', 'versionId' => 'builtin-pressure-8-v1', 'file' => 'assets/library/pressure/08.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_8'];
$builtins[] = ['assetId' => 'builtin-pressure-9', 'versionId' => 'builtin-pressure-9-v1', 'file' => 'assets/library/pressure/09.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_9'];
$builtins[] = ['assetId' => 'builtin-pressure-10', 'versionId' => 'builtin-pressure-10-v1', 'file' => 'assets/library/pressure/10.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_10'];
$builtins[] = ['assetId' => 'builtin-pressure-11', 'versionId' => 'builtin-pressure-11-v1', 'file' => 'assets/library/pressure/11.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_11'];
$builtins[] = ['assetId' => 'builtin-pressure-12', 'versionId' => 'builtin-pressure-12-v1', 'file' => 'assets/library/pressure/12.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_12'];
$builtins[] = ['assetId' => 'builtin-pressure-13', 'versionId' => 'builtin-pressure-13-v1', 'file' => 'assets/library/pressure/13.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_13'];
$builtins[] = ['assetId' => 'builtin-pressure-14', 'versionId' => 'builtin-pressure-14-v1', 'file' => 'assets/library/pressure/14.png', 'mime' => 'image/png', 'labelKey' => 'media_pressure_14'];

$builtins[] = ['assetId' => 'builtin-envy-1', 'versionId' => 'builtin-envy-1-v1', 'file' => 'assets/library/envy/01.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_1'];
$builtins[] = ['assetId' => 'builtin-envy-2', 'versionId' => 'builtin-envy-2-v1', 'file' => 'assets/library/envy/02.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_2'];
$builtins[] = ['assetId' => 'builtin-envy-3', 'versionId' => 'builtin-envy-3-v1', 'file' => 'assets/library/envy/03.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_3'];
$builtins[] = ['assetId' => 'builtin-envy-4', 'versionId' => 'builtin-envy-4-v1', 'file' => 'assets/library/envy/04.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_4'];
$builtins[] = ['assetId' => 'builtin-envy-5', 'versionId' => 'builtin-envy-5-v1', 'file' => 'assets/library/envy/05.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_5'];
$builtins[] = ['assetId' => 'builtin-envy-6', 'versionId' => 'builtin-envy-6-v1', 'file' => 'assets/library/envy/06.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_6'];
$builtins[] = ['assetId' => 'builtin-envy-7', 'versionId' => 'builtin-envy-7-v1', 'file' => 'assets/library/envy/07.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_7'];
$builtins[] = ['assetId' => 'builtin-envy-8', 'versionId' => 'builtin-envy-8-v1', 'file' => 'assets/library/envy/08.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_8'];
$builtins[] = ['assetId' => 'builtin-envy-9', 'versionId' => 'builtin-envy-9-v1', 'file' => 'assets/library/envy/09.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_9'];
$builtins[] = ['assetId' => 'builtin-envy-10', 'versionId' => 'builtin-envy-10-v1', 'file' => 'assets/library/envy/10.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_10'];
$builtins[] = ['assetId' => 'builtin-envy-11', 'versionId' => 'builtin-envy-11-v1', 'file' => 'assets/library/envy/11.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_11'];
$builtins[] = ['assetId' => 'builtin-envy-12', 'versionId' => 'builtin-envy-12-v1', 'file' => 'assets/library/envy/12.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_12'];
$builtins[] = ['assetId' => 'builtin-envy-13', 'versionId' => 'builtin-envy-13-v1', 'file' => 'assets/library/envy/13.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_13'];
$builtins[] = ['assetId' => 'builtin-envy-14', 'versionId' => 'builtin-envy-14-v1', 'file' => 'assets/library/envy/14.png', 'mime' => 'image/png', 'labelKey' => 'media_envy_14'];

$builtins[] = ['assetId' => 'builtin-anger-1', 'versionId' => 'builtin-anger-1-v1', 'file' => 'assets/library/anger/01.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_1'];
$builtins[] = ['assetId' => 'builtin-anger-2', 'versionId' => 'builtin-anger-2-v1', 'file' => 'assets/library/anger/02.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_2'];
$builtins[] = ['assetId' => 'builtin-anger-3', 'versionId' => 'builtin-anger-3-v1', 'file' => 'assets/library/anger/03.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_3'];
$builtins[] = ['assetId' => 'builtin-anger-4', 'versionId' => 'builtin-anger-4-v1', 'file' => 'assets/library/anger/04.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_4'];
$builtins[] = ['assetId' => 'builtin-anger-5', 'versionId' => 'builtin-anger-5-v1', 'file' => 'assets/library/anger/05.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_5'];
$builtins[] = ['assetId' => 'builtin-anger-6', 'versionId' => 'builtin-anger-6-v1', 'file' => 'assets/library/anger/06.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_6'];
$builtins[] = ['assetId' => 'builtin-anger-7', 'versionId' => 'builtin-anger-7-v1', 'file' => 'assets/library/anger/07.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_7'];
$builtins[] = ['assetId' => 'builtin-anger-8', 'versionId' => 'builtin-anger-8-v1', 'file' => 'assets/library/anger/08.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_8'];
$builtins[] = ['assetId' => 'builtin-anger-9', 'versionId' => 'builtin-anger-9-v1', 'file' => 'assets/library/anger/09.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_9'];
$builtins[] = ['assetId' => 'builtin-anger-10', 'versionId' => 'builtin-anger-10-v1', 'file' => 'assets/library/anger/10.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_10'];
$builtins[] = ['assetId' => 'builtin-anger-11', 'versionId' => 'builtin-anger-11-v1', 'file' => 'assets/library/anger/11.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_11'];
$builtins[] = ['assetId' => 'builtin-anger-12', 'versionId' => 'builtin-anger-12-v1', 'file' => 'assets/library/anger/12.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_12'];
$builtins[] = ['assetId' => 'builtin-anger-13', 'versionId' => 'builtin-anger-13-v1', 'file' => 'assets/library/anger/13.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_13'];
$builtins[] = ['assetId' => 'builtin-anger-14', 'versionId' => 'builtin-anger-14-v1', 'file' => 'assets/library/anger/14.png', 'mime' => 'image/png', 'labelKey' => 'media_anger_14'];

$builtins[] = ['assetId' => 'builtin-peter-1', 'versionId' => 'builtin-peter-1-v1', 'file' => 'assets/library/peter/01.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_1'];
$builtins[] = ['assetId' => 'builtin-peter-2', 'versionId' => 'builtin-peter-2-v1', 'file' => 'assets/library/peter/02.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_2'];
$builtins[] = ['assetId' => 'builtin-peter-3', 'versionId' => 'builtin-peter-3-v1', 'file' => 'assets/library/peter/03.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_3'];
$builtins[] = ['assetId' => 'builtin-peter-4', 'versionId' => 'builtin-peter-4-v1', 'file' => 'assets/library/peter/04.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_4'];
$builtins[] = ['assetId' => 'builtin-peter-5', 'versionId' => 'builtin-peter-5-v1', 'file' => 'assets/library/peter/05.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_5'];
$builtins[] = ['assetId' => 'builtin-peter-6', 'versionId' => 'builtin-peter-6-v1', 'file' => 'assets/library/peter/06.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_6'];
$builtins[] = ['assetId' => 'builtin-peter-7', 'versionId' => 'builtin-peter-7-v1', 'file' => 'assets/library/peter/07.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_7'];
$builtins[] = ['assetId' => 'builtin-peter-8', 'versionId' => 'builtin-peter-8-v1', 'file' => 'assets/library/peter/08.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_8'];
$builtins[] = ['assetId' => 'builtin-peter-9', 'versionId' => 'builtin-peter-9-v1', 'file' => 'assets/library/peter/09.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_9'];
$builtins[] = ['assetId' => 'builtin-peter-10', 'versionId' => 'builtin-peter-10-v1', 'file' => 'assets/library/peter/10.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_10'];
$builtins[] = ['assetId' => 'builtin-peter-11', 'versionId' => 'builtin-peter-11-v1', 'file' => 'assets/library/peter/11.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_11'];
$builtins[] = ['assetId' => 'builtin-peter-12', 'versionId' => 'builtin-peter-12-v1', 'file' => 'assets/library/peter/12.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_12'];
$builtins[] = ['assetId' => 'builtin-peter-13', 'versionId' => 'builtin-peter-13-v1', 'file' => 'assets/library/peter/13.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_13'];
$builtins[] = ['assetId' => 'builtin-peter-14', 'versionId' => 'builtin-peter-14-v1', 'file' => 'assets/library/peter/14.png', 'mime' => 'image/png', 'labelKey' => 'media_peter_14'];

$builtins[] = ['assetId' => 'builtin-truth-1', 'versionId' => 'builtin-truth-1-v1', 'file' => 'assets/library/truth/01.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_1'];
$builtins[] = ['assetId' => 'builtin-truth-2', 'versionId' => 'builtin-truth-2-v1', 'file' => 'assets/library/truth/02.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_2'];
$builtins[] = ['assetId' => 'builtin-truth-3', 'versionId' => 'builtin-truth-3-v1', 'file' => 'assets/library/truth/03.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_3'];
$builtins[] = ['assetId' => 'builtin-truth-4', 'versionId' => 'builtin-truth-4-v1', 'file' => 'assets/library/truth/04.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_4'];
$builtins[] = ['assetId' => 'builtin-truth-5', 'versionId' => 'builtin-truth-5-v1', 'file' => 'assets/library/truth/05.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_5'];
$builtins[] = ['assetId' => 'builtin-truth-6', 'versionId' => 'builtin-truth-6-v1', 'file' => 'assets/library/truth/06.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_6'];
$builtins[] = ['assetId' => 'builtin-truth-7', 'versionId' => 'builtin-truth-7-v1', 'file' => 'assets/library/truth/07.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_7'];
$builtins[] = ['assetId' => 'builtin-truth-8', 'versionId' => 'builtin-truth-8-v1', 'file' => 'assets/library/truth/08.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_8'];
$builtins[] = ['assetId' => 'builtin-truth-9', 'versionId' => 'builtin-truth-9-v1', 'file' => 'assets/library/truth/09.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_9'];
$builtins[] = ['assetId' => 'builtin-truth-10', 'versionId' => 'builtin-truth-10-v1', 'file' => 'assets/library/truth/10.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_10'];
$builtins[] = ['assetId' => 'builtin-truth-11', 'versionId' => 'builtin-truth-11-v1', 'file' => 'assets/library/truth/11.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_11'];
$builtins[] = ['assetId' => 'builtin-truth-12', 'versionId' => 'builtin-truth-12-v1', 'file' => 'assets/library/truth/12.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_12'];
$builtins[] = ['assetId' => 'builtin-truth-13', 'versionId' => 'builtin-truth-13-v1', 'file' => 'assets/library/truth/13.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_13'];
$builtins[] = ['assetId' => 'builtin-truth-14', 'versionId' => 'builtin-truth-14-v1', 'file' => 'assets/library/truth/14.png', 'mime' => 'image/png', 'labelKey' => 'media_truth_14'];

$builtins[] = ['assetId' => 'builtin-vineyard-1', 'versionId' => 'builtin-vineyard-1-v1', 'file' => 'assets/library/vineyard/01.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_1'];
$builtins[] = ['assetId' => 'builtin-vineyard-2', 'versionId' => 'builtin-vineyard-2-v1', 'file' => 'assets/library/vineyard/02.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_2'];
$builtins[] = ['assetId' => 'builtin-vineyard-3', 'versionId' => 'builtin-vineyard-3-v1', 'file' => 'assets/library/vineyard/03.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_3'];
$builtins[] = ['assetId' => 'builtin-vineyard-4', 'versionId' => 'builtin-vineyard-4-v1', 'file' => 'assets/library/vineyard/04.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_4'];
$builtins[] = ['assetId' => 'builtin-vineyard-5', 'versionId' => 'builtin-vineyard-5-v1', 'file' => 'assets/library/vineyard/05.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_5'];
$builtins[] = ['assetId' => 'builtin-vineyard-6', 'versionId' => 'builtin-vineyard-6-v1', 'file' => 'assets/library/vineyard/06.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_6'];
$builtins[] = ['assetId' => 'builtin-vineyard-7', 'versionId' => 'builtin-vineyard-7-v1', 'file' => 'assets/library/vineyard/07.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_7'];
$builtins[] = ['assetId' => 'builtin-vineyard-8', 'versionId' => 'builtin-vineyard-8-v1', 'file' => 'assets/library/vineyard/08.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_8'];
$builtins[] = ['assetId' => 'builtin-vineyard-9', 'versionId' => 'builtin-vineyard-9-v1', 'file' => 'assets/library/vineyard/09.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_9'];
$builtins[] = ['assetId' => 'builtin-vineyard-10', 'versionId' => 'builtin-vineyard-10-v1', 'file' => 'assets/library/vineyard/10.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_10'];
$builtins[] = ['assetId' => 'builtin-vineyard-11', 'versionId' => 'builtin-vineyard-11-v1', 'file' => 'assets/library/vineyard/11.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_11'];
$builtins[] = ['assetId' => 'builtin-vineyard-12', 'versionId' => 'builtin-vineyard-12-v1', 'file' => 'assets/library/vineyard/12.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_12'];
$builtins[] = ['assetId' => 'builtin-vineyard-13', 'versionId' => 'builtin-vineyard-13-v1', 'file' => 'assets/library/vineyard/13.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_13'];
$builtins[] = ['assetId' => 'builtin-vineyard-14', 'versionId' => 'builtin-vineyard-14-v1', 'file' => 'assets/library/vineyard/14.png', 'mime' => 'image/png', 'labelKey' => 'media_vineyard_14'];

$builtins[] = ['assetId' => 'builtin-joseph-1', 'versionId' => 'builtin-joseph-1-v1', 'file' => 'assets/library/joseph/01.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_1'];
$builtins[] = ['assetId' => 'builtin-joseph-2', 'versionId' => 'builtin-joseph-2-v1', 'file' => 'assets/library/joseph/02.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_2'];
$builtins[] = ['assetId' => 'builtin-joseph-3', 'versionId' => 'builtin-joseph-3-v1', 'file' => 'assets/library/joseph/03.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_3'];
$builtins[] = ['assetId' => 'builtin-joseph-4', 'versionId' => 'builtin-joseph-4-v1', 'file' => 'assets/library/joseph/04.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_4'];
$builtins[] = ['assetId' => 'builtin-joseph-5', 'versionId' => 'builtin-joseph-5-v1', 'file' => 'assets/library/joseph/05.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_5'];
$builtins[] = ['assetId' => 'builtin-joseph-6', 'versionId' => 'builtin-joseph-6-v1', 'file' => 'assets/library/joseph/06.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_6'];
$builtins[] = ['assetId' => 'builtin-joseph-7', 'versionId' => 'builtin-joseph-7-v1', 'file' => 'assets/library/joseph/07.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_7'];
$builtins[] = ['assetId' => 'builtin-joseph-8', 'versionId' => 'builtin-joseph-8-v1', 'file' => 'assets/library/joseph/08.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_8'];
$builtins[] = ['assetId' => 'builtin-joseph-9', 'versionId' => 'builtin-joseph-9-v1', 'file' => 'assets/library/joseph/09.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_9'];
$builtins[] = ['assetId' => 'builtin-joseph-10', 'versionId' => 'builtin-joseph-10-v1', 'file' => 'assets/library/joseph/10.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_10'];
$builtins[] = ['assetId' => 'builtin-joseph-11', 'versionId' => 'builtin-joseph-11-v1', 'file' => 'assets/library/joseph/11.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_11'];
$builtins[] = ['assetId' => 'builtin-joseph-12', 'versionId' => 'builtin-joseph-12-v1', 'file' => 'assets/library/joseph/12.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_12'];
$builtins[] = ['assetId' => 'builtin-joseph-13', 'versionId' => 'builtin-joseph-13-v1', 'file' => 'assets/library/joseph/13.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_13'];
$builtins[] = ['assetId' => 'builtin-joseph-14', 'versionId' => 'builtin-joseph-14-v1', 'file' => 'assets/library/joseph/14.png', 'mime' => 'image/png', 'labelKey' => 'media_joseph_14'];

$builtins[] = ['assetId' => 'builtin-loaves-1', 'versionId' => 'builtin-loaves-1-v1', 'file' => 'assets/library/loaves/01.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_1'];
$builtins[] = ['assetId' => 'builtin-loaves-2', 'versionId' => 'builtin-loaves-2-v1', 'file' => 'assets/library/loaves/02.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_2'];
$builtins[] = ['assetId' => 'builtin-loaves-3', 'versionId' => 'builtin-loaves-3-v1', 'file' => 'assets/library/loaves/03.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_3'];
$builtins[] = ['assetId' => 'builtin-loaves-4', 'versionId' => 'builtin-loaves-4-v1', 'file' => 'assets/library/loaves/04.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_4'];
$builtins[] = ['assetId' => 'builtin-loaves-5', 'versionId' => 'builtin-loaves-5-v1', 'file' => 'assets/library/loaves/05.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_5'];
$builtins[] = ['assetId' => 'builtin-loaves-6', 'versionId' => 'builtin-loaves-6-v1', 'file' => 'assets/library/loaves/06.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_6'];
$builtins[] = ['assetId' => 'builtin-loaves-7', 'versionId' => 'builtin-loaves-7-v1', 'file' => 'assets/library/loaves/07.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_7'];
$builtins[] = ['assetId' => 'builtin-loaves-8', 'versionId' => 'builtin-loaves-8-v1', 'file' => 'assets/library/loaves/08.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_8'];
$builtins[] = ['assetId' => 'builtin-loaves-9', 'versionId' => 'builtin-loaves-9-v1', 'file' => 'assets/library/loaves/09.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_9'];
$builtins[] = ['assetId' => 'builtin-loaves-10', 'versionId' => 'builtin-loaves-10-v1', 'file' => 'assets/library/loaves/10.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_10'];
$builtins[] = ['assetId' => 'builtin-loaves-11', 'versionId' => 'builtin-loaves-11-v1', 'file' => 'assets/library/loaves/11.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_11'];
$builtins[] = ['assetId' => 'builtin-loaves-12', 'versionId' => 'builtin-loaves-12-v1', 'file' => 'assets/library/loaves/12.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_12'];
$builtins[] = ['assetId' => 'builtin-loaves-13', 'versionId' => 'builtin-loaves-13-v1', 'file' => 'assets/library/loaves/13.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_13'];
$builtins[] = ['assetId' => 'builtin-loaves-14', 'versionId' => 'builtin-loaves-14-v1', 'file' => 'assets/library/loaves/14.png', 'mime' => 'image/png', 'labelKey' => 'media_loaves_14'];

$builtins[] = ['assetId' => 'builtin-generosity-1', 'versionId' => 'builtin-generosity-1-v1', 'file' => 'assets/library/generosity/01.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_1'];
$builtins[] = ['assetId' => 'builtin-generosity-2', 'versionId' => 'builtin-generosity-2-v1', 'file' => 'assets/library/generosity/02.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_2'];
$builtins[] = ['assetId' => 'builtin-generosity-3', 'versionId' => 'builtin-generosity-3-v1', 'file' => 'assets/library/generosity/03.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_3'];
$builtins[] = ['assetId' => 'builtin-generosity-4', 'versionId' => 'builtin-generosity-4-v1', 'file' => 'assets/library/generosity/04.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_4'];
$builtins[] = ['assetId' => 'builtin-generosity-5', 'versionId' => 'builtin-generosity-5-v1', 'file' => 'assets/library/generosity/05.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_5'];
$builtins[] = ['assetId' => 'builtin-generosity-6', 'versionId' => 'builtin-generosity-6-v1', 'file' => 'assets/library/generosity/06.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_6'];
$builtins[] = ['assetId' => 'builtin-generosity-7', 'versionId' => 'builtin-generosity-7-v1', 'file' => 'assets/library/generosity/07.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_7'];
$builtins[] = ['assetId' => 'builtin-generosity-8', 'versionId' => 'builtin-generosity-8-v1', 'file' => 'assets/library/generosity/08.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_8'];
$builtins[] = ['assetId' => 'builtin-generosity-9', 'versionId' => 'builtin-generosity-9-v1', 'file' => 'assets/library/generosity/09.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_9'];
$builtins[] = ['assetId' => 'builtin-generosity-10', 'versionId' => 'builtin-generosity-10-v1', 'file' => 'assets/library/generosity/10.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_10'];
$builtins[] = ['assetId' => 'builtin-generosity-11', 'versionId' => 'builtin-generosity-11-v1', 'file' => 'assets/library/generosity/11.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_11'];
$builtins[] = ['assetId' => 'builtin-generosity-12', 'versionId' => 'builtin-generosity-12-v1', 'file' => 'assets/library/generosity/12.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_12'];
$builtins[] = ['assetId' => 'builtin-generosity-13', 'versionId' => 'builtin-generosity-13-v1', 'file' => 'assets/library/generosity/13.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_13'];
$builtins[] = ['assetId' => 'builtin-generosity-14', 'versionId' => 'builtin-generosity-14-v1', 'file' => 'assets/library/generosity/14.png', 'mime' => 'image/png', 'labelKey' => 'media_generosity_14'];

$builtins[] = ['assetId' => 'builtin-lazarus-1', 'versionId' => 'builtin-lazarus-1-v1', 'file' => 'assets/library/lazarus/01.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_1'];
$builtins[] = ['assetId' => 'builtin-lazarus-2', 'versionId' => 'builtin-lazarus-2-v1', 'file' => 'assets/library/lazarus/02.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_2'];
$builtins[] = ['assetId' => 'builtin-lazarus-3', 'versionId' => 'builtin-lazarus-3-v1', 'file' => 'assets/library/lazarus/03.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_3'];
$builtins[] = ['assetId' => 'builtin-lazarus-4', 'versionId' => 'builtin-lazarus-4-v1', 'file' => 'assets/library/lazarus/04.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_4'];
$builtins[] = ['assetId' => 'builtin-lazarus-5', 'versionId' => 'builtin-lazarus-5-v1', 'file' => 'assets/library/lazarus/05.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_5'];
$builtins[] = ['assetId' => 'builtin-lazarus-6', 'versionId' => 'builtin-lazarus-6-v1', 'file' => 'assets/library/lazarus/06.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_6'];
$builtins[] = ['assetId' => 'builtin-lazarus-7', 'versionId' => 'builtin-lazarus-7-v1', 'file' => 'assets/library/lazarus/07.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_7'];
$builtins[] = ['assetId' => 'builtin-lazarus-8', 'versionId' => 'builtin-lazarus-8-v1', 'file' => 'assets/library/lazarus/08.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_8'];
$builtins[] = ['assetId' => 'builtin-lazarus-9', 'versionId' => 'builtin-lazarus-9-v1', 'file' => 'assets/library/lazarus/09.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_9'];
$builtins[] = ['assetId' => 'builtin-lazarus-10', 'versionId' => 'builtin-lazarus-10-v1', 'file' => 'assets/library/lazarus/10.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_10'];
$builtins[] = ['assetId' => 'builtin-lazarus-11', 'versionId' => 'builtin-lazarus-11-v1', 'file' => 'assets/library/lazarus/11.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_11'];
$builtins[] = ['assetId' => 'builtin-lazarus-12', 'versionId' => 'builtin-lazarus-12-v1', 'file' => 'assets/library/lazarus/12.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_12'];
$builtins[] = ['assetId' => 'builtin-lazarus-13', 'versionId' => 'builtin-lazarus-13-v1', 'file' => 'assets/library/lazarus/13.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_13'];
$builtins[] = ['assetId' => 'builtin-lazarus-14', 'versionId' => 'builtin-lazarus-14-v1', 'file' => 'assets/library/lazarus/14.png', 'mime' => 'image/png', 'labelKey' => 'media_lazarus_14'];

$builtins[] = ['assetId' => 'builtin-leadership-1', 'versionId' => 'builtin-leadership-1-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/01_cover.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_1'];
$builtins[] = ['assetId' => 'builtin-leadership-2', 'versionId' => 'builtin-leadership-2-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/02_vote.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_2'];
$builtins[] = ['assetId' => 'builtin-leadership-3', 'versionId' => 'builtin-leadership-3-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/03_bridge.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_3'];
$builtins[] = ['assetId' => 'builtin-leadership-4', 'versionId' => 'builtin-leadership-4-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/04_surprise.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_4'];
$builtins[] = ['assetId' => 'builtin-leadership-5', 'versionId' => 'builtin-leadership-5-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/05_reflection.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_5'];
$builtins[] = ['assetId' => 'builtin-leadership-6', 'versionId' => 'builtin-leadership-6-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/06_washing.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_6'];
$builtins[] = ['assetId' => 'builtin-leadership-7', 'versionId' => 'builtin-leadership-7-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/07_peter.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_7'];
$builtins[] = ['assetId' => 'builtin-leadership-8', 'versionId' => 'builtin-leadership-8-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/08_example.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_8'];
$builtins[] = ['assetId' => 'builtin-leadership-9', 'versionId' => 'builtin-leadership-9-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/09_scene.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_9'];
$builtins[] = ['assetId' => 'builtin-leadership-10', 'versionId' => 'builtin-leadership-10-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/10_replay.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_10'];
$builtins[] = ['assetId' => 'builtin-leadership-11', 'versionId' => 'builtin-leadership-11-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/11_help.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_11'];
$builtins[] = ['assetId' => 'builtin-leadership-12', 'versionId' => 'builtin-leadership-12-v1', 'file' => 'assets/lessons/kto-samyi-glavnyi/images/12_mission.png', 'mime' => 'image/png', 'labelKey' => 'media_leadership_12'];

$builtins[] = ['assetId' => 'builtin-listening-1', 'versionId' => 'builtin-listening-1-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/01.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_1'];
$builtins[] = ['assetId' => 'builtin-listening-2', 'versionId' => 'builtin-listening-2-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/02.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_2'];
$builtins[] = ['assetId' => 'builtin-listening-3', 'versionId' => 'builtin-listening-3-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/03.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_3'];
$builtins[] = ['assetId' => 'builtin-listening-4', 'versionId' => 'builtin-listening-4-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/04.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_4'];
$builtins[] = ['assetId' => 'builtin-listening-5', 'versionId' => 'builtin-listening-5-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/05.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_5'];
$builtins[] = ['assetId' => 'builtin-listening-6', 'versionId' => 'builtin-listening-6-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/06.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_6'];
$builtins[] = ['assetId' => 'builtin-listening-7', 'versionId' => 'builtin-listening-7-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/07.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_7'];
$builtins[] = ['assetId' => 'builtin-listening-8', 'versionId' => 'builtin-listening-8-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/08.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_8'];
$builtins[] = ['assetId' => 'builtin-listening-9', 'versionId' => 'builtin-listening-9-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/09.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_9'];
$builtins[] = ['assetId' => 'builtin-listening-10', 'versionId' => 'builtin-listening-10-v1', 'file' => 'assets/lessons/pochemu-my-ne-slyshim-drug-druga/images/10.png', 'mime' => 'image/png', 'labelKey' => 'media_listening_10'];

$builtins[] = ['assetId' => 'builtin-adult-forgiveness-1', 'versionId' => 'builtin-adult-forgiveness-1-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/01.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_1'];
$builtins[] = ['assetId' => 'builtin-adult-forgiveness-2', 'versionId' => 'builtin-adult-forgiveness-2-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/02.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_2'];
$builtins[] = ['assetId' => 'builtin-adult-forgiveness-3', 'versionId' => 'builtin-adult-forgiveness-3-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/03.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_3'];
$builtins[] = ['assetId' => 'builtin-adult-forgiveness-4', 'versionId' => 'builtin-adult-forgiveness-4-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/04.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_4'];
$builtins[] = ['assetId' => 'builtin-adult-forgiveness-5', 'versionId' => 'builtin-adult-forgiveness-5-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/05.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_5'];
$builtins[] = ['assetId' => 'builtin-adult-forgiveness-6', 'versionId' => 'builtin-adult-forgiveness-6-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/06.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_6'];
$builtins[] = ['assetId' => 'builtin-adult-forgiveness-7', 'versionId' => 'builtin-adult-forgiveness-7-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/07.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_7'];
$builtins[] = ['assetId' => 'builtin-adult-forgiveness-8', 'versionId' => 'builtin-adult-forgiveness-8-v1', 'file' => 'assets/lessons/prostit-znachit-vse-zabyt/images/08.png', 'mime' => 'image/png', 'labelKey' => 'media_adult_forgiveness_8'];

return $builtins;
