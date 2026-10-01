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

final readonly class NeighborUpgradeInstaller
{
    public function __construct(private CatalogService $catalog, private BlockRegistry $registry, private MediaCatalogue $media) {}

    public function install(): array
    {
        $old = require resource_path('content/kto-moi-blizhnii.php');
        $next = require resource_path('content/kto-moi-blizhnii-v2.php');
        [$oldDocument, $oldHash] = $this->source($old);
        [$document, $hash] = $this->source($next);

        return OwnerMutation::transaction([$old['ownerKey']], function () use ($old, $next, $oldDocument, $document, $oldHash, $hash): array {
            $entry = CatalogEntry::query()->where('slug', $old['slug'])->lockForUpdate()->first();
            $material = LessonMaterial::query()->whereKey($old['materialId'])->lockForUpdate()->first();
            $original = LessonVersion::query()->whereKey($old['versionId'])->first();
            if ($entry === null || $material === null || $original === null
                || $material->owner_key !== $old['ownerKey'] || $original->lesson_material_id !== $material->id
                || $original->status !== 'released' || $original->purpose !== 'authoring' || $original->document !== $oldDocument->toArray()
                || $entry->metadata !== $old['metadata'] || $next['materialId'] !== $old['materialId'] || $next['ownerKey'] !== $old['ownerKey']
                || $next['slug'] !== $old['slug'] || $next['metadata'] !== $old['metadata']) {
                throw new RuntimeException('Reviewed neighbor source differs. Existing versions, copies and classes were preserved.');
            }
            $existing = LessonVersion::query()->whereKey($next['versionId'])->first();
            if ($entry->lesson_version_id === $next['versionId'] && $entry->source_revision === $next['sourceRevision'] && $entry->source_hash === $hash
                && $existing !== null && $existing->lesson_material_id === $material->id && $existing->status === 'released' && $existing->purpose === 'authoring'
                && $existing->document === $document->toArray() && $material->current_version_id === $existing->id && $material->revision === 2) {
                return ['entry' => $entry, 'created' => false];
            }
            if ($entry->lesson_version_id !== $old['versionId'] || $entry->source_revision !== $old['sourceRevision'] || $entry->source_hash !== $oldHash
                || $material->current_version_id !== $old['versionId'] || $material->revision !== 1 || $existing !== null) {
                throw new RuntimeException('Neighbor upgrade receipt differs or the new identifier is occupied. No published resources were overwritten.');
            }
            $version = new LessonVersion(['lesson_material_id' => $material->id, 'status' => 'released', 'purpose' => 'authoring', 'document' => $document->toArray()]);
            $version->id = $next['versionId'];
            $version->save();
            $this->catalog->validatePublication($version, $next['metadata']);
            $entry->repinSourceRelease($old['versionId'], $old['sourceRevision'], $oldHash, $version, $next['sourceRevision'], $hash);
            $material->current_version_id = $version->id;
            $material->revision = 2;
            $material->save();

            return ['entry' => $entry, 'created' => true];
        });
    }

    private function source(array $source): array
    {
        $document = LessonDocument::fromArray($source['document'], $this->registry);
        $this->media->assertDocument($document);
        $hashes = [];
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                if ($block->type === 'core.image') {
                    $reference = $block->media['image'];
                    $hashes[$reference['versionId']] = hash_file('sha256', $this->media->resolve($reference['assetId'], $reference['versionId'])['path']);
                }
            }
        }
        ksort($hashes);

        return [$document, hash('sha256', json_encode([$source, $hashes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    }
}
