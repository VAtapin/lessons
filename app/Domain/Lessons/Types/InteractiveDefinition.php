<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\EditorTextFields;
use App\Domain\Lessons\InteractiveBlockType;

/** Common stateless defaults; each definition owns its strict data and answer forms. */
abstract class InteractiveDefinition implements EditorTextFields, InteractiveBlockType
{
    protected function textFields(string $prompt, array $groups = []): array
    {
        $fields = [['path' => [$prompt], 'required' => true, 'blankMode' => 'unicode']];
        foreach ($groups as $group) {
            $fields[] = ['path' => [$group, '*', 'text'], 'required' => true, 'blankMode' => 'unicode'];
        }

        return $fields;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function defaults(): array
    {
        return ['allowRepeat' => false];
    }

    public function grade(BlockInstance $block, array $value): ?bool
    {
        return null;
    }

    public function publicResult(BlockInstance $block): ?array
    {
        return null;
    }

    public function initialState(): string
    {
        return 'prepared';
    }
}
