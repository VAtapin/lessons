<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class FreeResponseBlock extends InteractiveDefinition
{
    public function translatedTextFields(): array
    {
        return $this->textFields('question');
    }

    public function id(): string
    {
        return 'core.free-response';
    }

    public function defaults(): array
    {
        return ['allowRepeat' => false, 'maxLength' => 500];
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['allowRepeat', 'maxLength']);
        Shape::integer($block->config['maxLength'], 'free.maxLength', 1, 1000);
        InteractiveShape::content($block, $locales, 'question');
        if ($block->solution !== null) {
            throw new ValidationException('Free response cannot have an automatic solution.');
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['text']);

        return ['text' => Shape::boundedText($value['text'], 'answer.text', $block->config['maxLength'])];
    }
}
