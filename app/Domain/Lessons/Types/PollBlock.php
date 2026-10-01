<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\ValidationException;

final class PollBlock extends InteractiveDefinition
{
    public function translatedTextFields(): array
    {
        return $this->textFields('question', ['options']);
    }

    public function id(): string
    {
        return 'core.poll';
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['allowRepeat']);
        InteractiveShape::content($block, $locales, 'question', ['options' => ['optionId', 2, 12]]);
        if ($block->solution !== null) {
            throw new ValidationException('Poll cannot have a solution.');
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['optionId']);

        return ['optionId' => InteractiveShape::member($value['optionId'], InteractiveShape::ids($block, 'options', 'optionId'))];
    }
}
