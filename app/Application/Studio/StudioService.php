<?php

declare(strict_types=1);

namespace App\Application\Studio;

use App\Application\Catalog\DocumentationFiles;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Application\Shared\OwnerMutation;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\CatalogEntry;
use App\Models\LessonMaterial;
use App\Models\LessonSaveReceipt;
use App\Models\LessonVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class StudioService
{
    public function __construct(private BlockRegistry $registry, private MediaCatalogue $media, private CurrentDraftResolver $drafts, private DocumentationFiles $documentationFiles) {}

    public function findOwned(string $ownerKey, string $lessonId): LessonMaterial
    {
        $material = LessonMaterial::query()->where('owner_key', $ownerKey)->with('currentVersion')->find($lessonId)
            ?? throw new ApiProblem('not_found', 404);
        $this->assertAvailable($material);

        return $material;
    }

    public function listOwned(string $ownerKey, bool $archived = false): array
    {
        return LessonMaterial::query()->where('owner_key', $ownerKey)->whereNull('purged_at')->where('archived', $archived)->with('currentVersion')
            ->orderByDesc('updated_at')->orderBy('id')->get()->map(fn (LessonMaterial $material): array => $this->summary($material))->all();
    }

    public function archive(string $ownerKey, string $lessonId, int $expectedRevision, bool $archived): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessonId, $expectedRevision, $archived): array {
            $material = $this->lockOwned($ownerKey, $lessonId, $expectedRevision, includeArchived: true);
            if ($material->archived !== $archived) {
                $material->archived = $archived;
                $material->archived_at = $archived ? CarbonImmutable::now('UTC') : null;
                $material->revision++;
                $material->save();
            }

            return $this->summary($material->load('currentVersion'));
        });
    }

    /** Permanent removal from authoring; immutable snapshots remain available to existing classes. */
    public function purge(string $ownerKey, array $lessons): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessons): array {
            $materials = [];
            foreach ($lessons as $lesson) {
                $material = $this->lockOwned($ownerKey, $lesson['id'], $lesson['expectedRevision'], includeArchived: true);
                if (! $material->archived) {
                    throw new ApiProblem('invalid_state', 409);
                }
                if (CatalogEntry::query()->whereIn('lesson_version_id', $material->versions()->select('id'))->exists()) {
                    throw new ApiProblem('catalog_source_protected', 409);
                }
                $materials[] = $material;
            }
            $now = CarbonImmutable::now('UTC');
            foreach ($materials as $material) {
                $material->purged_at = $now;
                $material->revision++;
                $material->save();
            }

            return ['deletedIds' => array_map(fn (LessonMaterial $material): string => $material->id, $materials)];
        });
    }

    private function summary(LessonMaterial $material): array
    {
        $version = $material->currentVersion;
        $document = $version->editor_draft ?? $version->document;

        return ['id' => $material->id, 'title' => $document['content'][$document['defaultLocale']]['title'],
            'revision' => $material->revision, 'status' => $version->status, 'favorite' => (bool) $material->favorite,
            'archived' => (bool) $material->archived, 'archivedAt' => $material->archived_at?->utc()->toISOString(),
            'updatedAt' => $material->updated_at->toIso8601String()];
    }

    public function create(string $ownerKey, array $payload): LessonMaterial
    {
        $document = $this->validate($payload, $ownerKey);

        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $document): LessonMaterial {
            $material = LessonMaterial::query()->create(['owner_key' => $ownerKey, 'revision' => 1]);
            $version = $this->newDraft($material, $document);
            $material->current_version_id = $version->id;
            $material->save();

            return $material->load('currentVersion');
        });
    }

    public function save(string $ownerKey, string $lessonId, int $expectedRevision, array $payload): LessonMaterial
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessonId, $expectedRevision, $payload): LessonMaterial {
            $material = $this->lockOwned($ownerKey, $lessonId);
            if ($material->currentVersion->editor_draft !== null) {
                throw new EditorProblem('editor_update_required', 409, lesson: $this->present($material));
            }
            $this->checkRevision($material, $expectedRevision);
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

    public function saveEditor(string $ownerKey, string $lessonId, EditorSaveRequest $request): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessonId, $request): array {
            $material = $this->lockOwned($ownerKey, $lessonId);
            $receipt = LessonSaveReceipt::query()->where('lesson_material_id', $lessonId)->where('save_id', $request->saveId)->first();
            if ($receipt !== null && $receipt->created_at->greaterThan(CarbonImmutable::now('UTC')->subDays(30))) {
                if (! hash_equals($receipt->fingerprint, $request->fingerprint)) {
                    throw new EditorProblem('save_conflict', 409, lesson: $this->present($material));
                }

                return $this->acknowledge($material, $receipt);
            }
            if ($receipt !== null || $material->revision !== $request->revision) {
                throw new EditorProblem('revision_conflict', 409, lesson: $this->present($material));
            }
            $draft = $this->drafts->parse($request->document, $ownerKey);
            $working = $draft->toArray();
            $version = $material->currentVersion;
            $baseline = $version->document;
            if ($draft->readiness()['readyLocales'] === $working['locales']) {
                $baseline = $draft->readyDocument($working['locales'])->toArray();
            }
            if ($version->status === 'released') {
                $version = new LessonVersion(['lesson_material_id' => $material->id, 'status' => 'draft', 'purpose' => 'authoring']);
                $version->id = (string) Str::uuid();
                $material->current_version_id = $version->id;
            }
            $baseline['id'] = $working['id'] = $version->id;
            $version->document = $baseline;
            $version->editor_draft = $working;
            $version->save();
            $material->revision++;
            $material->save();
            $receipt = LessonSaveReceipt::query()->create(['lesson_material_id' => $lessonId, 'save_id' => $request->saveId,
                'fingerprint' => $request->fingerprint, 'applied_revision' => $material->revision, 'applied_version_id' => $version->id]);

            return $this->acknowledge($material->load('currentVersion'), $receipt);
        });
    }

    public function release(string $ownerKey, string $lessonId, int $expectedRevision, ?array $locales = null, ?string $selectedLocale = null): LessonVersion
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessonId, $expectedRevision, $locales, $selectedLocale): LessonVersion {
            $material = $this->lockOwned($ownerKey, $lessonId, $expectedRevision);
            $version = $material->currentVersion;
            $document = $this->drafts->snapshot($version, $ownerKey, $locales, $selectedLocale);
            if ($version->status === 'draft') {
                $version->document = $document->toArray();
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

        return ['id' => $material->id, 'revision' => $material->revision, 'status' => $version->status, 'favorite' => (bool) $material->favorite,
            'versionId' => $version->id, 'document' => $version->editor_draft ?? $version->document, 'readiness' => $this->drafts->working($version)->readiness()];
    }

    /** Copy a saved block under the same owner/revision lock used by editing. */
    public function ownedBlockSnapshot(string $ownerKey, string $lessonId, int $expectedRevision, string $blockId): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessonId, $expectedRevision, $blockId): array {
            $material = $this->lockOwned($ownerKey, $lessonId, $expectedRevision);

            return $this->drafts->block($material->currentVersion, $ownerKey, $blockId);
        });
    }

    public function preview(string $ownerKey, string $lessonId, int $revision, array $payload, Audience $audience, string $locale, string $stageId): array
    {
        $material = $this->findOwned($ownerKey, $lessonId);
        $this->checkRevision($material, $revision);
        $draft = $this->drafts->parse($payload, $ownerKey);

        return ['audience' => $audience->value, 'locale' => $locale, 'revision' => $material->revision,
            'stage' => $this->drafts->preview($draft, $ownerKey, $audience, $locale, $stageId), 'readiness' => $draft->readiness()];
    }

    private function acknowledge(LessonMaterial $material, LessonSaveReceipt $receipt): array
    {
        return ['lesson' => $this->present($material), 'acknowledgedSaveId' => $receipt->save_id,
            'appliedRevision' => $receipt->applied_revision, 'appliedVersionId' => $receipt->applied_version_id];
    }

    private function lockOwned(string $ownerKey, string $lessonId, ?int $expectedRevision = null, bool $includeArchived = false): LessonMaterial
    {
        $material = LessonMaterial::query()->where('owner_key', $ownerKey)->lockForUpdate()->find($lessonId)
            ?? throw new ApiProblem('not_found', 404);
        if ($material->purged_at !== null) {
            throw new ApiProblem('not_found', 404);
        }
        if (! $includeArchived) {
            $this->assertAvailable($material);
        }
        if ($expectedRevision !== null) {
            $this->checkRevision($material, $expectedRevision);
        }

        return $material;
    }

    private function assertAvailable(LessonMaterial $material): void
    {
        if ($material->purged_at !== null) {
            throw new ApiProblem('not_found', 404);
        }
        if ($material->archived) {
            throw new ApiProblem('lesson_in_trash', 409);
        }
    }

    private function checkRevision(LessonMaterial $material, int $revision): void
    {
        if ($material->revision !== $revision) {
            throw new ApiProblem('revision_conflict', 409);
        }
    }

    private function validate(array $payload, string $ownerKey): LessonDocument
    {
        try {
            $document = LessonDocument::fromArray($payload, $this->registry);
        } catch (ValidationException) {
            throw new ApiProblem('invalid_document', 422);
        }
        $this->media->assertDocument($document, $ownerKey);
        $this->documentationFiles->assertDocumentation($document->documentation);

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
