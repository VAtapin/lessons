<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

final class EditorFixture
{
    public static function types(): iterable
    {
        foreach (['text', 'image', 'single-choice', 'prompt', 'multiple-choice', 'poll', 'free-response', 'sequence', 'matching', 'roles', 'signals'] as $name) {
            yield $name => ['core.'.$name, 1];
        }
        yield 'text v2' => ['core.text', 2];
    }

    public static function block(string $type = 'core.text', int $version = 1): array
    {
        $options = [['optionId' => 'A', 'text' => 'First'], ['optionId' => 'a', 'text' => 'Second']];
        $items = [['itemId' => 'A', 'text' => 'First'], ['itemId' => 'a', 'text' => 'Second']];
        $right = [['itemId' => 'X', 'text' => 'Right'], ['itemId' => 'x', 'text' => 'Other']];
        $roles = [['roleId' => 'A', 'text' => 'Leader'], ['roleId' => 'a', 'text' => 'Writer']];
        $content = match ($type) {
            'core.text' => $version === 1 ? ['text' => 'Text'] : ['title' => '', 'text' => 'Text', 'source' => ''],
            'core.image' => ['alt' => 'Image description', 'caption' => ''],
            'core.prompt', 'core.signals' => ['text' => 'Instruction'],
            'core.single-choice', 'core.multiple-choice', 'core.poll' => ['question' => 'Question', 'options' => $options],
            'core.free-response' => ['question' => 'Question'],
            'core.sequence' => ['question' => 'Question', 'items' => $items],
            'core.matching' => ['question' => 'Question', 'left' => $items, 'right' => $right],
            'core.roles' => ['text' => 'Choose', 'roles' => $roles],
        };
        $solution = match ($type) {
            'core.single-choice' => ['optionId' => 'A'],
            'core.multiple-choice' => ['optionIds' => ['a', 'A']],
            'core.sequence' => ['itemIds' => ['a', 'A']],
            'core.matching' => ['pairs' => [['leftId' => 'A', 'rightId' => 'X'], ['leftId' => 'a', 'rightId' => 'x']]],
            default => null,
        };

        return ['id' => 'block', 'type' => $type, 'schemaVersion' => $version, 'content' => ['ru' => $content, 'de' => $content],
            'config' => $type === 'core.roles' ? ['capacities' => ['A' => 1, 'a' => 2]] : [],
            'media' => $type === 'core.image' ? ['image' => ['assetId' => 'asset', 'versionId' => 'version']] : [],
            'solution' => $solution];
    }

    public static function document(?array $block = null): array
    {
        return ['id' => 'document', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'],
            'content' => ['ru' => ['title' => 'Название'], 'de' => ['title' => 'Titel']],
            'stages' => [['id' => 'stage', 'content' => ['ru' => ['title' => 'Этап', 'notes' => 'Private RU'], 'de' => ['title' => 'Abschnitt', 'notes' => 'Private DE']],
                'blocks' => [$block ?? self::block()]]]];
    }

    /** Blank every textual value in one content translation without altering structural IDs. */
    public static function blank(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $index => $item) {
                    $content[$key][$index]['text'] = '';
                }
            } else {
                $content[$key] = '';
            }
        }

        return $content;
    }
}
