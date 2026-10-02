<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Library\TemplateLibraryService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Application\Shared\OwnerMutation;
use App\Application\Studio\StudioService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\ValidationException;
use App\Models\CommonTemplate;
use App\Models\User;
use Illuminate\Support\Str;

/** Administration/public access around the existing immutable template library. */
final readonly class CommonTemplateService
{
    public const OWNER = '8102c49a-5894-4d75-84af-e91615893958';

    public function __construct(private AdminAccess $access, private TemplateLibraryService $library, private StudioService $studio, private BlockRegistry $registry, private MediaCatalogue $media) {}

    public function listing(?string $locale, ?User $admin = null): array
    {
        if ($locale === null) {
            $this->access->require($admin);
        }
        $query = CommonTemplate::query()->with('record.currentVersion')->orderBy('id');
        if ($locale !== null) {
            $query->where('visible', true);
        }

        return $query->get()->filter(fn ($common) => $locale === null || in_array($locale, $common->record->currentVersion->locales, true))
            ->map(fn ($common) => $this->present($common, $locale))->values()->all();
    }

    public function detail(string $id, string $locale): array
    {
        $common = $this->findPublic($id);
        $version = $common->record->currentVersion;
        if (! in_array($locale, $version->locales, true)) {
            throw new ApiProblem('not_found', 404);
        }
        $block = BlockInstance::fromArray($version->block, $this->registry, $version->locales);
        $this->media->assertBlock($block);
        $projection = $block->project(Audience::Projector, $locale);
        if (isset($block->media['image'])) {
            $image = $block->media['image'];
            $projection['resources'] = ['image' => $this->media->resolve($image['assetId'], $image['versionId'])['url']];
        }

        return ['template' => $this->present($common, $locale), 'preview' => $projection];
    }

    public function save(User $admin, array $data, ?string $id = null, ?int $revision = null): array
    {
        $this->access->require($admin);
        $labels = $data['labels'];
        if (! is_array($labels) || count($labels) !== 2 || $data['locales'] !== ['ru', 'de']) {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }
        foreach (['title', 'description'] as $field) {
            TaxonomyService::labels(['ru' => $labels['ru'][$field] ?? null, 'de' => $labels['de'][$field] ?? null]);
        }
        try {
            $block = BlockInstance::fromArray($data['block'], $this->registry, $data['locales']);
        } catch (ValidationException) {
            throw new ApiProblem('invalid_document', 422);
        }
        $this->media->assertBlock($block);

        return OwnerMutation::transaction([self::OWNER], function () use ($data, $labels, $block, $id, $revision): array {
            if ($id === null) {
                // The same authoring validation and createFromLesson path used by personal templates.
                $document = ['id' => (string) Str::uuid(), 'schemaVersion' => 1, 'defaultLocale' => $data['defaultLocale'], 'locales' => $data['locales'],
                    'content' => ['ru' => ['title' => $labels['ru']['title']], 'de' => ['title' => $labels['de']['title']]],
                    'stages' => [['id' => 'common-source', 'content' => ['ru' => ['title' => $labels['ru']['title']], 'de' => ['title' => $labels['de']['title']]], 'blocks' => [$block->toArray()]]]];
                $material = $this->studio->create(self::OWNER, $document);
                $record = $this->library->createFromLesson(self::OWNER, $material->id, $material->revision, $block->id, $data['attribution']);
                $common = CommonTemplate::create(['block_template_record_id' => $record->id, 'labels' => $labels, 'visible' => true]);
            } else {
                $common = CommonTemplate::query()->lockForUpdate()->find($id) ?? throw new ApiProblem('not_found', 404);
                $this->library->update(self::OWNER, $common->block_template_record_id, $revision, $data['locales'], $data['defaultLocale'], $block->toArray(), $data['attribution']);
                $common->labels = $labels;
                $common->save();
            }

            return $this->present($common->load('record.currentVersion'));
        });
    }

    public function visibility(User $admin, string $id, int $revision, bool $visible): array
    {
        $this->access->require($admin);

        return OwnerMutation::transaction([self::OWNER], function () use ($id, $revision, $visible): array {
            $common = CommonTemplate::query()->lockForUpdate()->find($id) ?? throw new ApiProblem('not_found', 404);
            $this->library->archive(self::OWNER, $common->block_template_record_id, $revision, ! $visible);
            $common->visible = $visible;
            $common->save();

            return $this->present($common->load('record.currentVersion'));
        });
    }

    public function instantiate(string $id, string $versionId, array $locales, string $owner): array
    {
        return OwnerMutation::transaction([$owner, self::OWNER], function () use ($id, $versionId, $locales): array {
            $common = $this->findPublic($id);
            $copy = $this->library->instantiate(self::OWNER, $common->block_template_record_id, $versionId, $locales);
            $block = BlockInstance::fromArray($copy, $this->registry, $locales);
            $this->media->assertBlock($block);

            return $copy;
        });
    }

    private function findPublic(string $id): CommonTemplate
    {
        return CommonTemplate::query()->where('visible', true)->with('record.currentVersion')->find($id) ?? throw new ApiProblem('not_found', 404);
    }

    private function present(CommonTemplate $common, ?string $locale = null): array
    {
        $record = $common->record;
        $data = ['id' => $common->id, 'templateId' => $record->id, 'revision' => $record->revision, 'visible' => $common->visible,
            'versionId' => $record->current_version_id, 'locales' => $record->currentVersion->locales, 'type' => $record->currentVersion->block['type'],
            'tags' => $record->tags, 'attribution' => $record->currentVersion->attribution,
            'versions' => $record->versions()->get()->map(fn ($version) => ['id' => $version->id, 'versionNo' => $version->version_no, 'locales' => $version->locales, 'attribution' => $version->attribution])->all()];

        return $locale === null ? $data + ['labels' => $common->labels, 'block' => $record->currentVersion->block, 'defaultLocale' => $record->currentVersion->default_locale]
            : $data + $common->labels[$locale];
    }
}
