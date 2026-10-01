<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\EditorTextFields;
use App\Domain\Lessons\InteractiveBlockType;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class SingleChoiceBlock implements EditorTextFields, InteractiveBlockType
{
    public function translatedTextFields(): array
    {
        return [
            ['path' => ['question'], 'required' => true, 'blankMode' => 'trim'],
            ['path' => ['options', '*', 'text'], 'required' => true, 'blankMode' => 'trim'],
        ];
    }

    public function id(): string
    {
        return 'core.single-choice';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function defaults(): array
    {
        return ['allowRepeat' => false];
    }

    public function initialState(): string
    {
        return 'open';
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['optionId']);

        return ['optionId' => InteractiveShape::member($value['optionId'], InteractiveShape::ids($block, 'options', 'optionId'))];
    }

    public function grade(BlockInstance $block, array $value): ?bool
    {
        return $block->solution === null ? null : $value === $this->publicResult($block);
    }

    public function publicResult(BlockInstance $block): ?array
    {
        return $block->solution === null ? null : ['optionId' => $block->solution['optionId']];
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        Shape::object($block->config, ['allowRepeat'], [], 'choice.config');
        if (! is_bool($block->config['allowRepeat'])) {
            throw new ValidationException('choice.config.allowRepeat must be boolean.');
        }

        Shape::object($block->media, [], [], 'choice.media');
        $identity = null;
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['question', 'options'], [], "choice.content.{$locale}");
            Shape::text($content['question'], "choice.content.{$locale}.question");
            $ids = [];
            foreach (Shape::list($content['options'], "choice.content.{$locale}.options", 2) as $option) {
                Shape::object($option, ['optionId', 'text'], [], 'choice.option');
                $ids[] = Shape::id($option['optionId'], 'choice.option.optionId');
                Shape::text($option['text'], 'choice.option.text');
            }

            if (count(array_unique($ids)) !== count($ids)) {
                throw new ValidationException('Choice option identifiers must be unique.');
            }

            sort($ids, SORT_STRING);
            if ($identity !== null && $ids !== $identity) {
                throw new ValidationException('Choice option identifiers must match across translations.');
            }

            $identity = $ids;
        }

        if ($block->solution !== null) {
            Shape::object($block->solution, ['optionId'], [], 'choice.solution');
            Shape::id($block->solution['optionId'], 'choice.solution.optionId');
            if (! in_array($block->solution['optionId'], $identity ?? [], true)) {
                throw new ValidationException('Choice solution must reference an existing option.');
            }
        }
    }
}
