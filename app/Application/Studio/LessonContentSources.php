<?php

declare(strict_types=1);

namespace App\Application\Studio;

use App\Models\LessonVersion;

/** Saved references only: stale strict baselines are not editable-draft usages. */
final class LessonContentSources
{
    public static function forVersion(LessonVersion $version): array
    {
        if ($version->status === 'draft' && $version->purpose === 'authoring' && $version->editor_draft !== null) {
            return [['source' => 'editor', 'document' => $version->editor_draft]];
        }
        $sources = [['source' => 'snapshot', 'document' => $version->document]];
        if ($version->editor_draft !== null) {
            $sources[] = ['source' => 'editor', 'document' => $version->editor_draft];
        }

        return $sources;
    }
}
