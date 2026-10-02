<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\MediaCatalogue;
use App\Application\Shared\OwnerMutation;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\CatalogEntry;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use RuntimeException;

final readonly class ReviewedLessonInstaller
{
    public function __construct(private CatalogService $catalog, private BlockRegistry $registry, private MediaCatalogue $media, private DocumentationFiles $files) {}

    public function install(array $source): array
    {
        [$document, $hash] = $this->source($source);

        return OwnerMutation::transaction([$source['ownerKey']], function () use ($source, $document, $hash): array {
            $existing = CatalogEntry::query()->where('slug', $source['slug'])->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->source_revision !== $source['sourceRevision'] || $existing->source_hash !== $hash
                    || $existing->lesson_version_id !== $source['versionId']
                    || $existing->version?->material?->owner_key !== $source['ownerKey']
                    || $existing->version->status !== 'released' || $existing->version->document !== $document->toArray()
                    || $existing->metadata !== $source['metadata']) {
                    throw new RuntimeException('Catalog source receipt differs. Existing published content was preserved; install a separately reviewed new revision.');
                }

                return ['entry' => $existing, 'created' => false];
            }
            if (LessonMaterial::query()->whereKey($source['materialId'])->exists() || LessonVersion::query()->whereKey($source['versionId'])->exists()) {
                throw new RuntimeException('A stable catalog identifier already exists without this receipt. No resources were overwritten.');
            }
            $material = new LessonMaterial(['owner_key' => $source['ownerKey'], 'revision' => 1]);
            $material->id = $source['materialId'];
            $material->save();
            $version = new LessonVersion(['lesson_material_id' => $material->id, 'status' => 'released', 'purpose' => 'authoring', 'document' => $document->toArray()]);
            $version->id = $source['versionId'];
            $version->save();
            $material->current_version_id = $version->id;
            $material->save();
            $entry = $this->catalog->approve($version, $source['metadata'], 'project-owner:explicit-topic-import', $source['slug'], $source['sourceRevision'], $hash);

            return ['entry' => $entry, 'created' => true];
        });
    }

    private function source(array $source, bool $legacyNeighbor = false): array
    {
        $document = LessonDocument::fromArray($source['document'], $this->registry);
        $this->media->assertDocument($document);
        $this->files->assertDocumentation($document->documentation);
        $mediaHashes = [];
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                foreach ($legacyNeighbor && $block->type !== 'core.image' ? [] : $block->media as $reference) {
                    $file = $this->media->resolve($reference['assetId'], $reference['versionId']);
                    $mediaHashes[$reference['versionId']] = hash_file('sha256', $file['path']);
                }
            }
        }
        foreach ($legacyNeighbor ? [] : ($document->documentation?->toArray()['files'] ?? []) as $reference) {
            $file = $this->files->resolve($reference['fileId']);
            $mediaHashes[$reference['fileId']] = hash_file('sha256', $file['path']);
        }
        ksort($mediaHashes);
        $hash = hash('sha256', json_encode([$source, $mediaHashes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return [$document, $hash];
    }

    public function upgrade(array $old, array $source, bool $legacyNeighbor = false): array
    {
        [$previous, $previousHash] = $this->source($old, $legacyNeighbor);
        [$document, $hash] = $this->source($source);
        if ($old['materialId'] !== $source['materialId'] || $old['ownerKey'] !== $source['ownerKey'] || $old['slug'] !== $source['slug'] || $old['versionId'] === $source['versionId']) {
            throw new RuntimeException('Reviewed upgrade requires a new version of the same source material.');
        }

        return OwnerMutation::transaction([$source['ownerKey']], function () use ($old, $source, $previous, $previousHash, $document, $hash): array {
            $entry = CatalogEntry::query()->where('slug', $old['slug'])->lockForUpdate()->first();
            $material = LessonMaterial::query()->whereKey($old['materialId'])->lockForUpdate()->first();
            $original = LessonVersion::query()->find($old['versionId']);
            $existing = LessonVersion::query()->find($source['versionId']);
            if ($entry === null || $material === null || $material->owner_key !== $old['ownerKey'] || $original === null
                || $original->lesson_material_id !== $material->id || $original->status !== 'released' || $original->purpose !== 'authoring'
                || $original->document !== $previous->toArray()) {
                throw new RuntimeException('Reviewed original differs. Versions, copies and classes were preserved.');
            }
            if ($entry->lesson_version_id === $source['versionId'] && $entry->source_revision === $source['sourceRevision'] && $entry->source_hash === $hash
                && $entry->metadata === $source['metadata'] && $existing !== null && $existing->lesson_material_id === $material->id
                && $existing->status === 'released' && $existing->purpose === 'authoring' && $existing->document === $document->toArray()
                && $material->current_version_id === $existing->id) {
                return ['entry' => $entry, 'created' => false];
            }
            if ($entry->lesson_version_id !== $old['versionId'] || $entry->source_revision !== $old['sourceRevision'] || $entry->source_hash !== $previousHash
                || $entry->metadata !== $old['metadata'] || $material->current_version_id !== $old['versionId'] || $existing !== null) {
                throw new RuntimeException('Upgrade receipt differs or identifier is occupied. Published resources were preserved.');
            }
            $version = new LessonVersion(['lesson_material_id' => $material->id, 'status' => 'released', 'purpose' => 'authoring', 'document' => $document->toArray()]);
            $version->id = $source['versionId'];
            $version->save();
            $this->catalog->validatePublication($version, $source['metadata']);
            $entry->repinSourceRelease($old['versionId'], $old['sourceRevision'], $previousHash, $version, $source['sourceRevision'], $hash);
            $entry->metadata = $source['metadata'];
            $entry->save();
            $material->current_version_id = $version->id;
            $material->revision++;
            $material->save();

            return ['entry' => $entry, 'created' => true];
        });
    }
}
