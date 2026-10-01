<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\BlockType;
use App\Domain\Lessons\EditorDraft;
use App\Domain\Lessons\EditorDraftException;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditorDraftTest extends TestCase
{
    #[DataProvider('types')]
    public function test_each_type_preserves_partial_text_and_releases_only_actual_ready_translation(string $type, int $version): void
    {
        $data = EditorFixture::document(EditorFixture::block($type, $version));
        $data['content']['de']['title'] = '';
        $data['stages'][0]['content']['de']['title'] = '';
        $data['stages'][0]['blocks'][0]['content']['de'] = EditorFixture::blank($data['stages'][0]['blocks'][0]['content']['de']);
        $data['stages'][0]['blocks'][0]['teacherNotes'] = ['ru' => '', 'de' => ''];
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        self::assertSame(['ru'], $draft->readiness()['readyLocales']);
        self::assertSame('draft', $draft->readiness()['locales'][1]['status']);
        self::assertSame('draft', $draft->blockReadiness('block')['locales'][1]['status']);
        self::assertSame($data['stages'][0]['blocks'][0]['content'], $draft->toArray()['stages'][0]['blocks'][0]['content']);
        self::assertSame($draft->toArray(), EditorDraft::fromArray($draft->toArray(), BlockRegistry::core())->toArray());
        $ready = $draft->readyDocument(['ru']);
        self::assertSame(['ru'], $ready->locales);
        self::assertSame(['ru' => ''], $ready->stages[0]->blocks[0]->teacherNotes);
        self::assertSame($data['stages'][0]['blocks'][0]['content']['ru'], $ready->stages[0]->blocks[0]->content['ru']);
        self::assertSame($ready->toArray(), LessonDocument::fromArray($ready->toArray(), BlockRegistry::core())->toArray());
        self::assertSame(['ru'], array_keys($draft->readyBlock('block', ['ru'])->content));
        $this->expectException(ValidationException::class);
        LessonDocument::fromArray($data, BlockRegistry::core());
    }

    public static function types(): iterable
    {
        yield from EditorFixture::types();
    }

    #[DataProvider('types')]
    public function test_partial_translation_still_rejects_private_content_and_invalid_config_for_every_type(string $type, int $version): void
    {
        $base = EditorFixture::document(EditorFixture::block($type, $version));
        $base['stages'][0]['blocks'][0]['content']['de'] = EditorFixture::blank($base['stages'][0]['blocks'][0]['content']['de']);
        foreach (['config', 'media', 'content'] as $case) {
            $data = $base;
            $block = &$data['stages'][0]['blocks'][0];
            if ($case === 'content') {
                $block['content']['de']['teacherNotes'] = 'PRIVATE INVALID VALUE';
            } else {
                $block[$case]['hidden'] = 'PRIVATE INVALID VALUE';
            }
            unset($block);
            try {
                EditorDraft::fromArray($data, BlockRegistry::core());
                self::fail('Partial text must not bypass the registered validator.');
            } catch (EditorDraftException $error) {
                self::assertSame('invalid_editor_document', $error->reason);
                self::assertSame('/stages/0/blocks/0', $error->issues[0]['path']);
                self::assertSame('stage', $error->issues[0]['stageId']);
                self::assertSame('block', $error->issues[0]['blockId']);
            }
        }
    }

    public function test_readiness_distinguishes_draft_partial_ready_and_excludes_optional_fields(): void
    {
        $data = EditorFixture::document(EditorFixture::block('core.text', 2));
        $data['content']['de']['title'] = '';
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        self::assertSame('partial', $draft->readiness()['locales'][1]['status']);
        self::assertSame([['code' => 'required_text', 'path' => '/content/de/title', 'locale' => 'de']], $draft->readiness()['locales'][1]['issues']);
        self::assertSame(['ru', 'de'], $draft->blockReadiness('block')['readyLocales']);
        foreach (['ru', 'de'] as $locale) {
            $data['content'][$locale]['title'] = '';
            $data['stages'][0]['content'][$locale]['title'] = " \n";
            $data['stages'][0]['blocks'][0]['content'][$locale]['text'] = "\u{00A0}\u{2003}";
        }
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        self::assertSame([], $draft->readiness()['readyLocales']);
        self::assertSame(['draft', 'draft'], array_column($draft->readiness()['locales'], 'status'));
        $this->expectException(EditorDraftException::class);
        $draft->readyDocument(['ru']);
    }

    public function test_block_extraction_is_independent_of_material_stage_and_other_blocks(): void
    {
        $data = EditorFixture::document();
        $data['content']['ru']['title'] = '';
        $data['stages'][0]['content']['ru']['title'] = '';
        $other = EditorFixture::block('core.prompt');
        $other['id'] = 'other';
        $other['content']['ru']['text'] = '';
        $data['stages'][0]['blocks'][] = $other;
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        self::assertSame(['de'], $draft->readiness()['readyLocales']);
        self::assertSame(['ru', 'de'], $draft->blockReadiness('block')['readyLocales']);
        self::assertSame(['ru', 'de'], array_keys($draft->readyBlock('block', ['de', 'ru'])->content));
        try {
            $draft->readyDocument(['ru', 'de']);
            self::fail('Unready default must not become a strict snapshot.');
        } catch (EditorDraftException $error) {
            self::assertSame('translation_not_ready', $error->reason);
            self::assertNotEmpty($error->issues);
        }
    }

    #[DataProvider('invalidSelections')]
    public function test_snapshot_locale_selection_is_strict_and_requires_original_default(array $locales): void
    {
        $draft = EditorDraft::fromArray(EditorFixture::document(), BlockRegistry::core());
        foreach (['document', 'block'] as $kind) {
            try {
                $kind === 'document' ? $draft->readyDocument($locales) : $draft->readyBlock('block', $locales);
                self::fail('Invalid selected locales must fail.');
            } catch (EditorDraftException $error) {
                self::assertSame('invalid_editor_document', $error->reason);
                self::assertSame('/locales', $error->issues[0]['path']);
            }
        }
    }

    public static function invalidSelections(): iterable
    {
        yield 'none' => [[]];
        yield 'without original default' => [['de']];
        yield 'unknown' => [['ru', 'en']];
        yield 'duplicate' => [['ru', 'ru']];
        yield 'not a list' => [['locale' => 'ru']];
    }

    public function test_preview_restores_exact_values_and_keeps_existing_audience_privacy(): void
    {
        $block = EditorFixture::block('core.single-choice');
        $block['content']['de'] = ['question' => '', 'options' => [['optionId' => 'a', 'text' => 'x'], ['optionId' => 'A', 'text' => "\0\t"]]];
        $block['teacherNotes'] = ['ru' => 'Teacher RU', 'de' => 'Teacher DE'];
        $block['origin'] = ['templateId' => 'template-secret', 'versionId' => 'origin-secret'];
        $data = EditorFixture::document($block);
        $data['stages'][0]['content']['de']['title'] = '';
        $extra = $data['stages'][0];
        $extra['id'] = 'hidden-stage';
        $extra['blocks'][0]['id'] = 'hidden-block';
        $extra['content']['de']['title'] = 'Unselected stage';
        $data['stages'][] = $extra;
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        self::assertSame('/stages/0/blocks/0/content/de/options/1/text', $draft->blockReadiness('block')['locales'][1]['issues'][1]['path']);
        foreach (Audience::cases() as $audience) {
            $view = $draft->projectStage($audience, 'de', 'stage');
            self::assertSame('', $view['content']['title']);
            self::assertSame($block['content']['de'], $view['blocks'][0]['content']);
            self::assertArrayNotHasKey('origin', $view['blocks'][0]);
            self::assertStringNotContainsString('Unselected stage', json_encode($view, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('Teacher RU', json_encode($view, JSON_THROW_ON_ERROR));
            if ($audience === Audience::Teacher) {
                self::assertSame('Teacher DE', $view['blocks'][0]['teacherNotes']);
                self::assertSame(['optionId' => 'A'], $view['blocks'][0]['solution']);
                self::assertSame('Private DE', $view['content']['notes']);
            } else {
                self::assertArrayNotHasKey('solution', $view['blocks'][0]);
                self::assertArrayNotHasKey('teacherNotes', $view['blocks'][0]);
                self::assertArrayNotHasKey('notes', $view['content']);
                self::assertStringNotContainsString('Private', json_encode($view, JSON_THROW_ON_ERROR));
            }
        }
    }

    public function test_unicode_blank_predicate_matches_each_legacy_or_modern_validator(): void
    {
        foreach ([1 => 'ready', 2 => 'draft'] as $version => $status) {
            $data = EditorFixture::document(EditorFixture::block('core.text', $version));
            $data['stages'][0]['blocks'][0]['content']['de']['text'] = "\u{00A0}\u{2003}";
            $draft = EditorDraft::fromArray($data, BlockRegistry::core());
            self::assertSame($status, $draft->blockReadiness('block')['locales'][1]['status']);
            self::assertSame("\u{00A0}\u{2003}", $draft->toArray()['stages'][0]['blocks'][0]['content']['de']['text']);
        }
    }

    #[DataProvider('lengthBoundaries')]
    public function test_blank_replacement_does_not_bypass_existing_unicode_length_limits(string $type, int $version, string $field, int $maximum, string $space): void
    {
        $data = EditorFixture::document(EditorFixture::block($type, $version));
        $data['stages'][0]['blocks'][0]['content']['de'][$field] = str_repeat($space, $maximum);
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        self::assertSame($data['stages'][0]['blocks'][0]['content']['de'][$field], $draft->toArray()['stages'][0]['blocks'][0]['content']['de'][$field]);
        $data['stages'][0]['blocks'][0]['content']['de'][$field] .= $space;
        $this->expectException(EditorDraftException::class);
        EditorDraft::fromArray($data, BlockRegistry::core());
    }

    public static function lengthBoundaries(): iterable
    {
        yield 'required ASCII' => ['core.prompt', 1, 'text', 5000, ' '];
        yield 'required NBSP' => ['core.prompt', 1, 'text', 5000, "\u{00A0}"];
        yield 'required EM SPACE' => ['core.prompt', 1, 'text', 5000, "\u{2003}"];
        yield 'required large text' => ['core.text', 2, 'text', 50000, "\u{2003}"];
        yield 'optional title' => ['core.text', 2, 'title', 5000, ' '];
        yield 'optional source' => ['core.text', 2, 'source', 5000, "\u{00A0}"];
    }

    public function test_legacy_length_is_not_silently_restricted_and_original_references_are_detached(): void
    {
        $text = str_repeat(' ', 50001);
        $note = 'Private';
        $data = EditorFixture::document();
        $data['stages'][0]['blocks'][0]['content']['ru']['text'] = &$text;
        $data['stages'][0]['blocks'][0]['teacherNotes'] = ['ru' => &$note, 'de' => ''];
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        $text = 'External mutation';
        $note = 'Changed note';
        $output = $draft->toArray();
        self::assertSame(str_repeat(' ', 50001), $output['stages'][0]['blocks'][0]['content']['ru']['text']);
        self::assertSame('Private', $output['stages'][0]['blocks'][0]['teacherNotes']['ru']);
        $output['stages'][0]['blocks'][0]['content']['ru']['text'] = 'Changed output';
        self::assertSame(str_repeat(' ', 50001), $draft->toArray()['stages'][0]['blocks'][0]['content']['ru']['text']);
    }

    #[DataProvider('structuralErrors')]
    public function test_incomplete_text_never_relaxes_structure_config_media_solution_or_private_notes(array $data, string $path): void
    {
        try {
            EditorDraft::fromArray($data, BlockRegistry::core());
            self::fail('Structurally unsafe draft must not be accepted.');
        } catch (EditorDraftException $error) {
            self::assertSame('invalid_editor_document', $error->reason);
            self::assertSame($path, $error->issues[0]['path']);
            foreach ($error->issues as $issue) {
                self::assertEmpty(array_diff(array_keys($issue), ['code', 'path', 'locale', 'stageId', 'blockId']));
            }
            self::assertStringNotContainsString('PRIVATE INVALID VALUE', $error->getMessage());
        }
    }

    public static function structuralErrors(): iterable
    {
        $base = EditorFixture::document(EditorFixture::block('core.single-choice'));
        $base['stages'][0]['blocks'][0]['content']['de']['question'] = '';
        $data = $base;
        $data['owner_key'] = 'PRIVATE INVALID VALUE';
        yield 'unknown root' => [$data, ''];
        $data = $base;
        $data['stages'] = [];
        yield 'no stages' => [$data, '/stages'];
        $data = $base;
        $data['stages'][0]['blocks'] = [];
        yield 'no blocks' => [$data, '/stages/0/blocks'];
        foreach (['missing locale', 'missing text', 'non string', 'extra field', 'config', 'media', 'solution', 'teacher notes', 'UTF-8', 'identity'] as $case) {
            $data = $base;
            $block = &$data['stages'][0]['blocks'][0];
            switch ($case) {
                case 'missing locale': unset($block['content']['de']);
                    break;
                case 'missing text': unset($block['content']['de']['question']);
                    break;
                case 'non string': $block['content']['de']['question'] = null;
                    break;
                case 'extra field': $block['content']['de']['published'] = true;
                    break;
                case 'config': $block['config'] = ['solution' => 'PRIVATE INVALID VALUE'];
                    break;
                case 'media': $block['media'] = ['url' => 'PRIVATE INVALID VALUE'];
                    break;
                case 'solution': $block['solution'] = ['optionId' => 'unknown'];
                    break;
                case 'teacher notes': $block['teacherNotes'] = ['ru' => 'Missing'];
                    break;
                case 'UTF-8': $block['content']['de']['question'] = "\xC3\x28";
                    break;
                case 'identity': $block['content']['de']['options'][0]['optionId'] = 'different';
                    break;
            }
            unset($block);
            yield $case => [$data, $case === 'UTF-8' ? '' : '/stages/0/blocks/0'];
        }
        $data = $base;
        $data['stages'][0]['blocks'][] = $data['stages'][0]['blocks'][0];
        yield 'duplicate block' => [$data, '/stages/0/blocks/1/id'];
        $data = $base;
        $data['stages'][] = $data['stages'][0];
        yield 'duplicate stage' => [$data, '/stages/1/id'];
        $data = EditorFixture::document(EditorFixture::block('core.image'));
        $data['stages'][0]['blocks'][0]['content']['de']['alt'] = '';
        $data['stages'][0]['blocks'][0]['media']['image']['url'] = 'PRIVATE INVALID VALUE';
        yield 'image arbitrary URL' => [$data, '/stages/0/blocks/0'];
        $data = $base;
        $data['stages'][0]['blocks'][0]['origin'] = ['templateId' => '', 'versionId' => 'version'];
        yield 'incomplete origin' => [$data, '/stages/0/blocks/0'];
        $data = $base;
        $data['stages'][0]['blocks'][0]['teacherNotes'] = ['ru' => str_repeat(' ', 5001), 'de' => ''];
        yield 'overlong optional note' => [$data, '/stages/0/blocks/0'];
        $data = EditorFixture::document(EditorFixture::block('core.roles'));
        $data['stages'][0]['blocks'][0]['content']['de'] = EditorFixture::blank($data['stages'][0]['blocks'][0]['content']['de']);
        $data['stages'][0]['blocks'][0]['config']['capacities']['A'] = 101;
        yield 'over capacity' => [$data, '/stages/0/blocks/0'];
        $data = $base;
        $data['stages'][0]['config'] = ['layout' => 'pixel-editor'];
        yield 'unknown layout' => [$data, '/stages/0'];
        $data = EditorFixture::document(EditorFixture::block('core.multiple-choice'));
        $data['stages'][0]['blocks'][0]['content']['de']['options'][0]['text'] = str_repeat("\u{2003}", 5001);
        yield 'overlong blank wildcard label' => [$data, '/stages/0/blocks/0'];
    }

    public function test_case_sensitive_block_ids_and_wildcard_paths_survive_translation_reordering(): void
    {
        $data = EditorFixture::document(EditorFixture::block('core.matching'));
        $data['stages'][0]['blocks'][0]['id'] = 'B';
        $data['stages'][0]['blocks'][0]['content']['de']['left'] = array_reverse($data['stages'][0]['blocks'][0]['content']['de']['left']);
        $data['stages'][0]['blocks'][0]['content']['de']['left'][0]['text'] = '';
        $other = EditorFixture::block();
        $other['id'] = 'b';
        $data['stages'][0]['blocks'][] = $other;
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        self::assertSame(['ru'], $draft->blockReadiness('B')['readyLocales']);
        self::assertSame(['ru', 'de'], $draft->blockReadiness('b')['readyLocales']);
        self::assertSame('/stages/0/blocks/0/content/de/left/0/text', $draft->blockReadiness('B')['locales'][1]['issues'][0]['path']);
        self::assertSame('a', $draft->toArray()['stages'][0]['blocks'][0]['content']['de']['left'][0]['itemId']);
        self::assertSame(['B', 'b'], array_column($draft->projectStage(Audience::Student, 'de', 'stage')['blocks'], 'id'));
    }

    public function test_strict_subset_extraction_does_not_mutate_working_translations_or_existing_snapshot(): void
    {
        $data = EditorFixture::document();
        $data['stages'][0]['blocks'][0]['content']['de']['text'] = '';
        $draft = EditorDraft::fromArray($data, BlockRegistry::core());
        $before = $draft->toArray();
        $snapshot = $draft->readyDocument(['ru']);
        $draft->readyBlock('block', ['ru']);
        self::assertSame($before, $draft->toArray());
        self::assertSame('', $draft->projectStage(Audience::Student, 'de', 'stage')['blocks'][0]['content']['text']);
        $edited = $draft->toArray();
        $edited['stages'][0]['blocks'][0]['content']['ru']['text'] = '';
        $edited['stages'][0]['blocks'][0]['content']['de']['text'] = 'New translation';
        $next = EditorDraft::fromArray($edited, BlockRegistry::core());
        self::assertSame('Text', $snapshot->stages[0]->blocks[0]->content['ru']['text']);
        self::assertSame(['ru'], $snapshot->locales);
        self::assertSame(['de'], $next->readiness()['readyLocales']);
        self::assertSame($before, $draft->toArray());
    }

    public function test_missing_block_stage_and_locale_do_not_fall_back(): void
    {
        $draft = EditorDraft::fromArray(EditorFixture::document(), BlockRegistry::core());
        foreach (['block_not_found', 'stage_not_found', 'invalid_editor_document'] as $reason) {
            try {
                match ($reason) {
                    'block_not_found' => $draft->blockReadiness('missing'),
                    'stage_not_found' => $draft->projectStage(Audience::Teacher, 'ru', 'missing'),
                    default => $draft->projectStage(Audience::Teacher, 'en', 'stage'),
                };
                self::fail('Missing context must be rejected.');
            } catch (EditorDraftException $error) {
                self::assertSame($reason, $error->reason);
            }
        }
    }

    public function test_custom_registered_type_without_editor_metadata_stays_strict(): void
    {
        $registry = BlockRegistry::core();
        $registry->register(new class implements BlockType
        {
            public function id(): string
            {
                return 'custom.strict';
            }

            public function schemaVersion(): int
            {
                return 1;
            }

            public function defaults(): array
            {
                return [];
            }

            public function validate(BlockInstance $block, array $locales): void
            {
                foreach ($locales as $locale) {
                    Shape::object($block->content[$locale], ['text'], [], 'custom.content');
                    Shape::text($block->content[$locale]['text'], 'custom.text');
                }
            }
        });
        $data = EditorFixture::document();
        $data['stages'][0]['blocks'][0]['type'] = 'custom.strict';
        self::assertSame(['ru', 'de'], EditorDraft::fromArray($data, $registry)->readiness()['readyLocales']);
        $data['stages'][0]['blocks'][0]['content']['de']['text'] = '';
        $this->expectException(EditorDraftException::class);
        EditorDraft::fromArray($data, $registry);
    }
}
