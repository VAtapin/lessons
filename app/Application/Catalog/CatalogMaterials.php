<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\TeacherDocumentation;

final readonly class CatalogMaterials
{
    public function __construct(private DocumentationFiles $files) {}

    public function cards(array $lesson, ?TeacherDocumentation $documentation, string $locale, string $format): array
    {
        $groups = [];
        foreach ($documentation === null ? [] : $this->files->localizedReferences($documentation, $locale) as $reference) {
            $file = $this->files->resolve($reference['fileId']);
            if (self::format($file) !== $format) {
                continue;
            }
            // DOCX/PDF variants of the same source are one material with two downloads.
            $key = $reference['locale'].'-'.pathinfo($file['downloadName'], PATHINFO_FILENAME);
            if (! isset($groups[$key])) {
                $label = isset($file['labelKey']) ? __('studio.'.$file['labelKey'], [], $locale) : __('interface.format_'.$format, [], $locale);
                $label = trim(preg_replace('/\s*[·—]?\s*\b(?:DOCX|PDF|PPTX)\b/u', '', $label));
                $groups[$key] = [...$lesson, 'materialId' => $lesson['slug'].'-'.sha1($key),
                    'title' => $label.' — '.$lesson['title'], 'lessonTitle' => $lesson['title'],
                    'format' => [$format], 'locales' => [$reference['locale']], 'downloads' => []];
            }
            $groups[$key]['downloads'][] = ['url' => $file['url'], 'extension' => strtoupper(pathinfo($file['downloadName'], PATHINFO_EXTENSION)), 'bytes' => $file['bytes']];
        }

        return array_values($groups);
    }

    public static function format(array $file): string
    {
        return $file['catalogFormat'] ?? ($file['kind'] === 'presentation' ? 'presentation' : 'notes');
    }

    public function activities(array $lesson, LessonDocument $document, string $locale, string $format): array
    {
        $cards = [];
        foreach ($document->stages as $index => $stage) {
            foreach ($stage->blocks as $block) {
                // Reviewed games only: counting the flock and finding the hidden sheep.
                // Role plays, quizzes and sequencing exercises are not automatically games.
                $matches = $format === 'game' && in_array($block->id, ['sheep-step-02-herd', 'sheep-step-04-scene'], true);
                if (! $matches) {
                    continue;
                }
                $content = $block->content[$locale];
                $text = $content['question'] ?? $content['text'] ?? $content['title'] ?? $stage->content[$locale]['title'];
                $cards[] = [...$lesson, 'materialId' => $lesson['slug'].'-'.$block->id,
                    'title' => $stage->content[$locale]['title'], 'lessonTitle' => $lesson['title'],
                    'description' => mb_strimwidth($text, 0, 350, '…'), 'format' => [$format], 'stageIndex' => $index];
            }
        }

        return $cards;
    }
}
