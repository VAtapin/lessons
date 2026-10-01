<?php

declare(strict_types=1);

namespace App\Application\Library;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\LibraryMetadata;
use App\Application\Shared\MediaCatalogue;
use App\Application\Shared\OwnerMutation;
use App\Application\Studio\StudioService;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\BlockTemplate;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;
use App\Models\BlockTemplateRecord;
use App\Models\BlockTemplateVersion;
use App\Models\LessonVersion;
use Illuminate\Support\Str;

final readonly class TemplateLibraryService
{
    public function __construct(private StudioService $studio, private BlockRegistry $registry, private MediaCatalogue $media, private LibraryMetadata $metadata) {}

    public function findOwned(string $ownerKey, string $id): BlockTemplateRecord
    {
        return BlockTemplateRecord::query()->where('owner_key', $ownerKey)->with('currentVersion')->find($id)
            ?? throw new ApiProblem('not_found', 404);
    }

    public function listOwned(string $ownerKey, array $filters): array
    {
        $query = BlockTemplateRecord::query()->where('owner_key', $ownerKey)->where('archived', (bool) ($filters['archived'] ?? false))->with('currentVersion')->orderByDesc('updated_at')->orderBy('id');
        if (isset($filters['q']) && $filters['q'] !== '') {
            // Bound query plus escaping treats '%' and '_' as literal search text.
            $text = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']);
            $query->whereRaw("title LIKE ? ESCAPE '!'", ['%'.$text.'%']);
        }
        if (isset($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        return $query->get()->filter(function (BlockTemplateRecord $record) use ($filters): bool {
            return (! isset($filters['type']) || $record->currentVersion->block['type'] === $filters['type'])
                && (! isset($filters['locale']) || in_array($filters['locale'], $record->currentVersion->locales, true));
        })->map(fn (BlockTemplateRecord $record) => [
            'id' => $record->id, 'title' => $record->title, 'tags' => $record->tags,
            'type' => $record->currentVersion->block['type'], 'locales' => $record->currentVersion->locales,
            'revision' => $record->revision, 'currentVersionId' => $record->current_version_id, 'archived' => $record->archived,
        ])->values()->all();
    }

    public function createFromLesson(string $ownerKey, string $lessonId, int $expectedLessonRevision, string $blockId, array $metadata): BlockTemplateRecord
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessonId, $expectedLessonRevision, $blockId, $metadata): BlockTemplateRecord {
            $source = $this->studio->ownedBlockSnapshot($ownerKey, $lessonId, $expectedLessonRevision, $blockId);
            $metadata = $this->metadata->parse($metadata);
            $record = BlockTemplateRecord::create($this->recordMetadata($metadata) + ['owner_key' => $ownerKey, 'revision' => 1, 'archived' => false]);
            $version = $this->newVersion($record, $source['block'], $source['locales'], $source['defaultLocale'], $metadata, 1);
            $record->current_version_id = $version->id;
            $record->save();

            return $record->load('currentVersion');
        });
    }

    public function update(string $ownerKey, string $id, int $expectedRevision, array $locales, string $defaultLocale, array $payload, array $metadata): BlockTemplateRecord
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $id, $expectedRevision, $locales, $defaultLocale, $payload, $metadata): BlockTemplateRecord {
            $record = $this->lockOwned($ownerKey, $id, $expectedRevision);
            $metadata = $this->metadata->parse($metadata);
            $block = $this->validateBlock($ownerKey, $payload, $locales, $defaultLocale);
            $version = $this->newVersion($record, $block, $locales, $defaultLocale, $metadata, $record->currentVersion->version_no + 1);
            $record->fill($this->recordMetadata($metadata));
            $record->current_version_id = $version->id;
            $record->revision++;
            $record->save();

            return $record->load('currentVersion');
        });
    }

    public function instantiate(string $ownerKey, string $id, string $versionId, array $locales): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $id, $versionId, $locales): array {
            $record = BlockTemplateRecord::query()->where('owner_key', $ownerKey)->lockForUpdate()->find($id)
                ?? throw new ApiProblem('not_found', 404);
            $version = $record->versions()->find($versionId) ?? throw new ApiProblem('not_found', 404);
            if ($record->archived) {
                throw new ApiProblem('archived_resource', 409);
            }
            try {
                Shape::locales($locales);
                foreach ($locales as $locale) {
                    if (! in_array($locale, $version->locales, true)) {
                        throw new ValidationException('Requested translation is unavailable.');
                    }
                }
                $source = BlockInstance::fromArray($version->block, $this->registry, $version->locales);
                $copy = (new BlockTemplate($record->id, $version->id, $source))->instantiate((string) Str::uuid())->toArray();
                $copy['content'] = array_intersect_key($copy['content'], array_flip($locales));
                if (isset($copy['teacherNotes'])) {
                    $copy['teacherNotes'] = array_intersect_key($copy['teacherNotes'], array_flip($locales));
                }
                $instance = BlockInstance::fromArray($copy, $this->registry, $locales);
            } catch (ValidationException) {
                throw new ApiProblem('invalid_document', 422);
            }
            $this->media->assertBlock($instance, $ownerKey);

            return $instance->toArray();
        });
    }

    public function archive(string $ownerKey, string $id, int $expectedRevision, bool $archived): BlockTemplateRecord
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $id, $expectedRevision, $archived): BlockTemplateRecord {
            $record = $this->lockOwned($ownerKey, $id, $expectedRevision);
            if ($record->archived !== $archived) {
                $record->archived = $archived;
                $record->revision++;
                $record->save();
            }

            return $record->load('currentVersion');
        });
    }

    public function present(BlockTemplateRecord $record): array
    {
        return [
            'id' => $record->id, 'title' => $record->title, 'tags' => $record->tags, 'author' => $record->author,
            'source' => $record->source, 'rightsBasis' => $record->rights_basis, 'usageRights' => $record->usage_rights,
            'revision' => $record->revision, 'currentVersionId' => $record->current_version_id, 'archived' => $record->archived,
            'versions' => $record->versions()->get()->map(fn (BlockTemplateVersion $version) => [
                'id' => $version->id, 'versionNo' => $version->version_no, 'locales' => $version->locales,
                'defaultLocale' => $version->default_locale, 'block' => $version->block, 'attribution' => $version->attribution,
                'createdAt' => $version->created_at->toIso8601String(),
            ])->all(),
            'usages' => $this->usages($record->owner_key, $record->id),
        ];
    }

    /** Derived from saved owned snapshots; arbitrary legacy origin is not authority. */
    public function usages(string $ownerKey, string $id): array
    {
        $record = $this->findOwned($ownerKey, $id);
        $versionIds = $record->versions()->pluck('id')->all();
        $usages = [];
        $lessons = LessonVersion::query()->whereHas('material', fn ($query) => $query->where('owner_key', $ownerKey))->orderBy('created_at')->orderBy('id')->get();
        foreach ($lessons as $lesson) {
            $document = $lesson->document;
            foreach ($document['stages'] as $stage) {
                foreach ($stage['blocks'] as $block) {
                    $origin = $block['origin'] ?? null;
                    if (is_array($origin) && ($origin['templateId'] ?? null) === $id && in_array($origin['versionId'] ?? null, $versionIds, true)) {
                        $usages[] = [
                            'lessonId' => $lesson->lesson_material_id, 'lessonVersionId' => $lesson->id,
                            'title' => $document['content'][$document['defaultLocale']]['title'], 'status' => $lesson->status, 'blockId' => $block['id'],
                        ];
                    }
                }
            }
        }

        return $usages;
    }

    private function lockOwned(string $ownerKey, string $id, int $revision): BlockTemplateRecord
    {
        $record = BlockTemplateRecord::query()->where('owner_key', $ownerKey)->lockForUpdate()->find($id)
            ?? throw new ApiProblem('not_found', 404);
        if ($record->revision !== $revision) {
            throw new ApiProblem('revision_conflict', 409);
        }

        return $record;
    }

    private function validateBlock(string $ownerKey, array $payload, array $locales, string $defaultLocale): BlockInstance
    {
        try {
            Shape::locales($locales);
            if (! in_array($defaultLocale, $locales, true)) {
                throw new ValidationException('Default locale must be declared.');
            }
            $block = BlockInstance::fromArray($payload, $this->registry, $locales);
        } catch (ValidationException) {
            throw new ApiProblem('invalid_document', 422);
        }
        $this->media->assertBlock($block, $ownerKey);

        return $block;
    }

    private function newVersion(BlockTemplateRecord $record, BlockInstance $block, array $locales, string $defaultLocale, array $metadata, int $number): BlockTemplateVersion
    {
        return BlockTemplateVersion::create([
            'block_template_record_id' => $record->id, 'version_no' => $number, 'block' => $block->toArray(),
            'locales' => $locales, 'default_locale' => $defaultLocale,
            'attribution' => array_intersect_key($metadata, array_flip(['author', 'source', 'rightsBasis', 'usageRights'])),
        ]);
    }

    private function recordMetadata(array $metadata): array
    {
        return [
            'title' => $metadata['title'], 'tags' => $metadata['tags'], 'author' => $metadata['author'], 'source' => $metadata['source'],
            'rights_basis' => $metadata['rightsBasis'], 'usage_rights' => $metadata['usageRights'],
        ];
    }
}
