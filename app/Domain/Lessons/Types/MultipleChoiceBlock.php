<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;

final class MultipleChoiceBlock extends InteractiveDefinition
{
    public function translatedTextFields(): array
    {
        return $this->textFields('question', ['options']);
    }

    public function id(): string
    {
        return 'core.multiple-choice';
    }

    public function defaults(): array
    {
        return ['allowRepeat' => false, 'minSelections' => 1, 'maxSelections' => 2];
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['allowRepeat', 'minSelections', 'maxSelections']);
        $ids = InteractiveShape::content($block, $locales, 'question', ['options' => ['optionId', 2, 12]])['options'];
        Shape::integer($block->config['minSelections'], 'multiple.minSelections', 1, count($ids));
        Shape::integer($block->config['maxSelections'], 'multiple.maxSelections', $block->config['minSelections'], count($ids));
        if ($block->solution !== null) {
            $this->validateAnswer($block, $block->solution);
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['optionIds']);
        $ids = InteractiveShape::selection($value['optionIds'], InteractiveShape::ids($block, 'options', 'optionId'), $block->config['minSelections'], $block->config['maxSelections']);
        sort($ids, SORT_STRING);

        return ['optionIds' => $ids];
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
