<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\BlockTemplate;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ReleasedLesson;
use App\Domain\Lessons\Types\TextBlock;
use App\Domain\Lessons\ValidationException;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LessonDocumentTest extends TestCase
{
    public function test_valid_document_is_normalized_and_round_trips(): void
    {
        $document = LessonDocument::fromArray(self::document(), BlockRegistry::core());

        self::assertSame(1, $document->schemaVersion);
        self::assertSame(['format' => 'plain'], $document->stages[0]->blocks[0]->config);
        self::assertSame(['fit' => 'contain'], $document->stages[0]->blocks[1]->config);
        self::assertSame(['allowRepeat' => false], $document->stages[0]->blocks[2]->config);
        self::assertSame($document->toArray(), LessonDocument::fromArray($document->toArray(), BlockRegistry::core())->toArray());
    }

    public function test_duplicate_registration_is_rejected(): void
    {
        $registry = BlockRegistry::core();
        $this->expectException(ValidationException::class);
        $registry->register(new TextBlock);
    }

    public function test_valid_unicode_snapshot_can_be_json_encoded_without_losing_content(): void
    {
        $data = self::document();
        $data['stages'][0]['blocks'][0]['content']['ru']['text'] = "Текст 👩🏽‍🏫 漢字 \0\n";
        $document = LessonDocument::fromArray($data, BlockRegistry::core());
        $json = json_encode($document->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        self::assertStringContainsString('Текст 👩🏽‍🏫 漢字', $json);
        self::assertSame($document->toArray(), json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    #[DataProvider('invalidUtf8Documents')]
    public function test_invalid_utf8_is_rejected_in_all_payload_strings_and_keys(array $data): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Content strings and object keys must be valid UTF-8.');
        LessonDocument::fromArray($data, BlockRegistry::core());
    }

    public static function invalidUtf8Documents(): iterable
    {
        $invalid = "\xC3\x28";
        $base = self::document();
        $data = $base;
        $data['content']['ru']['title'] = $invalid;
        yield 'document title' => [$data];
        $data = $base;
        $data['stages'][0]['content']['de']['notes'] = $invalid;
        yield 'private teacher note' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['content']['ru']['text'] = $invalid;
        yield 'block text' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['config'] = ['format' => $invalid];
        yield 'configuration value' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][1]['media']['image']['versionId'] = $invalid;
        yield 'media reference' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][2]['solution']['optionId'] = $invalid;
        yield 'private solution' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['origin'] = ['templateId' => 'template-1', 'versionId' => $invalid];
        yield 'template origin' => [$data];
        $data = $base;
        $data[$invalid] = 'unexpected';
        yield 'document object key' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['config'] = [$invalid => 'unexpected'];
        yield 'nested object key' => [$data];
    }

    #[DataProvider('invalidDocuments')]
    public function test_invalid_document_is_rejected(array $data): void
    {
        $this->expectException(ValidationException::class);
        LessonDocument::fromArray($data, BlockRegistry::core());
    }

    public static function invalidDocuments(): iterable
    {
        $base = self::document();
        $data = $base;
        $data['schemaVersion'] = 2;
        yield 'unsupported document version' => [$data];
        $data = $base;
        $data['schemaVersion'] = '1';
        yield 'string version' => [$data];
        $data = $base;
        $data['runtime'] = ['timer' => 30];
        yield 'runtime does not belong in document' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['type'] = 'plugin.unknown';
        yield 'unregistered block type' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['schemaVersion'] = 2;
        yield 'unsupported block version' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['schemaVersion'] = '1';
        yield 'string block version' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['id'] = '';
        yield 'empty identifier' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['extra'] = 'hidden';
        yield 'unknown block field' => [$data];
        $data = $base;
        $data['stages'][] = $data['stages'][0];
        yield 'duplicate stage identifiers' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][] = $data['stages'][0]['blocks'][0];
        yield 'duplicate blocks within stage' => [$data];
        $data = $base;
        $data['stages'][] = $data['stages'][0];
        $data['stages'][1]['id'] = 'stage-2';
        yield 'duplicate blocks across stages' => [$data];
        $data = $base;
        $data['locales'][] = 'ru';
        yield 'duplicate locales' => [$data];
        $data = $base;
        $data['defaultLocale'] = 'en';
        yield 'unavailable default locale' => [$data];
        $data = $base;
        unset($data['content']['de']);
        yield 'missing document translation' => [$data];
        $data = $base;
        unset($data['stages'][0]['content']['de']);
        yield 'missing stage translation' => [$data];
        $data = $base;
        unset($data['stages'][0]['blocks'][2]['content']['de']);
        yield 'missing choice translation' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][2]['content']['de']['options'][0]['optionId'] = 'other';
        yield 'different option identity across languages' => [$data];
        $data = $base;
        array_pop($data['stages'][0]['blocks'][2]['content']['de']['options']);
        yield 'missing option translation' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][2]['content']['ru']['options'][1]['optionId'] = 'first';
        yield 'duplicate option identifiers' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][2]['solution']['optionId'] = 'missing';
        yield 'solution references unknown option' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][2]['content']['de']['options'][0]['correct'] = true;
        yield 'solution cannot be hidden in content' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][2]['config'] = ['allowRepeat' => 1];
        yield 'configuration requires boolean' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][2]['config'] = ['solution' => 'first'];
        yield 'solution cannot be hidden in configuration' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['content']['ru']['text'] = ['invalid'];
        yield 'text requires string' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['config'] = ['format' => 'html'];
        yield 'raw HTML formatting unsupported' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['media'] = ['url' => 'https://example.test'];
        yield 'text does not support media' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][1]['media']['image'] = ['assetId' => 'asset-1'];
        yield 'image requires immutable media version' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][1]['content']['de']['alt'] = '';
        yield 'image requires alternative text' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][1]['config'] = ['fit' => 'stretch'];
        yield 'unsupported image fit' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['config'] = null;
        yield 'explicit null configuration is not an object' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][1]['media'] = null;
        yield 'explicit null media is not an object' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['solution'] = ['optionId' => 'first'];
        yield 'text cannot have a solution' => [$data];
        $data = $base;
        $data['stages'][0]['config'] = ['durationSeconds' => -1];
        yield 'duration must be positive' => [$data];
        $data = $base;
        $data['stages'] = ['stage' => $data['stages'][0]];
        yield 'stages require ordered list' => [$data];
        $data = $base;
        $data['stages'][0]['blocks'][0]['content']['ru']['text'] = new \stdClass;
        yield 'PHP objects are not lesson content' => [$data];
    }

    public function test_option_identity_survives_translated_text_and_reordering(): void
    {
        $data = self::document();
        $data['stages'][0]['blocks'][2]['content']['de']['options'] = array_reverse($data['stages'][0]['blocks'][2]['content']['de']['options']);
        $document = LessonDocument::fromArray($data, BlockRegistry::core());
        $view = $document->project(Audience::Student, 'de');

        self::assertSame('second', $view['stages'][0]['blocks'][2]['content']['options'][0]['optionId']);
        self::assertSame('Zweite', $view['stages'][0]['blocks'][2]['content']['options'][0]['text']);
    }

    public function test_teacher_projection_contains_solution_and_translated_notes(): void
    {
        $view = LessonDocument::fromArray(self::document(), BlockRegistry::core())->project(Audience::Teacher, 'de');

        self::assertSame('Geheime Notiz', $view['stages'][0]['content']['notes']);
        self::assertSame(['optionId' => 'first'], $view['stages'][0]['blocks'][2]['solution']);
        self::assertSame('Frage?', $view['stages'][0]['blocks'][2]['content']['question']);
    }

    #[DataProvider('publicAudiences')]
    public function test_public_projection_excludes_solution_notes_and_other_languages(Audience $audience): void
    {
        $view = LessonDocument::fromArray(self::document(), BlockRegistry::core())->project($audience, 'de');

        self::assertSame('de', $view['locale']);
        self::assertSame('Titel', $view['content']['title']);
        self::assertArrayNotHasKey('notes', $view['stages'][0]['content']);
        self::assertArrayNotHasKey('solution', $view['stages'][0]['blocks'][2]);
        self::assertArrayNotHasKey('ru', $view['stages'][0]['blocks'][2]['content']);
        self::assertStringNotContainsString('Geheime Notiz', json_encode($view, JSON_THROW_ON_ERROR));
    }

    public static function publicAudiences(): iterable
    {
        yield 'student' => [Audience::Student];
        yield 'projector' => [Audience::Projector];
    }

    public function test_missing_locale_is_rejected_without_fallback(): void
    {
        $document = LessonDocument::fromArray(self::document(), BlockRegistry::core());
        $this->expectException(ValidationException::class);
        $document->project(Audience::Student, 'en');
    }

    public function test_single_choice_can_have_no_solution(): void
    {
        $data = self::document();
        unset($data['stages'][0]['blocks'][2]['solution']);
        $document = LessonDocument::fromArray($data, BlockRegistry::core());
        self::assertNull($document->stages[0]->blocks[2]->solution);
    }

    public function test_draft_editing_does_not_mutate_a_released_snapshot(): void
    {
        $text = 'Original';
        $data = self::document();
        $data['stages'][0]['blocks'][0]['content']['ru']['text'] = &$text;
        $release = new ReleasedLesson('release-1', LessonDocument::fromArray($data, BlockRegistry::core()));
        $text = 'External reference mutation';
        $draft = $release->newDraft('document-2');
        $draft['stages'][0]['blocks'][0]['content']['ru']['text'] = 'Draft edit';
        $draftDocument = LessonDocument::fromArray($draft, BlockRegistry::core());

        self::assertSame('Original', $release->document->stages[0]->blocks[0]->content['ru']['text']);
        self::assertSame('Draft edit', $draftDocument->stages[0]->blocks[0]->content['ru']['text']);
        self::assertSame('document-2', $draftDocument->id);
        self::assertNotSame($release->document->stages[0], $draftDocument->stages[0]);
    }

    public function test_released_nested_content_is_readonly(): void
    {
        $document = LessonDocument::fromArray(self::document(), BlockRegistry::core());
        $this->expectException(Error::class);
        $document->stages[0]->blocks[0]->content['ru']['text'] = 'Mutation';
    }

    public function test_template_insertion_creates_independent_instances_and_provenance(): void
    {
        $registry = BlockRegistry::core();
        $source = BlockInstance::fromArray(self::document()['stages'][0]['blocks'][0], $registry, ['ru', 'de']);
        $template = new BlockTemplate('template-1', 'template-version-1', $source);
        $first = $template->instantiate('first-use');
        $second = $template->instantiate('second-use');
        $edited = $first->toArray();
        $edited['content']['ru']['text'] = 'Changed instance';
        $edited = BlockInstance::fromArray($edited, $registry, ['ru', 'de']);

        self::assertNotSame($first, $second);
        self::assertSame(['templateId' => 'template-1', 'versionId' => 'template-version-1'], $first->origin);
        self::assertSame('Текст', $template->block->content['ru']['text']);
        self::assertSame('Текст', $second->content['ru']['text']);
        self::assertSame('Changed instance', $edited->content['ru']['text']);
    }

    private static function document(): array
    {
        return [
            'id' => 'document-1', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'],
            'content' => ['ru' => ['title' => 'Название'], 'de' => ['title' => 'Titel']],
            'stages' => [[
                'id' => 'stage-1',
                'content' => ['ru' => ['title' => 'Этап', 'notes' => 'Секретная заметка'],
                    'de' => ['title' => 'Abschnitt', 'notes' => 'Geheime Notiz']],
                'blocks' => [
                    ['id' => 'text-1', 'type' => 'core.text', 'schemaVersion' => 1,
                        'content' => ['ru' => ['text' => 'Текст'], 'de' => ['text' => 'Text']]],
                    ['id' => 'image-1', 'type' => 'core.image', 'schemaVersion' => 1,
                        'content' => ['ru' => ['alt' => 'Описание'], 'de' => ['alt' => 'Beschreibung']],
                        'media' => ['image' => ['assetId' => 'asset-1', 'versionId' => 'media-version-1']]],
                    ['id' => 'choice-1', 'type' => 'core.single-choice', 'schemaVersion' => 1,
                        'content' => [
                            'ru' => ['question' => 'Вопрос?', 'options' => [
                                ['optionId' => 'first', 'text' => 'Первый'], ['optionId' => 'second', 'text' => 'Второй']]],
                            'de' => ['question' => 'Frage?', 'options' => [
                                ['optionId' => 'first', 'text' => 'Erste'], ['optionId' => 'second', 'text' => 'Zweite']]],
                        ], 'solution' => ['optionId' => 'first']],
                ],
            ]],
        ];
    }
}
