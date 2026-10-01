<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

/** A reusable teacher-controlled reveal, discussion switch or anonymous response board. */
final class PresentationBlock extends InteractiveDefinition
{
    public function id(): string
    {
        return 'core.presentation';
    }

    public function translatedTextFields(): array
    {
        return $this->textFields('text', ['modes']);
    }

    public function defaults(): array
    {
        return ['kind' => 'reveal', 'reviewBlockId' => null, 'sourceBlockIds' => [], 'maxItems' => 8];
    }

    public function initialState(): string
    {
        return 'open';
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['kind', 'reviewBlockId', 'sourceBlockIds', 'maxItems']);
        if (! in_array($block->config['kind'], ['reveal', 'discussion', 'response-board'], true)
            || $block->solution !== null || ! is_int($block->config['maxItems'])
            || $block->config['maxItems'] < 1 || $block->config['maxItems'] > 50) {
            throw new ValidationException('Invalid presentation configuration.');
        }
        if ($block->config['reviewBlockId'] !== null) {
            Shape::id($block->config['reviewBlockId'], 'presentation.reviewBlockId');
        }
        $sources = Shape::list($block->config['sourceBlockIds'], 'presentation.sourceBlockIds', 0);
        if (count($sources) > 20 || count(array_unique($sources, SORT_REGULAR)) !== count($sources)) {
            throw new ValidationException('Invalid presentation sources.');
        }
        foreach ($sources as $id) {
            Shape::id($id, 'presentation.sourceBlockId');
        }
        $identity = null;
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['text', 'modes'], [], 'presentation.content');
            Shape::boundedText($content['text'], 'presentation.text', 5000);
            $modes = Shape::list($content['modes'], 'presentation.modes', 0);
            if (count($modes) > 8) {
                throw new ValidationException('Too many discussion modes.');
            }
            $ids = [];
            foreach ($modes as $mode) {
                Shape::object($mode, ['modeId', 'text'], ['label'], 'presentation.mode');
                $ids[] = Shape::id($mode['modeId'], 'presentation.modeId');
                Shape::boundedText($mode['text'], 'presentation.mode.text', 5000);
                if (array_key_exists('label', $mode)) {
                    Shape::boundedText($mode['label'], 'presentation.mode.label', 200);
                }
            }
            if (count(array_unique($ids)) !== count($ids) || ($identity !== null && $identity !== $ids)) {
                throw new ValidationException('Discussion identities must match.');
            }
            $identity = $ids;
        }
        if (($block->config['kind'] === 'discussion') !== ($identity !== [])
            || ($block->config['kind'] !== 'response-board' && $sources !== [])
            || ($block->config['kind'] !== 'reveal' && $block->config['reviewBlockId'] !== null)) {
            throw new ValidationException('Presentation fields do not match its kind.');
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        throw new ValidationException('A presentation does not accept student answers.');
    }
}
