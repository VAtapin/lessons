<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

final readonly class LessonDocument
{
    /** @param list<Stage> $stages */
    private function __construct(
        public string $id,
        public int $schemaVersion,
        public string $defaultLocale,
        public array $locales,
        public array $content,
        public array $stages,
        public ?TeacherDocumentation $documentation,
    ) {}

    public static function fromArray(array $data, BlockRegistry $registry): self
    {
        $data = Shape::copy($data);
        Shape::object($data, ['id', 'schemaVersion', 'defaultLocale', 'locales', 'content', 'stages'], ['documentation'], 'document');
        $id = Shape::id($data['id'], 'document.id');
        $version = Shape::version($data['schemaVersion'], 'document.schemaVersion');
        $locales = Shape::locales($data['locales']);
        if (! is_string($data['defaultLocale']) || ! in_array($data['defaultLocale'], $locales, true)) {
            throw new ValidationException('document.defaultLocale must be listed in locales.');
        }

        $content = Shape::translations($data['content'], $locales, 'document.content');
        foreach ($locales as $locale) {
            $translation = Shape::object($content[$locale], ['title'], [], "document.content.{$locale}");
            Shape::text($translation['title'], "document.content.{$locale}.title");
        }

        $stages = [];
        $stageIds = [];
        $blockIds = [];
        foreach (Shape::list($data['stages'], 'document.stages') as $dataStage) {
            if (! is_array($dataStage)) {
                throw new ValidationException('document.stages must contain objects.');
            }

            $stage = Stage::fromArray($dataStage, $registry, $locales);
            if (in_array($stage->id, $stageIds, true)) {
                throw new ValidationException('Stage identifiers must be unique in the document.');
            }

            $stageIds[] = $stage->id;
            foreach ($stage->blocks as $block) {
                if (in_array($block->id, $blockIds, true)) {
                    throw new ValidationException('Block identifiers must be unique across all stages.');
                }

                $blockIds[] = $block->id;
            }

            $stages[] = $stage;
        }

        $byId = [];
        foreach ($stages as $stage) {
            foreach ($stage->blocks as $block) {
                $byId[$block->id] = $block;
            }
        }
        foreach ($stages as $stage) {
            foreach ($stage->blocks as $block) {
                if ($block->type !== 'core.presentation') {
                    continue;
                }
                foreach ($block->config['sourceBlockIds'] as $sourceId) {
                    if (($byId[$sourceId]->type ?? null) !== 'core.free-response') {
                        throw new ValidationException('Response boards require existing free-response sources.');
                    }
                }
                $reviewId = $block->config['reviewBlockId'];
                if ($reviewId !== null && (! in_array($reviewId, array_column($stage->blocks, 'id'), true)
                    || ($byId[$reviewId]->solution ?? null) === null)) {
                    throw new ValidationException('Reveal review references must point to a solved task on this stage.');
                }
            }
        }

        $documentation = isset($data['documentation']) && is_array($data['documentation'])
            ? TeacherDocumentation::fromArray($data['documentation'], $locales) : null;
        if (array_key_exists('documentation', $data) && $documentation === null) {
            throw new ValidationException('document.documentation must be an object.');
        }

        return new self($id, $version, $data['defaultLocale'], $locales, $content, $stages, $documentation);
    }

    /** Full storage representation; never send directly to a projector/student. */
    public function toArray(): array
    {
        return ['id' => $this->id, 'schemaVersion' => $this->schemaVersion,
            'defaultLocale' => $this->defaultLocale, 'locales' => $this->locales,
            'content' => $this->content,
            'stages' => array_map(fn (Stage $stage) => $stage->toArray(), $this->stages),
            ...($this->documentation !== null ? ['documentation' => $this->documentation->toArray()] : [])];
    }

    public function project(Audience $audience, string $locale): array
    {
        if (! in_array($locale, $this->locales, true)) {
            throw new ValidationException('Requested document translation is unavailable.');
        }

        return ['id' => $this->id, 'schemaVersion' => $this->schemaVersion,
            'locale' => $locale, 'content' => $this->content[$locale],
            'stages' => array_map(fn (Stage $stage) => $stage->project($audience, $locale), $this->stages),
            ...($audience === Audience::Teacher && $this->documentation !== null
                ? ['documentation' => $this->documentation->project($locale)] : [])];
    }
}
