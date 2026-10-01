<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\OwnerMutation;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\TeacherDocumentation;
use App\Models\LessonDocumentation;
use App\Models\LessonVersion;
use RuntimeException;

final readonly class NeighborDocumentationInstaller
{
    public function __construct(private NeighborInstaller $neighbor, private DocumentationFiles $files, private BlockRegistry $registry) {}

    public function install(): bool
    {
        // The original snapshot remains available after a reviewed catalog upgrade.
        $legacy = require resource_path('content/kto-moi-blizhnii.php');
        $version = LessonVersion::query()->find($legacy['versionId']);
        $version ??= $this->neighbor->install()['entry']->version;
        if ($version->lesson_material_id !== $legacy['materialId'] || $version->material->owner_key !== $legacy['ownerKey']
            || $version->status !== 'released' || $version->purpose !== 'authoring'
            || $version->document !== LessonDocument::fromArray($legacy['document'], $this->registry)->toArray()) {
            throw new RuntimeException('Original documentation source differs; released content was preserved.');
        }
        $source = require resource_path('content/neighbor-documentation.php');
        $documentation = TeacherDocumentation::fromArray($source, ['ru', 'de']);
        $this->files->assertDocumentation($documentation);
        $hashes = [];
        foreach ($source['files'] as $file) {
            $hashes[$file['fileId']] = $this->files->resolve($file['fileId'])['sha256'];
        }
        ksort($hashes);
        $hash = hash('sha256', json_encode([$source, $hashes], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return OwnerMutation::transaction([$version->material->owner_key], function () use ($version, $source, $hash): bool {
            $existing = LessonDocumentation::query()->whereKey($version->id)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->source_hash, $hash) || $existing->payload !== $source) {
                    throw new RuntimeException('Existing documentation receipt differs; no released content was overwritten.');
                }

                return false;
            }
            LessonDocumentation::query()->create(['lesson_version_id' => $version->id, 'payload' => $source, 'source_hash' => $hash]);

            return true;
        });
    }
}
