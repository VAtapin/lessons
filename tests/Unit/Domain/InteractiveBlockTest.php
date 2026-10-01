<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\BlockTemplate;
use App\Domain\Lessons\InteractiveBlockType;
use App\Domain\Lessons\ValidationException;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InteractiveBlockTest extends TestCase
{
    #[DataProvider('validAnswers')]
    public function test_registered_types_normalize_answers_grade_and_whitelist_results(string $type, array $answer, array $normalized, ?bool $grade, ?array $result, string $initial): void
    {
        $registry = BlockRegistry::core();
        $block = BlockInstance::fromArray(self::block($type), $registry, ['ru', 'de']);
        $definition = $registry->resolve($type, 1);
        self::assertInstanceOf(InteractiveBlockType::class, $definition);
        $value = $definition->validateAnswer($block, $answer);
        self::assertSame($normalized, $value);
        self::assertSame($grade, $definition->grade($block, $value));
        self::assertSame($result, $definition->publicResult($block));
        self::assertSame($initial, $definition->initialState());
        self::assertSame($block->toArray(), BlockInstance::fromArray($block->toArray(), $registry, ['ru', 'de'])->toArray());
        self::assertArrayNotHasKey('solution', $block->project(Audience::Student, 'de'));
        self::assertArrayNotHasKey('solution', $block->project(Audience::Projector, 'de'));
    }

    public static function validAnswers(): iterable
    {
        yield 'legacy choice correct' => ['core.single-choice', ['optionId' => 'A'], ['optionId' => 'A'], true, ['optionId' => 'A'], 'open'];
        yield 'legacy choice incorrect case distinct' => ['core.single-choice', ['optionId' => 'a'], ['optionId' => 'a'], false, ['optionId' => 'A'], 'open'];
        yield 'multiple unordered set' => ['core.multiple-choice', ['optionIds' => ['a', 'A']], ['optionIds' => ['A', 'a']], true, ['optionIds' => ['A', 'a']], 'prepared'];
        yield 'multiple partial wrong set' => ['core.multiple-choice', ['optionIds' => ['A']], ['optionIds' => ['A']], false, ['optionIds' => ['A', 'a']], 'prepared'];
        yield 'poll ungraded' => ['core.poll', ['optionId' => 'a'], ['optionId' => 'a'], null, null, 'prepared'];
        yield 'free literal text untouched' => ['core.free-response', ['text' => "  <b>Привет</b> 👋\n"], ['text' => "  <b>Привет</b> 👋\n"], null, null, 'prepared'];
        yield 'sequence order correct' => ['core.sequence', ['itemIds' => ['a', 'A']], ['itemIds' => ['a', 'A']], true, ['itemIds' => ['a', 'A']], 'prepared'];
        yield 'sequence order wrong' => ['core.sequence', ['itemIds' => ['A', 'a']], ['itemIds' => ['A', 'a']], false, ['itemIds' => ['a', 'A']], 'prepared'];
        yield 'matching pair and property order' => ['core.matching', ['pairs' => [['rightId' => 'x', 'leftId' => 'a'], ['rightId' => 'X', 'leftId' => 'A']]], ['pairs' => [['leftId' => 'A', 'rightId' => 'X'], ['leftId' => 'a', 'rightId' => 'x']]], true, ['pairs' => [['leftId' => 'A', 'rightId' => 'X'], ['leftId' => 'a', 'rightId' => 'x']]], 'prepared'];
        yield 'matching incorrect' => ['core.matching', ['pairs' => [['leftId' => 'A', 'rightId' => 'x'], ['leftId' => 'a', 'rightId' => 'X']]], ['pairs' => [['leftId' => 'A', 'rightId' => 'x'], ['leftId' => 'a', 'rightId' => 'X']]], false, ['pairs' => [['leftId' => 'A', 'rightId' => 'X'], ['leftId' => 'a', 'rightId' => 'x']]], 'prepared'];
        yield 'role choice' => ['core.roles', ['roleId' => 'a'], ['roleId' => 'a'], null, null, 'open'];
        yield 'role release' => ['core.roles', ['roleId' => null], ['roleId' => null], null, null, 'open'];
        yield 'signals' => ['core.signals', ['question' => true, 'ready' => false], ['ready' => false, 'question' => true], null, null, 'open'];
    }

    #[DataProvider('invalidAnswers')]
    public function test_answer_validation_rejects_untrusted_shapes_and_identities(string $type, array $answer): void
    {
        $registry = BlockRegistry::core();
        $block = BlockInstance::fromArray(self::block($type), $registry, ['ru', 'de']);
        $this->expectException(ValidationException::class);
        $registry->resolve($type, 1)->validateAnswer($block, $answer);
    }

    public static function invalidAnswers(): iterable
    {
        yield 'choice unknown id' => ['core.single-choice', ['optionId' => 'unknown']];
        yield 'choice number is not string ID' => ['core.single-choice', ['optionId' => 1]];
        yield 'choice injected grade' => ['core.single-choice', ['optionId' => 'A', 'grade' => true]];
        yield 'multiple empty' => ['core.multiple-choice', ['optionIds' => []]];
        yield 'multiple duplicate' => ['core.multiple-choice', ['optionIds' => ['A', 'A']]];
        yield 'multiple unknown' => ['core.multiple-choice', ['optionIds' => ['B']]];
        yield 'multiple associative list' => ['core.multiple-choice', ['optionIds' => ['id' => 'A']]];
        yield 'multiple too many' => ['core.multiple-choice', ['optionIds' => ['A', 'a', 'B']]];
        yield 'poll unknown' => ['core.poll', ['optionId' => 'B']];
        yield 'poll correct flag' => ['core.poll', ['optionId' => 'A', 'correct' => true]];
        yield 'free number' => ['core.free-response', ['text' => 5]];
        yield 'free empty' => ['core.free-response', ['text' => '']];
        yield 'free whitespace' => ['core.free-response', ['text' => "\t\n \u{00A0}\u{2003}"]];
        yield 'free too long' => ['core.free-response', ['text' => str_repeat('👋', 501)]];
        yield 'free invalid utf8' => ['core.free-response', ['text' => "\xC3\x28"]];
        yield 'free injected publication' => ['core.free-response', ['text' => 'Answer', 'published' => true]];
        yield 'sequence incomplete' => ['core.sequence', ['itemIds' => ['A']]];
        yield 'sequence duplicate' => ['core.sequence', ['itemIds' => ['A', 'A']]];
        yield 'sequence unknown' => ['core.sequence', ['itemIds' => ['A', 'B']]];
        yield 'matching incomplete' => ['core.matching', ['pairs' => [['leftId' => 'A', 'rightId' => 'X']]]];
        yield 'matching repeated right' => ['core.matching', ['pairs' => [['leftId' => 'A', 'rightId' => 'X'], ['leftId' => 'a', 'rightId' => 'X']]]];
        yield 'matching repeated left' => ['core.matching', ['pairs' => [['leftId' => 'A', 'rightId' => 'X'], ['leftId' => 'A', 'rightId' => 'x']]]];
        yield 'matching sides not interchangeable' => ['core.matching', ['pairs' => [['leftId' => 'X', 'rightId' => 'A'], ['leftId' => 'x', 'rightId' => 'a']]]];
        yield 'matching unknown pair field' => ['core.matching', ['pairs' => [['leftId' => 'A', 'rightId' => 'X', 'correct' => true], ['leftId' => 'a', 'rightId' => 'x']]]];
        yield 'role missing' => ['core.roles', []];
        yield 'role unknown' => ['core.roles', ['roleId' => 'B']];
        yield 'role injected participant' => ['core.roles', ['roleId' => 'A', 'participantId' => 'someone']];
        yield 'signal integer bool' => ['core.signals', ['ready' => 1, 'question' => false]];
        yield 'signal missing field' => ['core.signals', ['ready' => true]];
        yield 'signal forged ack' => ['core.signals', ['ready' => true, 'question' => true, 'acknowledged' => true]];
    }

    #[DataProvider('newTypes')]
    public function test_new_types_reject_extra_fields_missing_translations_and_private_data_in_public_config(string $type, int $version): void
    {
        $base = self::block($type, $version);
        foreach (['config', 'content', 'translation', 'media'] as $mutation) {
            $data = $base;
            if ($mutation === 'config') {
                $data['config']['solution'] = ['text' => 'Hidden'];
            } elseif ($mutation === 'content') {
                $data['content']['ru']['teacherNotes'] = 'Hidden';
            } elseif ($mutation === 'translation') {
                unset($data['content']['de']);
            } else {
                $data['media'] = ['url' => 'https://example.test/image.png'];
            }
            self::rejectsBlock($data);
        }
    }

    public static function newTypes(): iterable
    {
        yield 'text v2' => ['core.text', 2];
        foreach (['prompt', 'multiple-choice', 'poll', 'free-response', 'sequence', 'matching', 'roles', 'signals'] as $type) {
            yield $type => ['core.'.$type, 1];
        }
    }

    #[DataProvider('translatedGroups')]
    public function test_item_limits_translation_identity_and_case_sensitive_ids(string $type, string $group, string $idField, int $minimum): void
    {
        $base = self::block($type);
        $registry = BlockRegistry::core();
        // Reordered translations and distinct 'A'/'a' IDs are valid.
        $base['content']['de'][$group] = array_reverse($base['content']['de'][$group]);
        self::assertSame($type, BlockInstance::fromArray($base, $registry, ['ru', 'de'])->type);
        $different = $base;
        $different['content']['de'][$group][0][$idField] = 'Other';
        self::rejectsBlock($different);
        $duplicate = $base;
        $duplicate['content']['ru'][$group][1][$idField] = $duplicate['content']['ru'][$group][0][$idField];
        self::rejectsBlock($duplicate);
        $short = $base;
        $short['content']['ru'][$group] = array_slice($short['content']['ru'][$group], 0, $minimum - 1);
        self::rejectsBlock($short);
        $long = $base;
        $long['content']['ru'][$group] = array_map(fn (int $n) => [$idField => 'item-'.$n, 'text' => 'Text'], range(1, 13));
        self::rejectsBlock($long);
    }

    public static function translatedGroups(): iterable
    {
        yield 'multiple options' => ['core.multiple-choice', 'options', 'optionId', 2];
        yield 'poll options' => ['core.poll', 'options', 'optionId', 2];
        yield 'sequence items' => ['core.sequence', 'items', 'itemId', 2];
        yield 'matching left' => ['core.matching', 'left', 'itemId', 2];
        yield 'matching right' => ['core.matching', 'right', 'itemId', 2];
        yield 'roles' => ['core.roles', 'roles', 'roleId', 1];
    }

    public function test_multiple_selection_bounds_and_solution_obey_the_same_rules(): void
    {
        $data = self::block('core.multiple-choice');
        foreach ([['minSelections' => 0], ['minSelections' => 2, 'maxSelections' => 1], ['maxSelections' => 3], ['maxSelections' => '2'], ['allowRepeat' => 1]] as $invalid) {
            $bad = $data;
            $bad['config'] = $invalid;
            self::rejectsBlock($bad);
        }
        $data['config'] = ['minSelections' => 2, 'maxSelections' => 2];
        $registry = BlockRegistry::core();
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        $this->expectException(ValidationException::class);
        $registry->resolve($block->type, 1)->validateAnswer($block, ['optionIds' => ['A']]);
    }

    public function test_declared_upper_item_limits_are_valid_and_not_applied_to_legacy_single_choice(): void
    {
        $registry = BlockRegistry::core();
        foreach (['multiple-choice', 'poll', 'sequence', 'matching', 'roles'] as $name) {
            $type = 'core.'.$name;
            $data = self::block($type);
            $data['solution'] = null;
            $fields = match ($name) {
                'multiple-choice', 'poll' => ['options' => 'optionId'],
                'sequence' => ['items' => 'itemId'],
                'matching' => ['left' => 'itemId', 'right' => 'itemId'],
                'roles' => ['roles' => 'roleId'],
            };
            foreach ($fields as $group => $idField) {
                $items = array_map(fn (int $n) => [$idField => 'id-'.$n, 'text' => 'Item '.$n], range(1, 12));
                $data['content']['ru'][$group] = $items;
                $data['content']['de'][$group] = array_reverse($items);
            }
            if ($name === 'roles') {
                $data['config']['capacities'] = array_fill_keys(array_column($data['content']['ru']['roles'], 'roleId'), 1);
            }
            self::assertSame($type, BlockInstance::fromArray($data, $registry, ['ru', 'de'])->type);
        }
        $legacy = self::block('core.single-choice');
        $items = array_map(fn (int $n) => ['optionId' => 'id-'.$n, 'text' => 'Option'], range(1, 13));
        $legacy['content']['ru']['options'] = $items;
        $legacy['content']['de']['options'] = array_reverse($items);
        $legacy['solution'] = ['optionId' => 'id-13'];
        $block = BlockInstance::fromArray($legacy, $registry, ['ru', 'de']);
        self::assertSame(['optionId' => 'id-13'], $registry->resolve($block->type, 1)->publicResult($block));
    }

    public function test_free_answer_length_uses_unicode_and_preserves_original_text_and_boundaries(): void
    {
        $registry = BlockRegistry::core();
        $data = self::block('core.free-response');
        $data['config'] = ['maxLength' => 1];
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        self::assertSame(['text' => '👋'], $registry->resolve($block->type, 1)->validateAnswer($block, ['text' => '👋']));
        $data['config']['maxLength'] = 1000;
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        self::assertSame(['text' => str_repeat('漢', 1000)], $registry->resolve($block->type, 1)->validateAnswer($block, ['text' => str_repeat('漢', 1000)]));
        foreach ([0, 1001, '500', 1.0] as $invalid) {
            $data['config']['maxLength'] = $invalid;
            self::rejectsBlock($data);
        }
    }

    public function test_roles_require_exact_capacity_keys_and_integer_limits_without_querying_availability(): void
    {
        $data = self::block('core.roles');
        $data['config']['capacities'] = ['A' => 1, 'a' => 100];
        $registry = BlockRegistry::core();
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        self::assertSame(['roleId' => 'a'], $registry->resolve($block->type, 1)->validateAnswer($block, ['roleId' => 'a']));
        foreach ([[], ['A' => 1], ['A' => 1, 'a' => 1, 'other' => 1], ['A' => 0, 'a' => 1], ['A' => 101, 'a' => 1], ['A' => '1', 'a' => 1]] as $invalid) {
            $bad = $data;
            $bad['config']['capacities'] = $invalid;
            self::rejectsBlock($bad);
        }
        // Numeric string IDs remain legal even though PHP casts map keys to integers.
        $data['content'] = ['ru' => ['text' => 'Role', 'roles' => [['roleId' => '0', 'text' => 'Zero']]], 'de' => ['text' => 'Rolle', 'roles' => [['roleId' => '0', 'text' => 'Null']]]];
        $data['config']['capacities'] = ['0' => 1];
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        self::assertSame(['roleId' => '0'], $registry->resolve($block->type, 1)->validateAnswer($block, ['roleId' => '0']));
    }

    public function test_matching_requires_equal_group_sizes_even_when_all_translations_are_complete(): void
    {
        $data = self::block('core.matching');
        $data['solution'] = null;
        foreach (['ru', 'de'] as $locale) {
            $data['content'][$locale]['right'][] = ['itemId' => 'extra', 'text' => 'Another match'];
        }
        self::rejectsBlock($data);
    }

    public function test_new_content_limits_and_utf8_are_checked_even_without_an_answer(): void
    {
        $data = self::block('core.poll');
        $data['content']['ru']['question'] = str_repeat('漢', 5000);
        $data['content']['ru']['options'][0]['text'] = str_repeat('漢', 5000);
        self::assertSame(5000, mb_strlen(BlockInstance::fromArray($data, BlockRegistry::core(), ['ru', 'de'])->content['ru']['question']));
        foreach (['question', 'label', 'utf8', 'blank'] as $mutation) {
            $bad = $data;
            if ($mutation === 'question') {
                $bad['content']['ru']['question'] .= '漢';
            } elseif ($mutation === 'label') {
                $bad['content']['ru']['options'][0]['text'] .= '漢';
            } elseif ($mutation === 'utf8') {
                $bad['content']['de']['options'][1]['text'] = "\xC3\x28";
            } else {
                $bad['content']['de']['question'] = "\u{00A0}\u{2003}";
            }
            self::rejectsBlock($bad);
        }
    }

    #[DataProvider('gradedTypes')]
    public function test_absent_solution_is_ungraded_and_rejects_invalid_solution_shapes(string $type): void
    {
        $registry = BlockRegistry::core();
        $data = self::block($type);
        $solution = $data['solution'];
        unset($data['solution']);
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        $definition = $registry->resolve($type, 1);
        self::assertNull($definition->grade($block, $definition->validateAnswer($block, $solution)));
        self::assertNull($definition->publicResult($block));
        $data['solution'] = $solution + ['notes' => 'Never public'];
        self::rejectsBlock($data);
    }

    public static function gradedTypes(): iterable
    {
        foreach (['single-choice', 'multiple-choice', 'sequence', 'matching'] as $type) {
            yield $type => ['core.'.$type];
        }
    }

    public function test_non_grading_interactions_cannot_contain_a_solution(): void
    {
        foreach (['poll', 'free-response', 'roles', 'signals'] as $type) {
            $data = self::block('core.'.$type);
            $data['solution'] = ['correct' => true];
            self::rejectsBlock($data);
        }
    }

    public function test_static_versions_preserve_legacy_text_and_validate_declared_presentations(): void
    {
        $registry = BlockRegistry::core();
        $legacy = self::block('core.text', 1);
        $old = BlockInstance::fromArray($legacy, $registry, ['ru', 'de']);
        self::assertSame(['format' => 'plain'], $old->config);
        self::assertNotInstanceOf(InteractiveBlockType::class, $registry->resolve('core.text', 1));
        $data = self::block('core.text', 2);
        foreach (['paragraphs', 'list', 'quote'] as $presentation) {
            $data['config'] = ['presentation' => $presentation];
            $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
            self::assertSame($presentation, $block->config['presentation']);
            self::assertSame($block->toArray(), BlockInstance::fromArray($block->toArray(), $registry, ['ru', 'de'])->toArray());
        }
        $data['config'] = ['presentation' => 'html'];
        self::rejectsBlock($data);
        $data = self::block('core.text', 2);
        $data['content']['ru']['title'] = str_repeat('漢', 5000);
        $data['content']['ru']['source'] = str_repeat('漢', 5000);
        $data['content']['ru']['text'] = str_repeat('漢', 50000);
        self::assertSame(50000, mb_strlen(BlockInstance::fromArray($data, $registry, ['ru', 'de'])->content['ru']['text']));
        foreach (['title', 'source', 'text'] as $field) {
            $bad = $data;
            $bad['content']['ru'][$field] .= '漢';
            self::rejectsBlock($bad);
        }
        $data = self::block('core.prompt');
        foreach (['discussion', 'instruction', 'reflection'] as $kind) {
            foreach (['class', 'pair', 'group'] as $target) {
                $data['config'] = ['kind' => $kind, 'target' => $target];
                self::assertSame($data['config'], BlockInstance::fromArray($data, $registry, ['ru', 'de'])->config);
            }
        }
        foreach ([['kind' => 'html'], ['target' => 'everyone']] as $invalid) {
            $data['config'] = $invalid;
            self::rejectsBlock($data);
        }
    }

    public function test_teacher_notes_are_private_translated_detached_and_preserved_by_template_copies(): void
    {
        $registry = BlockRegistry::core();
        $data = self::block('core.multiple-choice');
        $note = 'Секрет';
        $data['teacherNotes'] = ['ru' => &$note, 'de' => 'Privat'];
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        $note = 'External change';
        self::assertSame('Секрет', $block->teacherNotes['ru']);
        self::assertSame('Privat', $block->project(Audience::Teacher, 'de')['teacherNotes']);
        foreach ([Audience::Student, Audience::Projector] as $audience) {
            $view = $block->project($audience, 'de');
            self::assertArrayNotHasKey('teacherNotes', $view);
            self::assertArrayNotHasKey('solution', $view);
            self::assertArrayNotHasKey('origin', $view);
            self::assertStringNotContainsString('Privat', json_encode($view, JSON_THROW_ON_ERROR));
        }
        $template = new BlockTemplate('template', 'version', $block);
        $first = $template->instantiate('one');
        $second = $template->instantiate('two');
        $edit = $first->toArray();
        $edit['teacherNotes']['de'] = 'Changed copy';
        $edited = BlockInstance::fromArray($edit, $registry, ['ru', 'de']);
        self::assertSame('Changed copy', $edited->teacherNotes['de']);
        self::assertSame('Privat', $second->teacherNotes['de']);
        self::assertSame('Privat', $block->teacherNotes['de']);
        $this->expectException(Error::class);
        $block->teacherNotes['ru'] = 'Mutation';
    }

    public function test_empty_notes_preserve_old_roundtrip_and_invalid_notes_never_become_content(): void
    {
        $registry = BlockRegistry::core();
        $data = self::block('core.text', 1);
        $old = BlockInstance::fromArray($data, $registry, ['ru', 'de'])->toArray();
        $data['teacherNotes'] = [];
        self::assertSame($old, BlockInstance::fromArray($data, $registry, ['ru', 'de'])->toArray());
        $data['teacherNotes'] = ['ru' => str_repeat('👋', 5000), 'de' => ''];
        self::assertSame('', BlockInstance::fromArray($data, $registry, ['ru', 'de'])->project(Audience::Teacher, 'de')['teacherNotes']);
        foreach ([null, 'Private', ['ru' => 'Missing locale'], ['ru' => 'One', 'de' => 'Two', 'en' => 'Extra'], ['ru' => str_repeat('👋', 5001), 'de' => ''], ['ru' => 'One', 'de' => 2]] as $invalid) {
            $data['teacherNotes'] = $invalid;
            self::rejectsBlock($data);
        }
    }

    public function test_answer_normalization_detaches_php_references(): void
    {
        $registry = BlockRegistry::core();
        $block = BlockInstance::fromArray(self::block('core.multiple-choice'), $registry, ['ru', 'de']);
        $id = 'A';
        $answer = ['optionIds' => [&$id, 'a']];
        $normalized = $registry->resolve($block->type, 1)->validateAnswer($block, $answer);
        $id = 'Other';
        self::assertSame(['optionIds' => ['A', 'a']], $normalized);
    }

    private static function rejectsBlock(array $data): void
    {
        try {
            BlockInstance::fromArray($data, BlockRegistry::core(), ['ru', 'de']);
            self::fail('Expected an invalid block to be rejected.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
    }

    private static function block(string $type, int $version = 1): array
    {
        $options = [['optionId' => 'A', 'text' => 'First'], ['optionId' => 'a', 'text' => 'Second']];
        $items = [['itemId' => 'A', 'text' => 'First'], ['itemId' => 'a', 'text' => 'Second']];
        $right = [['itemId' => 'X', 'text' => 'First match'], ['itemId' => 'x', 'text' => 'Second match']];
        $roles = [['roleId' => 'A', 'text' => 'First role'], ['roleId' => 'a', 'text' => 'Second role']];
        $content = match ($type) {
            'core.text' => $version === 1 ? ['text' => 'Plain text'] : ['title' => '', 'text' => "First paragraph\nSecond paragraph", 'source' => ''],
            'core.prompt', 'core.signals' => ['text' => 'Instruction'],
            'core.single-choice', 'core.multiple-choice', 'core.poll' => ['question' => 'Question', 'options' => $options],
            'core.free-response' => ['question' => 'Question'],
            'core.sequence' => ['question' => 'Question', 'items' => $items],
            'core.matching' => ['question' => 'Question', 'left' => $items, 'right' => $right],
            'core.roles' => ['text' => 'Choose role', 'roles' => $roles],
        };
        $solution = match ($type) {
            'core.single-choice' => ['optionId' => 'A'],
            'core.multiple-choice' => ['optionIds' => ['a', 'A']],
            'core.sequence' => ['itemIds' => ['a', 'A']],
            'core.matching' => ['pairs' => [['leftId' => 'a', 'rightId' => 'x'], ['leftId' => 'A', 'rightId' => 'X']]],
            default => null,
        };

        return [
            'id' => 'fixture', 'type' => $type, 'schemaVersion' => $version,
            'content' => ['ru' => $content, 'de' => $content],
            'config' => $type === 'core.roles' ? ['capacities' => ['A' => 1, 'a' => 2]] : [],
            'solution' => $solution,
        ];
    }
}
