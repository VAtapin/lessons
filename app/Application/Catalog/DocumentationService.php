<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\TeacherDocumentation;
use App\Models\LessonDocumentation;
use App\Models\LessonVersion;

final readonly class DocumentationService
{
    public function forVersion(LessonVersion $version, LessonDocument $document): ?TeacherDocumentation
    {
        if ($document->documentation !== null) {
            return $document->documentation;
        }
        $receipt = LessonDocumentation::query()->find($version->id);

        return $receipt === null ? null : TeacherDocumentation::fromArray($receipt->payload, $document->locales);
    }
}
