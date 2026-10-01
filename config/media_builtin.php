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

return $builtins;
