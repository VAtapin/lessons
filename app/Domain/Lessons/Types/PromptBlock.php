<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\EditorTextFields;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\ValidationException;

final class PromptBlock implements EditorTextFields
{
    public function translatedTextFields(): array
    {
        return [['path' => ['text'], 'required' => true, 'blankMode' => 'unicode']];
    }

    public function id(): string
    {
        return 'core.prompt';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function defaults(): array
    {
        return ['kind' => 'discussion', 'target' => 'class'];
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['kind', 'target']);
        if (! in_array($block->config['kind'], ['discussion', 'instruction', 'reflection'], true)
            || ! in_array($block->config['target'], ['class', 'pair', 'group'], true) || $block->solution !== null) {
            throw new ValidationException('Prompt has an invalid kind, target or solution.');
        }
        InteractiveShape::content($block, $locales, 'text');
    }
}
