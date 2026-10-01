<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockType;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class StructuredTextBlock implements BlockType
{
    public function id(): string
    {
        return 'core.text';
    }

    public function schemaVersion(): int
    {
        return 2;
    }

    public function defaults(): array
    {
        return ['presentation' => 'paragraphs'];
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        Shape::object($block->config, ['presentation'], [], 'text.config');
        Shape::object($block->media, [], [], 'text.media');
        if (! in_array($block->config['presentation'], ['paragraphs', 'list', 'quote'], true) || $block->solution !== null) {
            throw new ValidationException('Structured text has an invalid presentation or solution.');
        }
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['title', 'text', 'source'], [], "text.content.{$locale}");
            Shape::boundedText($content['title'], 'text.title', 5000, true);
            Shape::boundedText($content['text'], 'text.text', 50000);
            Shape::boundedText($content['source'], 'text.source', 5000, true);
        }
    }
}
