<?php

declare(strict_types=1);

namespace App\Application\Studio;

use App\Application\Catalog\DocumentationFiles;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\EditorDraft;
use App\Domain\Lessons\EditorDraftException;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\TeacherDocumentation;
use App\Models\LessonVersion;

final readonly class CurrentDraftResolver
{
    public function __construct(private BlockRegistry $registry, private MediaCatalogue $media, private DocumentationFiles $documentationFiles) {}

    public function working(LessonVersion $version): EditorDraft
    {
        return $this->parse($version->editor_draft ?? $version->document);
    }

    public function parse(array $payload, ?string $owner = null): EditorDraft
    {
        try {
            $draft = EditorDraft::fromArray($payload, $this->registry);
        } catch (EditorDraftException $problem) {
            throw $this->problem($problem);
        }
        if ($owner !== null) {
            $this->assertMedia($draft, $owner);
            $data = $draft->toArray();
            if (isset($data['documentation'])) {
                $this->documentationFiles->assertDocumentation(TeacherDocumentation::fromArray($data['documentation'], $data['locales']));
            }
        }

        return $draft;
    }

    public function assertMedia(EditorDraft $draft, string $owner): void
    {
        foreach ($draft->toArray()['stages'] as $stageIndex => $stage) {
            foreach ($stage['blocks'] as $blockIndex => $block) {
                if ($block['type'] !== 'core.image') {
                    continue;
                }
                $reference = $block['media']['image'];
                try {
                    $this->media->resolve($reference['assetId'], $reference['versionId'], $owner);
                } catch (ApiProblem) {
                    throw new EditorProblem('invalid_editor_document', 422, [['code' => 'invalid_media',
                        'path' => "/stages/{$stageIndex}/blocks/{$blockIndex}/media/image", 'stageId' => $stage['id'], 'blockId' => $block['id']]]);
                }
            }
        }
    }

    public function snapshot(LessonVersion $version, string $owner, ?array $locales = null, ?string $selectedLocale = null, bool $preserveReleased = true): LessonDocument
    {
        $draft = $this->working($version);
        $readiness = $draft->readiness();
        $subset = $locales ?? $readiness['readyLocales'];
        $selectedLocale ??= $readiness['defaultLocale'];
        if (! in_array($readiness['defaultLocale'], $readiness['readyLocales'], true)
            || ! in_array($selectedLocale, $readiness['readyLocales'], true)) {
            throw new EditorProblem('translation_not_ready', 422, $this->issues($readiness), $readiness);
        }
        try {
            $document = $draft->readyDocument($subset);
        } catch (EditorDraftException $problem) {
            throw $this->problem($problem, $readiness);
        }
        // Already released content is immutable: no second release can expand or shrink it.
        if ($preserveReleased && $version->status === 'released') {
            $snapshot = LessonDocument::fromArray($version->document, $this->registry);
            if ($locales !== null && $document->locales !== $snapshot->locales) {
                throw new EditorProblem('invalid_state', 409);
            }
            if (! in_array($selectedLocale, $snapshot->locales, true)) {
                throw new EditorProblem('translation_not_ready', 422, $this->issues($readiness), $readiness);
            }
            $document = $snapshot;
        }
        $this->media->assertDocument($document, $owner);

        return $document;
    }

    public function block(LessonVersion $version, string $owner, string $blockId): array
    {
        $draft = $this->working($version);
        try {
            $readiness = $draft->blockReadiness($blockId);
            if (! in_array($readiness['defaultLocale'], $readiness['readyLocales'], true)) {
                throw new EditorProblem('translation_not_ready', 422, $this->issues($readiness), $readiness);
            }
            $block = $draft->readyBlock($blockId, $readiness['readyLocales']);
        } catch (EditorDraftException $problem) {
            throw $this->problem($problem, $readiness ?? $draft->readiness());
        }
        $this->media->assertBlock($block, $owner);

        return ['block' => $block, 'locales' => $readiness['readyLocales'], 'defaultLocale' => $readiness['defaultLocale']];
    }

    public function preview(EditorDraft $draft, string $owner, Audience $audience, string $locale, string $stageId): array
    {
        try {
            $stage = $draft->projectStage($audience, $locale, $stageId);
        } catch (EditorDraftException $problem) {
            throw $this->problem($problem, $draft->readiness());
        }
        foreach ($stage['blocks'] as &$block) {
            if ($block['type'] === 'core.image') {
                $reference = $block['media']['image'];
                $block['resources'] = ['image' => $this->media->resolve($reference['assetId'], $reference['versionId'], $owner)['url']];
            }
        }

        return $stage;
    }

    private function problem(EditorDraftException $problem, ?array $readiness = null): EditorProblem
    {
        if (in_array($problem->reason, ['stage_not_found', 'block_not_found'], true)) {
            return new EditorProblem('not_found', 404);
        }

        return new EditorProblem($problem->reason, 422, $problem->issues, $readiness);
    }

    private function issues(array $readiness): array
    {
        return array_merge(...array_column($readiness['locales'], 'issues'));
    }
}
