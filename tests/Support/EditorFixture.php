<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;

trait EditorFixture
{
    private string $editorOwner;

    private function editorIdentity(): void
    {
        $this->editorOwner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $this->editorOwner]);
    }

    private function editorDocument(): array
    {
        $content = ['ru' => ['title' => 'Actual RU'], 'de' => ['title' => 'Actual DE']];

        return ['id' => 'editor-fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'], 'content' => $content,
            'stages' => [['id' => 'stage-a', 'content' => ['ru' => ['title' => 'First', 'notes' => 'Private stage note'], 'de' => ['title' => 'Erste', 'notes' => 'Private DE note']],
                'blocks' => [['id' => 'text-a', 'type' => 'core.text', 'schemaVersion' => 2,
                    'content' => ['ru' => ['text' => "\n Actual text \n", 'title' => '', 'source' => ''], 'de' => ['text' => 'Deutsch', 'title' => '', 'source' => '']],
                    'teacherNotes' => ['ru' => 'Private block note', 'de' => 'Private DE block note']],
                    ['id' => 'choice-a', 'type' => 'core.single-choice', 'schemaVersion' => 1,
                        'content' => ['ru' => ['question' => 'Question', 'options' => [['optionId' => 'a', 'text' => 'A'], ['optionId' => 'b', 'text' => 'B']]],
                            'de' => ['question' => 'Frage', 'options' => [['optionId' => 'a', 'text' => 'Aa'], ['optionId' => 'b', 'text' => 'Bb']]]],
                        'solution' => ['optionId' => 'a']]]],
                ['id' => 'stage-b', 'content' => ['ru' => ['title' => 'Second'], 'de' => ['title' => 'Zweite']],
                    'blocks' => [['id' => 'text-b', 'type' => 'core.text', 'schemaVersion' => 1,
                        'content' => ['ru' => ['text' => 'Future RU'], 'de' => ['text' => 'Future DE']]]]]]];
    }

    private function editorLesson(): array
    {
        return $this->postJson('/api/studio/lessons', ['document' => $this->editorDocument()])->assertCreated()->json('lesson');
    }

    private function editorBody(array $lesson, ?array $document = null, ?string $saveId = null): array
    {
        return ['saveId' => $saveId ?? (string) Str::uuid(), 'expectedRevision' => $lesson['revision'], 'document' => $document ?? $lesson['document']];
    }

    private function editorSave(array $lesson, array $document): array
    {
        return $this->putJson('/api/studio/lessons/'.$lesson['id'], $this->editorBody($lesson, $document))->assertOk()->json('lesson');
    }

    private function blankLocale(array $document, string $locale): array
    {
        $document['content'][$locale]['title'] = '';
        foreach ($document['stages'] as &$stage) {
            $stage['content'][$locale]['title'] = '';
            foreach ($stage['blocks'] as &$block) {
                foreach ($block['content'][$locale] as $field => &$value) {
                    if (is_string($value)) {
                        $value = '';
                    } elseif ($field === 'options') {
                        foreach ($value as &$option) {
                            $option['text'] = '';
                        }
                    }
                }
                unset($value);
            }
            unset($block);
        }

        return $document;
    }
}
