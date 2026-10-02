<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\LibraryMetadata;
use App\Application\Shared\MediaCatalogue;
use App\Application\Shared\OwnerMutation;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\Shape;
use App\Models\BlockTemplateRecord;
use App\Models\BlockTemplateVersion;
use App\Models\CommonTemplate;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/** Trusted content installation only. It is not a user-facing administrative authorization bypass. */
final readonly class CommonStarterInstaller
{
    private const NAMESPACE_ID = '042fc54b-8e14-46c5-a3da-7d62f92e3b64';

    public function __construct(private BlockRegistry $registry, private MediaCatalogue $media, private LibraryMetadata $metadata) {}

    public function install(?array $source = null): array
    {
        $source ??= require resource_path('content/common-starter-v1.php');
        Shape::object($source, ['id', 'sourceRevision', 'templates'], [], 'commonPack');
        Shape::id($source['id'], 'commonPack.id');
        Shape::boundedText($source['sourceRevision'], 'commonPack.sourceRevision', 120);
        $prepared = [];
        foreach (Shape::list($source['templates'], 'commonPack.templates') as $entry) {
            $slug = Shape::id($entry['slug'], 'commonPack.template.slug');
            if (isset($prepared[$slug]) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
                throw new RuntimeException('Starter template slugs must be unique and stable.');
            }
            if ($entry['locales'] !== ['ru', 'de'] || ! in_array($entry['defaultLocale'], $entry['locales'], true)) {
                throw new RuntimeException('Starter templates require full RU/DE translations.');
            }
            foreach (['title', 'description'] as $field) {
                TaxonomyService::labels(['ru' => $entry['labels']['ru'][$field] ?? null, 'de' => $entry['labels']['de'][$field] ?? null]);
            }
            $block = BlockInstance::fromArray($entry['block'], $this->registry, $entry['locales']);
            $this->media->assertBlock($block);
            $metadata = $this->metadata->parse($entry['attribution']);
            $mediaHashes = [];
            if (isset($block->media['image'])) {
                $image = $block->media['image'];
                $resolved = $this->media->resolve($image['assetId'], $image['versionId']);
                $mediaHashes[$image['versionId']] = hash_file('sha256', $resolved['path']);
            }
            // Include pack membership/order as well as this entry's exact media bytes.
            // Adding a new entry under an already released pack identity is source drift too.
            $hash = hash('sha256', json_encode([$source, $mediaHashes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $attribution = array_intersect_key($metadata, array_flip(['author', 'source', 'rightsBasis', 'usageRights']));
            $attribution['installation'] = ['pack' => $source['id'], 'sourceRevision' => $source['sourceRevision'], 'sourceHash' => $hash];
            $prepared[$slug] = ['block' => $block->toArray(), 'metadata' => $metadata, 'attribution' => $attribution, 'entry' => $entry,
                'recordId' => self::identity($source['id'], $slug, 'record'), 'versionId' => self::identity($source['id'], $slug, 'version-v1'),
                'commonId' => self::identity($source['id'], $slug, 'common')];
        }

        return OwnerMutation::transaction([CommonTemplateService::OWNER], function () use ($prepared): array {
            $installed = 0;
            $preserved = 0;
            foreach ($prepared as $seed) {
                $record = BlockTemplateRecord::query()->lockForUpdate()->find($seed['recordId']);
                $common = CommonTemplate::query()->lockForUpdate()->find($seed['commonId']);
                $version = BlockTemplateVersion::query()->find($seed['versionId']);
                if ($record !== null || $common !== null || $version !== null) {
                    if ($record === null || $common === null || $version === null
                        || $record->owner_key !== CommonTemplateService::OWNER || $common->block_template_record_id !== $record->id
                        || $version->block_template_record_id !== $record->id || $version->version_no !== 1
                        || $version->locales !== $seed['entry']['locales'] || $version->default_locale !== $seed['entry']['defaultLocale']
                        || $version->block !== $seed['block'] || $version->attribution !== $seed['attribution']
                        || ! $record->versions()->whereKey($record->current_version_id)->exists()) {
                        throw new RuntimeException('Starter source receipt or stable identifier differs. No templates were overwritten.');
                    }
                    // The initial immutable receipt remains authoritative; newer editor versions,
                    // changed labels/attribution and hidden/archived decisions are preserved.
                    $preserved++;

                    continue;
                }
                if (CommonTemplate::query()->where('block_template_record_id', $seed['recordId'])->exists()) {
                    throw new RuntimeException('Starter record has an unexpected common publication. No templates were overwritten.');
                }
                $metadata = $seed['metadata'];
                $record = new BlockTemplateRecord(['owner_key' => CommonTemplateService::OWNER, 'title' => $metadata['title'], 'tags' => $metadata['tags'],
                    'author' => $metadata['author'], 'source' => $metadata['source'], 'rights_basis' => $metadata['rightsBasis'], 'usage_rights' => $metadata['usageRights'],
                    'revision' => 1, 'archived' => false]);
                $record->id = $seed['recordId'];
                $record->save();
                $version = new BlockTemplateVersion(['block_template_record_id' => $record->id, 'version_no' => 1, 'block' => $seed['block'],
                    'locales' => $seed['entry']['locales'], 'default_locale' => $seed['entry']['defaultLocale'], 'attribution' => $seed['attribution']]);
                $version->id = $seed['versionId'];
                $version->save();
                $record->current_version_id = $version->id;
                $record->save();
                $common = new CommonTemplate(['block_template_record_id' => $record->id, 'labels' => $seed['entry']['labels'], 'visible' => true]);
                $common->id = $seed['commonId'];
                $common->save();
                $installed++;
            }

            return ['installed' => $installed, 'preserved' => $preserved, 'total' => count($prepared)];
        });
    }

    public static function identity(string $pack, string $slug, string $entity): string
    {
        return Uuid::uuid5(self::NAMESPACE_ID, $pack.'/'.$slug.'/'.$entity)->toString();
    }
}
