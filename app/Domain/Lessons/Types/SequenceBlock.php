<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;

final class SequenceBlock extends InteractiveDefinition
{
    public function translatedTextFields(): array
    {
        return $this->textFields('question', ['items']);
    }

    public function id(): string
    {
        return 'core.sequence';
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['allowRepeat']);
        InteractiveShape::content($block, $locales, 'question', ['items' => ['itemId', 2, 12]]);
        if ($block->solution !== null) {
            $this->validateAnswer($block, $block->solution);
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['itemIds']);

        return ['itemIds' => InteractiveShape::permutation($value['itemIds'], InteractiveShape::ids($block, 'items', 'itemId'))];
    }

    public function grade(BlockInstance $block, array $value): ?bool
    {
        return $block->solution === null ? null : $value === $this->publicResult($block);
    }

    public function publicResult(BlockInstance $block): ?array
    {
        return $block->solution === null ? null : $this->validateAnswer($block, $block->solution);
    }
}
