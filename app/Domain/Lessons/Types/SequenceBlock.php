<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class SequenceBlock extends InteractiveDefinition
{
    public function translatedTextFields(): array
    {
        return [...$this->textFields('question', ['items']), ...array_map(fn (string $field): array => [
            'path' => [$field], 'required' => false, 'blankMode' => 'unicode',
        ], ['label', 'submitLabel', 'emptyText', 'feedback', 'feedbackFirstWrong', 'feedbackWrong', 'feedbackCorrect', 'feedbackComplete', 'reviewLabel'])];
    }

    public function id(): string
    {
        return 'core.sequence';
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['allowRepeat']);
        $identity = null;
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['question', 'items'], ['label', 'submitLabel', 'emptyText', 'feedback', 'feedbackFirstWrong', 'feedbackWrong', 'feedbackCorrect', 'feedbackComplete', 'reviewLabel'], 'sequence.content');
            Shape::boundedText($content['question'], 'sequence.question', 5000);
            foreach (['label', 'submitLabel', 'emptyText', 'feedback', 'feedbackFirstWrong', 'feedbackWrong', 'feedbackCorrect', 'feedbackComplete', 'reviewLabel'] as $field) {
                if (array_key_exists($field, $content)) {
                    Shape::boundedText($content[$field], 'sequence.'.$field, 500, true);
                }
            }
            $items = Shape::list($content['items'], 'sequence.items', 2);
            $ids = [];
            foreach ($items as $item) {
                Shape::object($item, ['itemId', 'text'], ['icon'], 'sequence.item');
                $ids[] = Shape::id($item['itemId'], 'sequence.itemId');
                Shape::boundedText($item['text'], 'sequence.item.text', 5000);
                if (array_key_exists('icon', $item)) {
                    Shape::boundedText($item['icon'], 'sequence.item.icon', 10);
                }
            }
            sort($ids, SORT_STRING);
            if (count($ids) > 12 || count(array_unique($ids, SORT_STRING)) !== count($ids) || ($identity !== null && $identity !== $ids)) {
                throw new ValidationException('Sequence identities must be unique and match translations.');
            }
            $identity = $ids;
        }
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
