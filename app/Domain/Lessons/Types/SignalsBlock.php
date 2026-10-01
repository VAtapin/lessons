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
        return $this->textFields('text');
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
        InteractiveShape::content($block, $locales, 'text');
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
