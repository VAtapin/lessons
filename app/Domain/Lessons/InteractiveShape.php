<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Shared strict forms for translated identities and normalized answer values. */
final class InteractiveShape
{
    public static function config(BlockInstance $block, array $fields): void
    {
        Shape::object($block->config, $fields, [], 'interaction.config');
        if (in_array('allowRepeat', $fields, true)) {
            Shape::boolean($block->config['allowRepeat'], 'interaction.config.allowRepeat');
        }
        Shape::object($block->media, [], [], 'interaction.media');
    }

    /** @param array<string, array{0:string, 1:int, 2:int}> $groups */
    public static function content(BlockInstance $block, array $locales, string $prompt, array $groups = []): array
    {
        $identity = null;
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], [$prompt, ...array_keys($groups)], [], "interaction.content.{$locale}");
            Shape::boundedText($content[$prompt], "interaction.content.{$locale}.{$prompt}", 5000);
            $sets = [];
            foreach ($groups as $group => [$idField, $minimum, $maximum]) {
                $items = Shape::list($content[$group], "interaction.{$group}", $minimum);
                if (count($items) > $maximum) {
                    throw new ValidationException('Interaction contains too many items.');
                }
                $ids = [];
                foreach ($items as $item) {
                    Shape::object($item, [$idField, 'text'], [], "interaction.{$group}.item");
                    $ids[] = Shape::id($item[$idField], "interaction.{$idField}");
                    Shape::boundedText($item['text'], 'interaction.item.text', 5000);
                }
                if (count(array_unique($ids, SORT_STRING)) !== count($ids)) {
                    throw new ValidationException('Interaction item identifiers must be unique.');
                }
                sort($ids, SORT_STRING);
                $sets[$group] = $ids;
            }
            if ($identity !== null && $identity !== $sets) {
                throw new ValidationException('Interaction identifiers must match across translations.');
            }
            $identity = $sets;
        }

        return $identity ?? [];
    }

    public static function ids(BlockInstance $block, string $group, string $field): array
    {
        return array_column($block->content[array_key_first($block->content)][$group], $field);
    }

    public static function answer(array $value, array $fields): array
    {
        return Shape::object(Shape::copy($value), $fields, [], 'answer');
    }

    public static function member(mixed $value, array $ids): string
    {
        $id = Shape::id($value, 'answer.identifier');
        if (! in_array($id, $ids, true)) {
            throw new ValidationException('Answer references an unknown identifier.');
        }

        return $id;
    }

    public static function selection(mixed $value, array $ids, int $minimum, int $maximum): array
    {
        $selected = Shape::list($value, 'answer.identifiers', $minimum);
        if (count($selected) > $maximum) {
            throw new ValidationException('Answer exceeds the permitted selection count.');
        }
        foreach ($selected as $id) {
            self::member($id, $ids);
        }
        if (count(array_unique($selected, SORT_STRING)) !== count($selected)) {
            throw new ValidationException('Answer identifiers must be unique.');
        }

        return $selected;
    }

    public static function permutation(mixed $value, array $ids): array
    {
        return self::selection($value, $ids, count($ids), count($ids));
    }
}
