<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class MatchingBlock extends InteractiveDefinition
{
    public function id(): string
    {
        return 'core.matching';
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['allowRepeat']);
        $ids = InteractiveShape::content($block, $locales, 'question', ['left' => ['itemId', 2, 12], 'right' => ['itemId', 2, 12]]);
        if (count($ids['left']) !== count($ids['right'])) {
            throw new ValidationException('Matching groups must have equal lengths.');
        }
        if ($block->solution !== null) {
            $this->validateAnswer($block, $block->solution);
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['pairs']);
        $left = InteractiveShape::ids($block, 'left', 'itemId');
        $right = InteractiveShape::ids($block, 'right', 'itemId');
        $pairs = Shape::list($value['pairs'], 'answer.pairs', count($left));
        if (count($pairs) !== count($left)) {
            throw new ValidationException('Matching answer must contain every pair.');
        }
        $normalized = [];
        foreach ($pairs as $pair) {
            Shape::object($pair, ['leftId', 'rightId'], [], 'answer.pair');
            $normalized[] = [
                'leftId' => InteractiveShape::member($pair['leftId'], $left),
                'rightId' => InteractiveShape::member($pair['rightId'], $right),
            ];
        }
        if (count(array_unique(array_column($normalized, 'leftId'), SORT_STRING)) !== count($left)
            || count(array_unique(array_column($normalized, 'rightId'), SORT_STRING)) !== count($right)) {
            throw new ValidationException('Matching answer must be one-to-one.');
        }
        usort($normalized, fn (array $first, array $second): int => strcmp($first['leftId'], $second['leftId']));

        return ['pairs' => $normalized];
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
