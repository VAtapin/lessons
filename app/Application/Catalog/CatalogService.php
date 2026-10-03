<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Application\Shared\OwnerMutation;
use App\Application\Studio\StudioService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\CatalogEntry;
use App\Models\LessonVersion;
use Illuminate\Database\Eloquent\Builder;

final readonly class CatalogService
{
    public function __construct(private BlockRegistry $registry, private MediaCatalogue $media, private StudioService $studio, private RuntimeService $runtime, private DocumentationService $documentation, private DocumentationFiles $documentationFiles, private CatalogMaterials $materials) {}

    /** Approval is an explicit trusted administrative operation, never part of personal release. */
    public function approve(LessonVersion $version, array $metadata, string $reviewer, string $slug, ?string $sourceRevision = null, ?string $sourceHash = null): CatalogEntry
    {
        if ($reviewer === '' || strlen($reviewer) > 120 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 120 || in_array($slug, ['taxonomy', 'templates'], true)) {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }
        $this->validatePublication($version, $metadata);

        return CatalogEntry::query()->create(['slug' => $slug, 'lesson_version_id' => $version->id, 'metadata' => $metadata,
            'status' => 'approved', 'approved_at' => now(), 'approved_by' => $reviewer,
            'source_revision' => $sourceRevision, 'source_hash' => $sourceHash]);
    }

    public function validatePublication(LessonVersion $version, array $metadata): LessonDocument
    {
        $document = $this->publicDocument($version);
        if (! is_array($metadata['translations'] ?? null) || array_keys($metadata['translations']) !== $document->locales
            || ! is_int($metadata['durationMinutes'] ?? null) || $metadata['durationMinutes'] < 1) {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }
        foreach ($document->locales as $locale) {
            foreach (['title', 'description'] as $field) {
                if (! is_string($metadata['translations'][$locale][$field] ?? null) || trim($metadata['translations'][$locale][$field]) === ''
                    || mb_strlen($metadata['translations'][$locale][$field]) > ($field === 'title' ? 200 : 2000)) {
                    throw new ApiProblem('invalid_catalog_entry', 422);
                }
            }
        }
        foreach (['age', 'topic', 'audience', 'format'] as $field) {
            if (! is_array($metadata[$field] ?? null) || ! array_is_list($metadata[$field])) {
                throw new ApiProblem('invalid_catalog_entry', 422);
            }
            foreach ($metadata[$field] as $value) {
                if (! is_string($value) || ! preg_match('/^[a-z0-9+-]{1,80}$/D', $value)) {
                    throw new ApiProblem('invalid_catalog_entry', 422);
                }
            }
        }
        if (isset($metadata['cover'])) {
            if (! is_array($metadata['cover']) || ! is_string($metadata['cover']['assetId'] ?? null) || ! is_string($metadata['cover']['versionId'] ?? null)) {
                throw new ApiProblem('invalid_catalog_entry', 422);
            }
            $this->media->resolve($metadata['cover']['assetId'], $metadata['cover']['versionId']);
        }

        return $document;
    }

    public function listing(string $locale, array $filters = []): array
    {
        $entries = [];
        foreach ($this->approved()->orderBy('slug')->get() as $entry) {
            // Fail closed if a stored entry loses a supported translation or approved media.
            try {
                $document = $this->publicDocument($entry->version);
                if (! in_array($locale, $document->locales, true) || ! isset($entry->metadata['translations'][$locale])) {
                    continue;
                }
                $card = $this->card($entry, $locale, $document);
            } catch (ApiProblem|ValidationException) {
                continue;
            }
            $matches = true;
            foreach (['age', 'topic', 'audience', 'format'] as $field) {
                if ($field === 'format' && in_array($filters[$field] ?? '', ['game'], true)) {
                    continue;
                }
                if (isset($filters[$field]) && ! in_array($filters[$field], $card[$field], true)) {
                    $matches = false;
                }
            }
            $duration = $card['durationMinutes'] <= 20 ? 'short' : ($card['durationMinutes'] <= 60 ? 'standard' : 'long');
            if (isset($filters['duration']) && $filters['duration'] !== $duration) {
                $matches = false;
            }
            if ($matches) {
                if (in_array($filters['format'] ?? '', ['notes', 'presentation', 'worksheet'], true)) {
                    $candidates = $this->materials->cards($card, $this->documentation->forVersion($entry->version, $document), $locale, $filters['format']);
                } elseif (in_array($filters['format'] ?? '', ['game'], true)) {
                    $candidates = $this->materials->activities($card, $document, $locale, $filters['format']);
                } else {
                    $candidates = [$card];
                }
                foreach ($candidates as $candidate) {
                    if (! isset($filters['q']) || str_contains(mb_strtolower($candidate['title'].' '.$candidate['description'].' '.($candidate['lessonTitle'] ?? '')), mb_strtolower($filters['q']))) {
                        $entries[] = $candidate;
                    }
                }
            }
        }
        $page = (int) ($filters['page'] ?? 1);

        return ['entries' => array_slice($entries, ($page - 1) * 12, 12),
            'pagination' => ['page' => $page, 'perPage' => 12, 'total' => count($entries), 'lastPage' => max(1, (int) ceil(count($entries) / 12))]];
    }

    public function detail(string $slug, string $locale): array
    {
        [$entry, $document] = $this->find($slug, $locale);
        $card = $this->card($entry, $locale, $document);
        $card['stages'] = array_map(fn ($stage) => ['title' => $stage->content[$locale]['title'], 'durationSeconds' => $stage->config['durationSeconds'] ?? null], $document->stages);
        $card['details'] = $entry->metadata['details'][$locale] ?? null;
        $documentation = $this->documentation->forVersion($entry->version, $document);
        $card['documentation'] = $documentation === null ? null : $this->documentationFiles->present($documentation, $locale);

        $preview = $document->project(Audience::Projector, $locale);
        foreach ($preview['stages'] as &$stage) {
            foreach ($stage['blocks'] as &$block) {
                if (isset($block['media']['image'])) {
                    $reference = $block['media']['image'];
                    $block['resources'] = ['image' => $this->media->resolve($reference['assetId'], $reference['versionId'])['url']];
                }
            }
            unset($block);
        }
        unset($stage);

        return ['entry' => $card, 'preview' => $preview];
    }

    public function use(string $slug, string $locale, string $owner, bool $start = false): array
    {
        return OwnerMutation::transaction([$owner], function () use ($slug, $locale, $owner, $start): array {
            [$entry, $document] = $this->find($slug, $locale, lock: true);
            $copy = $document->toArray();
            $documentation = $this->documentation->forVersion($entry->version, $document);
            if ($documentation !== null) {
                $copy['documentation'] = $documentation->toArray();
            }
            $copy['defaultLocale'] = $locale;
            $material = $this->studio->create($owner, $copy);
            $result = [];
            if ($start) {
                $result['session'] = $this->runtime->start($owner, $material->id, $material->revision, $locale, prepare: true);
                $material->refresh()->load('currentVersion');
            }
            $result['lesson'] = $this->studio->present($material);

            return $result;
        });
    }

    private function approved(): Builder
    {
        return CatalogEntry::query()->where('status', 'approved')->whereNotNull('approved_at')->whereNotNull('approved_by')->with('version');
    }

    private function find(string $slug, string $locale, bool $lock = false): array
    {
        $query = $this->approved()->where('slug', $slug);
        if ($lock) {
            $query->lockForUpdate();
        }
        $entry = $query->first() ?? throw new ApiProblem('not_found', 404);
        try {
            $document = $this->publicDocument($entry->version);
            if (isset($entry->metadata['cover'])) {
                $this->media->resolve($entry->metadata['cover']['assetId'], $entry->metadata['cover']['versionId']);
            }
        } catch (ApiProblem|ValidationException) {
            throw new ApiProblem('not_found', 404);
        }
        if (! in_array($locale, $document->locales, true) || ! isset($entry->metadata['translations'][$locale])) {
            throw new ApiProblem('not_found', 404);
        }

        return [$entry, $document];
    }

    private function publicDocument(?LessonVersion $version): LessonDocument
    {
        if ($version === null || $version->status !== 'released' || $version->purpose !== 'authoring') {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }
        $document = LessonDocument::fromArray($version->document, $this->registry);
        // With no owner, only explicitly reusable builtins resolve. A private release cannot expose private files.
        $this->media->assertDocument($document);
        $this->documentationFiles->assertDocumentation($document->documentation);

        return $document;
    }

    private function card(CatalogEntry $entry, string $locale, LessonDocument $document): array
    {
        $meta = $entry->metadata;
        $cover = isset($meta['cover']) ? $this->media->resolve($meta['cover']['assetId'], $meta['cover']['versionId'])['url'] : null;
        $size = isset($meta['cover']) ? getimagesize($this->media->resolve($meta['cover']['assetId'], $meta['cover']['versionId'])['path']) : null;
        $formats = array_values(array_diff($meta['format'], ['questions']));
        $documentation = $this->documentation->forVersion($entry->version, $document);
        if ($documentation !== null) {
            $materials = $documentation->project($locale);
            if (trim($materials['plan'] ?? '') !== '') {
                $formats[] = 'notes';
            }
            foreach ($this->documentationFiles->localizedReferences($documentation, $locale) as $reference) {
                $file = $this->documentationFiles->resolve($reference['fileId']);
                $formats[] = CatalogMaterials::format($file);
            }
        }

        return ['slug' => $entry->slug, 'versionId' => $entry->lesson_version_id,
            'title' => $meta['translations'][$locale]['title'], 'description' => $meta['translations'][$locale]['description'],
            'locales' => $document->locales, 'age' => $meta['age'], 'topic' => $meta['topic'], 'audience' => $meta['audience'],
            'format' => array_values(array_unique($formats)), 'durationMinutes' => $meta['durationMinutes'], 'coverUrl' => $cover,
            'coverWidth' => $size[0] ?? null, 'coverHeight' => $size[1] ?? null];
    }
}
