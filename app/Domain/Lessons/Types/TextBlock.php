<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockType;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class TextBlock implements BlockType
{
    public function id(): string
    {
        return 'core.text';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function defaults(): array
    {
        return ['format' => 'plain'];
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        Shape::object($block->config, ['format'], [], 'text.config');
        if ($block->config['format'] !== 'plain' || $block->solution !== null) {
            throw new ValidationException('Text supports plain content and no solution.');
        }

        Shape::object($block->media, [], [], 'text.media');
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['text'], [], "text.content.{$locale}");
            Shape::text($content['text'], "text.content.{$locale}.text");
        }
    }
}
