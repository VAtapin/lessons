<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\EditorDraft;
use App\Domain\Lessons\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PresentationMetadataTest extends TestCase
{
    private static function presentation(string $kind = 'scene'): array
    {
        $content = ['text' => 'A visible question', 'modes' => []];
        if ($kind === 'summary') {
            $content['items'] = [['itemId' => 'notice', 'label' => 'See', 'text' => 'Notice a person']];
        }
        if ($kind === 'discussion') {
            $content['modes'] = [['modeId' => 'help', 'label' => 'How to help?', 'title' => 'Concrete help', 'text' => 'What could you do?']];
        }

        return ['id' => 'presentation', 'type' => 'core.presentation', 'schemaVersion' => 1,
            'config' => ['kind' => $kind], 'content' => ['ru' => $content, 'de' => $content]];
    }

    #[DataProvider('metadata')]
    public function test_optional_translated_metadata_is_retained_as_literal_public_content_and_accepts_blank_draft_text(string $type, string $field, int $maximum): void
    {
        $registry = BlockRegistry::core();
        $data = $type === 'core.presentation' ? self::presentation() : EditorFixture::block($type);
        $data['content']['ru'][$field] = '<strong>Literal lesson content</strong>';
        $data['content']['de'][$field] = '';
        $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
        self::assertSame($data['content']['ru'][$field], $block->project(Audience::Student, 'ru')['content'][$field]);
        self::assertSame('', $block->content['de'][$field]);
        $fields = $registry->resolve($type, 1)->translatedTextFields();
        self::assertContains(['path' => [$field], 'required' => false, 'blankMode' => 'unicode'], $fields);
        $draft = EditorDraft::fromArray(EditorFixture::document($data), $registry);
        self::assertSame(['ru', 'de'], $draft->readiness()['readyLocales']);
        $data['content']['ru'][$field] = str_repeat('x', $maximum + 1);
        $this->expectException(ValidationException::class);
        BlockInstance::fromArray($data, $registry, ['ru', 'de']);
    }

    public static function metadata(): iterable
    {
        foreach (['title', 'eyebrow', 'subtitle', 'source', 'label', 'actionLabel', 'hideLabel', 'resetLabel', 'restartLabel', 'resetText', 'emptyText'] as $field) {
            yield 'presentation '.$field => ['core.presentation', $field, 500];
        }
        foreach (['quote', 'feedback'] as $field) {
            yield 'presentation '.$field => ['core.presentation', $field, 5000];
        }
        foreach (['label', 'placeholder', 'submitLabel'] as $field) {
            yield 'free response '.$field => ['core.free-response', $field, 200];
        }
        foreach (['readyLabel', 'questionLabel'] as $field) {
            yield 'signals '.$field => ['core.signals', $field, 200];
        }
        foreach (['label', 'submitLabel', 'emptyText', 'feedback', 'feedbackFirstWrong', 'feedbackWrong', 'feedbackCorrect', 'feedbackComplete', 'reviewLabel'] as $field) {
            yield 'sequence '.$field => ['core.sequence', $field, 500];
        }
    }

    public function test_optional_nested_headings_may_be_blank_in_an_editable_translation(): void
    {
        $registry = BlockRegistry::core();
        foreach (['summary', 'discussion'] as $kind) {
            $data = self::presentation($kind);
            if ($kind === 'summary') {
                $data['content']['de']['items'][0]['label'] = '';
            } else {
                $data['content']['de']['modes'][0]['title'] = '';
            }
            $block = BlockInstance::fromArray($data, $registry, ['ru', 'de']);
            self::assertSame($data['content']['de'], $block->content['de']);
            self::assertSame(['ru', 'de'], EditorDraft::fromArray(EditorFixture::document($data), $registry)->readiness()['readyLocales']);
        }
    }

    #[DataProvider('invalidPresentation')]
    public function test_composition_and_summary_identities_are_strictly_validated(array $data): void
    {
        $this->expectException(ValidationException::class);
        BlockInstance::fromArray($data, BlockRegistry::core(), ['ru', 'de']);
    }

    public static function invalidPresentation(): iterable
    {
        $data = self::presentation();
        $data['config']['scene'] = 'arbitrary';
        yield 'unknown composition' => [$data];
        $data['config']['scene'] = 'story';
        $data['config']['imageSide'] = 'center';
        yield 'unknown image side' => [$data];
        $data = self::presentation('discussion');
        $data['config']['scene'] = 'story';
        yield 'scene config on other kind' => [$data];
        $data = self::presentation('summary');
        $data['content']['de']['items'][0]['itemId'] = 'other';
        yield 'mismatched translated summary identities' => [$data];
        $data = self::presentation('summary');
        $data['content']['ru']['items'][] = $data['content']['ru']['items'][0];
        yield 'duplicate summary identity' => [$data];
        $data = self::presentation('summary');
        $data['content']['ru']['items'] = $data['content']['de']['items'] = [];
        yield 'empty summary' => [$data];
        $data = self::presentation('summary');
        $data['config']['kind'] = 'closing';
        yield 'summary items on closing' => [$data];
        $data = self::presentation();
        $data['content']['ru']['items'] = [];
        yield 'even empty items only permitted on summary' => [$data];
        $data = self::presentation('discussion');
        $data['content']['ru']['modes'][0]['title'] = str_repeat('x', 201);
        yield 'overlong discussion mode title' => [$data];
        $data = self::presentation('discussion');
        $data['content']['ru']['modes'][0]['private'] = true;
        yield 'extra mode field' => [$data];
        $data = self::presentation();
        $data['content']['ru']['html'] = '<iframe>';
        yield 'unknown presentation field' => [$data];
        $data = EditorFixture::block('core.sequence');
        $data['content']['ru']['items'][0]['icon'] = str_repeat('x', 11);
        yield 'overlong sequence icon' => [$data];
        $data = EditorFixture::block('core.sequence');
        $data['content']['ru']['items'][0]['solution'] = true;
        yield 'extra sequence item field' => [$data];
    }
}
