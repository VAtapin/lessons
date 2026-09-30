<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** An immutable content snapshot; release is independent of public catalog visibility. */
final readonly class ReleasedLesson
{
    public function __construct(public string $releaseId, public LessonDocument $document)
    {
        Shape::id($releaseId, 'release.id');
    }

    /** Returns independent editable data, which must be validated again before release. */
    public function newDraft(string $documentId): array
    {
        Shape::id($documentId, 'draft.id');
        if ($documentId === $this->document->id) {
            throw new ValidationException('A new draft requires a new document identifier.');
        }

        return array_replace($this->document->toArray(), ['id' => $documentId]);
    }
}
