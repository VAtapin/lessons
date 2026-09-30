<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use RuntimeException;

final class RuntimeConflict extends RuntimeException
{
    public function __construct(public readonly string $problemCode, public readonly array $state)
    {
        parent::__construct($problemCode);
    }
}
