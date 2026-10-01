<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\EditorText;
use App\Domain\Lessons\EditorTextFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditorTextFieldsTest extends TestCase
{
    #[DataProvider('types')]
    public function test_registered_metadata_describes_text_and_excludes_structural_identities(string $type, int $version): void
    {
        $registry = BlockRegistry::core();
        $definition = $registry->resolve($type, $version);
        self::assertInstanceOf(EditorTextFields::class, $definition);
        $required = match ($type) {
            'core.text', 'core.prompt', 'core.signals' => [['text']],
            'core.image' => [['alt']],
            'core.single-choice', 'core.multiple-choice', 'core.poll' => [['question'], ['options', '*', 'text']],
            'core.free-response' => [['question']],
            'core.sequence' => [['question'], ['items', '*', 'text']],
            'core.matching' => [['question'], ['left', '*', 'text'], ['right', '*', 'text']],
            'core.roles' => [['text'], ['roles', '*', 'text']],
        };
        $mode = in_array($type, ['core.image', 'core.single-choice'], true) || ($type === 'core.text' && $version === 1) ? 'trim' : 'unicode';
        $fields = $definition->translatedTextFields();
        self::assertSame($required, array_column(array_filter($fields, fn (array $field) => $field['required']), 'path'));
        foreach ($fields as $field) {
            self::assertSame(['path', 'required', 'blankMode'], array_keys($field));
            self::assertSame($mode, $field['blankMode']);
            self::assertIsBool($field['required']);
        }
        // Optional metadata does not change strict defaults or validation.
        $block = BlockInstance::fromArray(EditorFixture::block($type, $version), $registry, ['ru', 'de']);
        self::assertSame($block->toArray(), BlockInstance::fromArray($block->toArray(), $registry, ['ru', 'de'])->toArray());
    }

    public static function types(): iterable
    {
        yield from EditorFixture::types();
    }

    public function test_validation_copy_preserves_blank_byte_and_codepoint_bounds_and_restores_only_known_paths(): void
    {
        foreach (['', ' ', "\t\n", "\u{00A0}", "\u{2003}", " \u{00A0}\u{2003}\n"] as $blank) {
            $actual = ['content' => ['question' => $blank, 'untouched' => 'x α 字 🙂']];
            $copy = $actual;
            $leaves = EditorText::prepare($copy, ['content'], [['path' => ['question'], 'required' => true, 'blankMode' => 'unicode']], ['locale' => 'de']);
            self::assertSame(max(1, strlen($blank)), strlen($copy['content']['question']));
            self::assertSame(max(1, mb_strlen($blank, 'UTF-8')), mb_strlen($copy['content']['question'], 'UTF-8'));
            self::assertNotSame(1, preg_match('/\A[\p{Z}\s]*\z/u', $copy['content']['question']));
            self::assertSame('x α 字 🙂', $copy['content']['untouched']);
            self::assertSame($actual, EditorText::restore($copy, $leaves));
        }
    }
}
