<?php

declare(strict_types=1);

namespace App\Application\History;

use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Application\Shared\OwnerMutation;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use Illuminate\Support\Str;

final readonly class RehearsalService
{
    public function __construct(private RuntimeService $runtime, private BlockRegistry $registry, private MediaCatalogue $media) {}

    public function start(string $owner, string $lessonId, int $revision, ?string $locale): array
    {
        return OwnerMutation::transaction([$owner], function () use ($owner, $lessonId, $revision, $locale): array {
            $material = LessonMaterial::query()->where('owner_key', $owner)->lockForUpdate()->find($lessonId)
                ?? throw new ApiProblem('not_found', 404);
            if ($material->revision !== $revision) {
                throw new ApiProblem('revision_conflict', 409);
            }
            $document = LessonDocument::fromArray($material->currentVersion->document, $this->registry);
            $this->media->assertDocument($document, $owner);
            $version = new LessonVersion(['lesson_material_id' => $material->id, 'status' => 'released', 'purpose' => 'rehearsal']);
            $version->id = (string) Str::uuid();
            $data = $document->toArray();
            $data['id'] = $version->id;
            $version->document = $data;
            $version->save();

            return $this->runtime->startSnapshot($owner, $version, $locale, 'rehearsal');
        });
    }

    public function preview(string $owner, string $id, string $audience): array
    {
        return $this->runtime->rehearsalPreview($owner, $id, $audience);
    }

    public function answer(string $owner, string $id, array $body): array
    {
        return $this->runtime->rehearsalAnswer($owner, $id, $body);
    }
}
