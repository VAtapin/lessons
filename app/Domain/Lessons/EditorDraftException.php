<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

use InvalidArgumentException;

final class EditorDraftException extends InvalidArgumentException
{
    /** @param list<array{code:string,path:string,locale?:string,stageId?:string,blockId?:string}> $issues */
    public function __construct(public readonly string $reason, public readonly array $issues)
    {
        parent::__construct('The editor document cannot satisfy the requested operation.');
    }
}
