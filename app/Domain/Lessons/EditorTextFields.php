<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Optional authoring metadata on the existing registered definition. */
interface EditorTextFields extends BlockType
{
    /** @return list<array{path:list<string>,required:bool,blankMode:'trim'|'unicode'}> */
    public function translatedTextFields(): array;
}
