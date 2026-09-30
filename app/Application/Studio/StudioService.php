<?php

declare(strict_types=1);

namespace App\Application\Studio;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class StudioService
{
    public function __construct(private BlockRegistry $registry, private MediaCatalogue $media) {}

    public function findOwned(string $ownerKey, string $lessonId): LessonMaterial
    {
        return LessonMaterial::query()->where('owner_key', $ownerKey)->with('currentVersion')->find($lessonId)
            ?? throw new ApiProblem('not_found', 404);
    }

    public function listOwned(string $ownerKey): array
    {
        return LessonMaterial::query()->where('owner_key', $ownerKey)->with('currentVersion')
            ->orderByDesc('updated_at')->orderBy('id')->get()->map(function (LessonMaterial $material): array {
                $version = $material->currentVersion;
                $document = $version->document;

                return ['id' => $material->id, 'title' => $document['content'][$document['defaultLocale']]['title'],
                    'revision' => $material->revision, 'status' => $version->status,
                    'updatedAt' => $material->updated_at->toIso8601String()];
            })->all();
    }

    public function create(string $ownerKey, array $payload): LessonMaterial
    {
        $document = $this->validate($payload, $ownerKey);

        return DB::transaction(function () use ($ownerKey, $document): LessonMaterial {
            $material = LessonMaterial::query()->create(['owner_key' => $ownerKey, 'revision' => 1]);
            $version = $this->newDraft($material, $document);
            $material->current_version_id = $version->id;
            $material->save();

            return $material->load('currentVersion');
        });
    }

    public function save(string $ownerKey, string $lessonId, int $expectedRevision, array $payload): LessonMaterial
    {
        return DB::transaction(function () use ($ownerKey, $lessonId, $expectedRevision, $payload): LessonMaterial {
            $material = $this->lockOwned($ownerKey, $lessonId, $expectedRevision);
            $document = $this->validate($payload, $ownerKey);
            $version = $material->currentVersion;
            if ($version->status === 'released') {
                $version = $this->newDraft($material, $document);
                $material->current_version_id = $version->id;
            } else {
                $data = $document->toArray();
                $data['id'] = $version->id;
                $version->document = $data;
                $version->save();
            }

            $material->revision++;
            $material->save();

            return $material->load('currentVersion');
        });
    }

    public function release(string $ownerKey, string $lessonId, int $expectedRevision): LessonVersion
    {
        return DB::transaction(function () use ($ownerKey, $lessonId, $expectedRevision): LessonVersion {
            $material = $this->lockOwned($ownerKey, $lessonId, $expectedRevision);
            $version = $material->currentVersion;
            $this->validate($version->document, $ownerKey);
            if ($version->status === 'draft') {
                $version->status = 'released';
                $version->save();
                $material->revision++;
                $material->save();
            }

            return $version->setRelation('material', $material);
        });
    }

    public function present(LessonMaterial $material): array
    {
        $version = $material->currentVersion;

        return ['id' => $material->id, 'revision' => $material->revision, 'status' => $version->status,
            'versionId' => $version->id, 'document' => $version->document];
    }

    /** Copy a saved block under the same owner/revision lock used by editing. */
    public function ownedBlockSnapshot(string $ownerKey, string $lessonId, int $expectedRevision, string $blockId): array
    {
        return DB::transaction(function () use ($ownerKey, $lessonId, $expectedRevision, $blockId): array {
            $material = $this->lockOwned($ownerKey, $lessonId, $expectedRevision);
            $document = $this->validate($material->currentVersion->document, $ownerKey);
            foreach ($document->stages as $stage) {
                foreach ($stage->blocks as $block) {
                    if ($block->id === $blockId) {
                        return ['block' => $block, 'locales' => $document->locales, 'defaultLocale' => $document->defaultLocale];
                    }
                }
            }

            throw new ApiProblem('not_found', 404);
        });
    }

    private function lockOwned(string $ownerKey, string $lessonId, int $expectedRevision): LessonMaterial
    {
        $material = LessonMaterial::query()->where('owner_key', $ownerKey)->lockForUpdate()->find($lessonId)
            ?? throw new ApiProblem('not_found', 404);
        if ($material->revision !== $expectedRevision) {
            throw new ApiProblem('revision_conflict', 409);
        }

        return $material;
    }

    private function validate(array $payload, string $ownerKey): LessonDocument
    {
        try {
            $document = LessonDocument::fromArray($payload, $this->registry);
        } catch (ValidationException) {
            throw new ApiProblem('invalid_document', 422);
        }
        $this->media->assertDocument($document, $ownerKey);

        return $document;
    }

    private function newDraft(LessonMaterial $material, LessonDocument $document): LessonVersion
    {
        $version = new LessonVersion(['lesson_material_id' => $material->id, 'status' => 'draft']);
        $version->id = (string) Str::uuid();
        $data = $document->toArray();
        $data['id'] = $version->id;
        $version->document = $data;
        $version->save();

        return $version;
    }
}
