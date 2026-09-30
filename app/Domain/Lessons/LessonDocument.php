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
    ) {}

    public static function fromArray(array $data, BlockRegistry $registry): self
    {
        $data = Shape::copy($data);
        Shape::object($data, ['id', 'schemaVersion', 'defaultLocale', 'locales', 'content', 'stages'], [], 'document');
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

        return new self($id, $version, $data['defaultLocale'], $locales, $content, $stages);
    }

    /** Full storage representation; never send directly to a projector/student. */
    public function toArray(): array
    {
        return ['id' => $this->id, 'schemaVersion' => $this->schemaVersion,
            'defaultLocale' => $this->defaultLocale, 'locales' => $this->locales,
            'content' => $this->content,
            'stages' => array_map(fn (Stage $stage) => $stage->toArray(), $this->stages)];
    }

    public function project(Audience $audience, string $locale): array
    {
        if (! in_array($locale, $this->locales, true)) {
            throw new ValidationException('Requested document translation is unavailable.');
        }

        return ['id' => $this->id, 'schemaVersion' => $this->schemaVersion,
            'locale' => $locale, 'content' => $this->content[$locale],
            'stages' => array_map(fn (Stage $stage) => $stage->project($audience, $locale), $this->stages)];
    }
}
