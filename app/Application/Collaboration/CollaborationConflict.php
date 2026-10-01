<?php

declare(strict_types=1);

namespace App\Application\Collaboration;

use RuntimeException;

final class CollaborationConflict extends RuntimeException
{
    public function __construct(public readonly string $problemCode, public readonly array $state)
    {
        parent::__construct($problemCode);
    }
}
