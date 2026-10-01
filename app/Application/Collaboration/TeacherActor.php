<?php

declare(strict_types=1);

namespace App\Application\Collaboration;

/** Credentials exist only in server memory and server-side session storage. */
final readonly class TeacherActor
{
    private function __construct(public string $kind, public string $id, public ?string $proof = null) {}

    public static function owner(string $key): self
    {
        return new self('owner', $key);
    }

    public static function grant(string $id, string $proof): self
    {
        return new self('grant', $id, $proof);
    }
}
