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
        $document = LessonDocument::fromArray($source['document'], $this->registry);
        $this->media->assertDocument($document);
        $this->files->assertDocumentation($document->documentation);
        $mediaHashes = [];
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                if ($block->type === 'core.image') {
                    $reference = $block->media['image'];
                    $file = $this->media->resolve($reference['assetId'], $reference['versionId']);
                    $mediaHashes[$reference['versionId']] = hash_file('sha256', $file['path']);
                }
            }
        }
        foreach ($document->documentation?->toArray()['files'] ?? [] as $reference) {
            $file = $this->files->resolve($reference['fileId']);
            $mediaHashes[$reference['fileId']] = hash_file('sha256', $file['path']);
        }
        ksort($mediaHashes);
        $hash = hash('sha256', json_encode([$source, $mediaHashes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

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

                // Retraction/approval decisions are preserved; reinstall never silently republishes.
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
}
