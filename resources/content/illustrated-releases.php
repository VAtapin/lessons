<?php

declare(strict_types=1);

$mapping = json_decode(file_get_contents(__DIR__.'/illustrated-source.json'), true, flags: JSON_THROW_ON_ERROR);
$sources = ['neighbor' => 'kto-moi-blizhnii-v3', 'words' => 'slova-ranyat', 'zakkhei' => 'zakkhei', 'judge' => 'ne-speshi-sudit', 'sheep' => 'poteryannaya-ovechka', 'talent' => 'talant'];
$result = [];
foreach ($sources as $key => $file) {
    $old = (static fn ($path) => require $path)(__DIR__.'/'.$file.'.php');
    $next = $old;
    $next['sourceRevision'] = $key.'-illustrated-2026-10-02-v2';
    $next['versionId'] = substr(hash('sha256', $next['sourceRevision']), 0, 8).'-3a2b-4e50-9d80-02cd60002026';
    $next['document']['id'] = $next['versionId'];
    foreach ($next['document']['documentation']['files'] as &$reference) {
        $reference['fileId'] = $mapping[$key]['changedFiles'][$reference['fileId']] ?? $reference['fileId'];
    }
    unset($reference);
    if ($key === 'words') {
        foreach ([6, 7] as $n) {
            $next['document']['documentation']['files'][] = ['fileId' => 'words-file-'.$n.'-ru-v2', 'kind' => 'plan', 'locale' => 'ru'];
        }
    }
    foreach ($next['document']['stages'] as $index => &$stage) {
        // The five/four sheep game retains its exact count and single-sheep media.
        // Its full-slide artwork is available independently in the media/block library.
        if ($key === 'sheep' && $index === 1 || $key === 'neighbor' && $index === 12) {
            continue;
        }
        $slide = $mapping[$key]['slides'][$mapping[$key]['mapping'][$index][0] - 1];
        $picture = ['id' => $stage['id'].'-illustrated-image', 'type' => 'core.image', 'schemaVersion' => 1,
            'content' => array_map(static fn ($content) => ['alt' => $content['title'], 'caption' => ''], $stage['content']),
            'config' => ['fit' => 'contain'], 'media' => ['image' => $slide['media']]];
        $found = false;
        foreach ($stage['blocks'] as &$block) {
            if ($block['type'] === 'core.image') {
                $block['media'] = $picture['media'];
                $found = true;
                break;
            }
        }
        unset($block);
        if (! $found) {
            array_splice($stage['blocks'], 1, 0, [$picture]);
        }
        if (count($mapping[$key]['mapping'][$index]) === 2) {
            $nextSlide = $mapping[$key]['slides'][$mapping[$key]['mapping'][$index][1] - 1];
            foreach ($stage['blocks'] as &$block) {
                if ($block['type'] === 'core.presentation' && $block['config']['kind'] === 'reveal') {
                    $block['media'] = ['image' => $nextSlide['media']];
                    break;
                }
            }
            unset($block);
        }
    }
    unset($stage);
    $next['metadata']['cover'] = $mapping[$key]['slides'][0]['media'];
    $result[$key] = ['old' => $old, 'next' => $next];
}

return $result;
