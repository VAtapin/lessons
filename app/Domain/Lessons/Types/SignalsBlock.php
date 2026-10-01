<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class SignalsBlock extends InteractiveDefinition
{
    public function translatedTextFields(): array
    {
        return [...$this->textFields('text'), ...array_map(fn (string $field): array => [
            'path' => [$field], 'required' => false, 'blankMode' => 'unicode',
        ], ['readyLabel', 'questionLabel'])];
    }

    public function id(): string
    {
        return 'core.signals';
    }

    public function defaults(): array
    {
        return [];
    }

    public function initialState(): string
    {
        return 'open';
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, []);
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['text'], ['readyLabel', 'questionLabel'], 'signals.content');
            Shape::boundedText($content['text'], 'signals.text', 5000);
            foreach (['readyLabel', 'questionLabel'] as $field) {
                if (array_key_exists($field, $content)) {
                    Shape::boundedText($content[$field], 'signals.'.$field, 200, true);
                }
            }
        }
        if ($block->solution !== null) {
            throw new ValidationException('Signals cannot have a solution.');
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['ready', 'question']);

        return ['ready' => Shape::boolean($value['ready'], 'answer.ready'), 'question' => Shape::boolean($value['question'], 'answer.question')];
    }
}
