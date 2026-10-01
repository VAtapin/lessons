<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Private authoring value; only actual ready subsets become strict snapshots. */
final readonly class EditorDraft
{
    private function __construct(
        private array $data,
        private array $leaves,
        private LessonDocument $validationDocument,
        private BlockRegistry $registry,
    ) {}

    public static function fromArray(array $data, BlockRegistry $registry): self
    {
        $copy = self::validate([], fn () => Shape::copy($data));
        self::validate([], fn () => Shape::object($copy,
            ['id', 'schemaVersion', 'defaultLocale', 'locales', 'content', 'stages'], ['documentation'], 'document'));
        $locales = self::validate(['locales'], fn () => Shape::locales($copy['locales']));
        $leaves = [];
        $title = [['path' => ['title'], 'required' => true, 'blankMode' => 'trim']];
        foreach ($locales as $locale) {
            array_push($leaves, ...EditorText::prepare($copy, ['content', $locale], $title, ['locale' => $locale]));
        }
        $stages = self::validate(['stages'], fn () => Shape::list($copy['stages'], 'stages'));
        $stageIds = [];
        $blockIds = [];
        foreach ($stages as $stageIndex => $stage) {
            $path = ['stages', $stageIndex];
            self::validate($path, fn () => Shape::object($stage, ['id', 'content', 'blocks'], ['config'], 'stage'));
            $stageId = self::validate([...$path, 'id'], fn () => Shape::id($stage['id'], 'stage.id'));
            $context = ['stageId' => $stageId];
            if (in_array($stageId, $stageIds, true)) {
                throw new EditorDraftException('invalid_editor_document', [EditorText::issue('duplicate_id', [...$path, 'id'], $context)]);
            }
            $stageIds[] = $stageId;
            foreach ($locales as $locale) {
                array_push($leaves, ...EditorText::prepare($copy, [...$path, 'content', $locale], $title, [...$context, 'locale' => $locale]));
            }
            $blocks = self::validate([...$path, 'blocks'], fn () => Shape::list($stage['blocks'], 'stage.blocks'), $context);
            foreach ($blocks as $blockIndex => $block) {
                $blockPath = [...$path, 'blocks', $blockIndex];
                $definition = self::validate($blockPath, function () use ($block, $registry): BlockType {
                    Shape::object($block, ['id', 'type', 'schemaVersion', 'content'], ['config', 'media', 'solution', 'origin', 'teacherNotes'], 'block');
                    Shape::id($block['type'], 'block.type');
                    if (! is_int($block['schemaVersion'])) {
                        throw new ValidationException('Unsupported block version.');
                    }

                    return $registry->resolve($block['type'], $block['schemaVersion']);
                }, $context);
                $blockId = self::validate([...$blockPath, 'id'], fn () => Shape::id($block['id'], 'block.id'), $context);
                $blockContext = [...$context, 'blockId' => $blockId];
                if (in_array($blockId, $blockIds, true)) {
                    throw new EditorDraftException('invalid_editor_document', [EditorText::issue('duplicate_id', [...$blockPath, 'id'], $blockContext)]);
                }
                $blockIds[] = $blockId;
                if ($definition instanceof EditorTextFields) {
                    foreach ($locales as $locale) {
                        array_push($leaves, ...EditorText::prepare($copy, [...$blockPath, 'content', $locale],
                            $definition->translatedTextFields(), [...$blockContext, 'locale' => $locale]));
                    }
                }
                self::validate($blockPath, fn () => BlockInstance::fromArray($copy['stages'][$stageIndex]['blocks'][$blockIndex], $registry, $locales), $blockContext);
            }
            self::validate($path, fn () => Stage::fromArray($copy['stages'][$stageIndex], $registry, $locales), $context);
        }
        $document = self::validate([], fn () => LessonDocument::fromArray($copy, $registry));

        return new self(EditorText::restore($document->toArray(), $leaves), $leaves, $document, clone $registry);
    }

    public function toArray(): array
    {
        return Shape::copy($this->data);
    }

    public function readiness(): array
    {
        return $this->readinessFor($this->leaves);
    }

    public function blockReadiness(string $blockId): array
    {
        $this->block($blockId);

        return $this->readinessFor(array_values(array_filter($this->leaves,
            fn (array $leaf) => ($leaf['blockId'] ?? null) === $blockId)));
    }

    public function readyDocument(array $locales): LessonDocument
    {
        $locales = $this->selectedLocales($locales, $this->readiness());
        $data = $this->data;
        $data['locales'] = $locales;
        $data['content'] = self::translations($data['content'], $locales);
        if (isset($data['documentation'])) {
            $data['documentation'] = TeacherDocumentation::fromArray($data['documentation'], $this->data['locales'])->forLocales($locales);
        }
        foreach ($data['stages'] as &$stage) {
            $stage['content'] = self::translations($stage['content'], $locales);
            foreach ($stage['blocks'] as &$block) {
                $block = self::blockTranslations($block, $locales);
            }
            unset($block);
        }
        unset($stage);

        return self::validate([], fn () => LessonDocument::fromArray($data, $this->registry));
    }

    public function readyBlock(string $blockId, array $locales): BlockInstance
    {
        $block = $this->block($blockId);
        $locales = $this->selectedLocales($locales, $this->blockReadiness($blockId));

        return self::validate(['stages', $block['stageIndex'], 'blocks', $block['blockIndex']],
            fn () => BlockInstance::fromArray(self::blockTranslations($block['data'], $locales), $this->registry, $locales),
            ['stageId' => $block['stageId'], 'blockId' => $blockId]);
    }

    public function projectStage(Audience $audience, string $locale, string $stageId): array
    {
        if (! in_array($locale, $this->data['locales'], true)) {
            throw new EditorDraftException('invalid_editor_document', [EditorText::issue('invalid_locale', ['locales'])]);
        }
        foreach ($this->data['stages'] as $index => $stage) {
            if ($stage['id'] !== $stageId) {
                continue;
            }
            $view = $this->validationDocument->stages[$index]->project($audience, $locale);
            // Keep the existing audience whitelist, restoring actual content before return.
            $view['content']['title'] = $stage['content'][$locale]['title'];
            foreach ($stage['blocks'] as $blockIndex => $block) {
                $view['blocks'][$blockIndex]['content'] = $block['content'][$locale];
            }

            return Shape::copy($view);
        }

        throw new EditorDraftException('stage_not_found', [EditorText::issue('stage_not_found', ['stages'])]);
    }

    private function block(string $blockId): array
    {
        foreach ($this->data['stages'] as $stageIndex => $stage) {
            foreach ($stage['blocks'] as $blockIndex => $block) {
                if ($block['id'] === $blockId) {
                    return ['data' => $block, 'stageIndex' => $stageIndex, 'stageId' => $stage['id'], 'blockIndex' => $blockIndex];
                }
            }
        }

        throw new EditorDraftException('block_not_found', [EditorText::issue('block_not_found', ['stages'])]);
    }

    private function readinessFor(array $leaves): array
    {
        $translations = [];
        $ready = [];
        foreach ($this->data['locales'] as $locale) {
            $fields = array_values(array_filter($leaves, fn (array $leaf) => $leaf['locale'] === $locale));
            $issues = [];
            foreach ($fields as $leaf) {
                if ($leaf['blank']) {
                    $context = array_intersect_key($leaf, array_flip(['locale', 'stageId', 'blockId']));
                    $issues[] = EditorText::issue('required_text', $leaf['path'], $context);
                }
            }
            $status = $issues === [] ? 'ready' : (count($issues) === count($fields) ? 'draft' : 'partial');
            $translations[] = ['locale' => $locale, 'status' => $status, 'issues' => $issues];
            if ($status === 'ready') {
                $ready[] = $locale;
            }
        }

        return ['defaultLocale' => $this->data['defaultLocale'], 'readyLocales' => $ready, 'locales' => $translations];
    }

    private function selectedLocales(array $locales, array $readiness): array
    {
        self::validate(['locales'], fn () => Shape::locales($locales));
        if (! in_array($this->data['defaultLocale'], $locales, true)
            || array_diff($locales, $this->data['locales']) !== []) {
            throw new EditorDraftException('invalid_editor_document', [EditorText::issue('invalid_locale_selection', ['locales'])]);
        }
        $issues = [];
        foreach ($readiness['locales'] as $translation) {
            if (in_array($translation['locale'], $locales, true)) {
                array_push($issues, ...$translation['issues']);
            }
        }
        if ($issues !== []) {
            throw new EditorDraftException('translation_not_ready', $issues);
        }

        return array_values(array_filter($this->data['locales'], fn (string $locale) => in_array($locale, $locales, true)));
    }

    private static function blockTranslations(array $block, array $locales): array
    {
        $block['content'] = self::translations($block['content'], $locales);
        if (($block['teacherNotes'] ?? []) !== []) {
            $block['teacherNotes'] = self::translations($block['teacherNotes'], $locales);
        }

        return $block;
    }

    private static function translations(array $content, array $locales): array
    {
        return array_intersect_key($content, array_flip($locales));
    }

    private static function validate(array $path, callable $validation, array $context = []): mixed
    {
        try {
            return $validation();
        } catch (ValidationException) {
            throw new EditorDraftException('invalid_editor_document', [EditorText::issue('invalid_shape', $path, $context)]);
        }
    }
}
