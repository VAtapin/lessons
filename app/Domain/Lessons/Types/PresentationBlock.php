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
        return [...$this->textFields('text', ['modes', 'items']), ...array_map(fn (string $field): array => [
            'path' => [$field], 'required' => false, 'blankMode' => 'unicode',
        ], ['title', 'eyebrow', 'subtitle', 'quote', 'source', 'label', 'actionLabel', 'hideLabel', 'resetLabel', 'restartLabel', 'resetText', 'emptyText', 'feedback']),
            ['path' => ['items', '*', 'label'], 'required' => false, 'blankMode' => 'unicode'],
            ['path' => ['modes', '*', 'label'], 'required' => false, 'blankMode' => 'unicode'],
            ['path' => ['modes', '*', 'title'], 'required' => false, 'blankMode' => 'unicode'],
            ['path' => ['table', 'headers', '*'], 'required' => true, 'blankMode' => 'unicode'],
            ['path' => ['table', 'rows', '*', '*'], 'required' => true, 'blankMode' => 'unicode']];
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
        Shape::object($block->config, ['kind', 'reviewBlockId', 'sourceBlockIds', 'maxItems'], ['scene', 'imageSide'], 'presentation.config');
        if ($block->config['kind'] === 'picture-count' || ($block->config['kind'] === 'reveal' && $block->media !== [])) {
            Shape::object($block->media, ['image'], [], 'presentation.media');
            $image = Shape::object($block->media['image'], ['assetId', 'versionId'], [], 'presentation.media.image');
            Shape::id($image['assetId'], 'presentation.media.image.assetId');
            Shape::id($image['versionId'], 'presentation.media.image.versionId');
        } else {
            Shape::object($block->media, [], [], 'presentation.media');
        }
        if ((array_key_exists('scene', $block->config) && (! in_array($block->config['scene'], ['cover', 'story', 'question', 'scenario', 'decision', 'discussion', 'journey', 'choice'], true) || $block->config['kind'] !== 'scene'))
            || (array_key_exists('imageSide', $block->config) && (! in_array($block->config['imageSide'], ['left', 'right'], true) || $block->config['kind'] !== 'scene'))) {
            throw new ValidationException('Unsupported scene composition.');
        }
        if (! in_array($block->config['kind'], ['reveal', 'discussion', 'personal-choice', 'response-board', 'scene', 'summary', 'closing', 'picture-count'], true)
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
        $countIdentity = null;
        $itemIdentity = null;
        $tableIdentity = null;
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['text', 'modes'], ['title', 'eyebrow', 'subtitle', 'quote', 'source', 'label', 'actionLabel', 'hideLabel', 'resetLabel', 'restartLabel', 'resetText', 'emptyText', 'feedback', 'items', 'table'], 'presentation.content');
            Shape::boundedText($content['text'], 'presentation.text', 5000);
            foreach (['title', 'eyebrow', 'subtitle', 'quote', 'source', 'label', 'actionLabel', 'hideLabel', 'resetLabel', 'restartLabel', 'resetText', 'emptyText', 'feedback'] as $field) {
                if (array_key_exists($field, $content)) {
                    Shape::boundedText($content[$field], 'presentation.'.$field, $field === 'quote' || $field === 'feedback' ? 5000 : 500, true);
                }
            }
            $dimensions = null;
            if (array_key_exists('table', $content)) {
                if ($block->config['kind'] !== 'reveal') {
                    throw new ValidationException('Tables require a teacher-controlled reveal.');
                }
                $table = Shape::object($content['table'], ['headers', 'rows'], [], 'presentation.table');
                $headers = Shape::list($table['headers'], 'presentation.table.headers', 2);
                $rows = Shape::list($table['rows'], 'presentation.table.rows', 1);
                if (count($headers) > 6 || count($rows) > 12) {
                    throw new ValidationException('Presentation table is too large.');
                }
                foreach ($headers as $header) {
                    Shape::boundedText($header, 'presentation.table.header', 200);
                }
                foreach ($rows as $row) {
                    if (count(Shape::list($row, 'presentation.table.row')) !== count($headers)) {
                        throw new ValidationException('Table rows must match the headers.');
                    }
                    foreach ($row as $cell) {
                        Shape::boundedText($cell, 'presentation.table.cell', 500);
                    }
                }
                $dimensions = [count($headers), count($rows)];
            }
            if ($tableIdentity !== null && $tableIdentity !== [$dimensions]) {
                throw new ValidationException('Table dimensions must match across translations.');
            }
            $tableIdentity = [$dimensions];
            $itemIds = [];
            foreach (Shape::list($content['items'] ?? [], 'presentation.items', 0) as $item) {
                Shape::object($item, ['itemId', 'text'], ['label'], 'presentation.item');
                $itemIds[] = Shape::id($item['itemId'], 'presentation.itemId');
                Shape::boundedText($item['text'], 'presentation.item.text', 1000);
                if (array_key_exists('label', $item)) {
                    Shape::boundedText($item['label'], 'presentation.item.label', 200, true);
                }
            }
            if (count($itemIds) > 20 || count(array_unique($itemIds)) !== count($itemIds)
                || ($itemIdentity !== null && $itemIdentity !== $itemIds)
                || (array_key_exists('items', $content) && $block->config['kind'] !== 'summary')
                || ($block->config['kind'] === 'summary') !== ($itemIds !== [])) {
                throw new ValidationException('Summary identities must match its kind and translations.');
            }
            $itemIdentity = $itemIds;
            $modes = Shape::list($content['modes'], 'presentation.modes', 0);
            if (count($modes) > 8) {
                throw new ValidationException('Too many discussion modes.');
            }
            $ids = [];
            $counts = [];
            foreach ($modes as $mode) {
                Shape::object($mode, $block->config['kind'] === 'picture-count' ? ['modeId', 'text', 'count'] : ['modeId', 'text'], ['label', 'title'], 'presentation.mode');
                if ($block->config['kind'] === 'picture-count') {
                    if (! is_int($mode['count']) || $mode['count'] < 0 || $mode['count'] > min(20, $block->config['maxItems'])) {
                        throw new ValidationException('Invalid picture count.');
                    }
                    $counts[] = $mode['count'];
                }
                $ids[] = Shape::id($mode['modeId'], 'presentation.modeId');
                Shape::boundedText($mode['text'], 'presentation.mode.text', 5000);
                if (array_key_exists('label', $mode)) {
                    Shape::boundedText($mode['label'], 'presentation.mode.label', 200);
                }
                if (array_key_exists('title', $mode)) {
                    Shape::boundedText($mode['title'], 'presentation.mode.title', 200, true);
                }
            }
            if (count(array_unique($ids)) !== count($ids) || ($identity !== null && $identity !== $ids)) {
                throw new ValidationException('Discussion identities must match.');
            }
            if ($countIdentity !== null && $countIdentity !== $counts) {
                throw new ValidationException('Picture counts must match across translations.');
            }
            $countIdentity = $counts;
            $identity = $ids;
        }
        if (in_array($block->config['kind'], ['discussion', 'personal-choice', 'picture-count'], true) !== ($identity !== [])
            || ($block->config['kind'] !== 'response-board' && $sources !== [])
            || ($block->config['kind'] !== 'reveal' && $block->config['reviewBlockId'] !== null)) {
            throw new ValidationException('Presentation fields do not match its kind.');
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        if (! in_array($block->config['kind'], ['discussion', 'personal-choice'], true)) {
            throw new ValidationException('This presentation does not accept student answers.');
        }
        $value = InteractiveShape::answer($value, ['modeId']);

        return ['modeId' => InteractiveShape::member($value['modeId'], InteractiveShape::ids($block, 'modes', 'modeId'))];
    }
}
