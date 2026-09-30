<?php

namespace App\Application\Shared;

use RuntimeException;

final class ApiProblem extends RuntimeException
{
    public function __construct(public readonly string $problemCode, public readonly int $status)
    {
        parent::__construct($problemCode);
    }
}
