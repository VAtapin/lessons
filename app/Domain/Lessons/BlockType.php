<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Implementations are registered by trusted application code, never by lesson JSON. */
interface BlockType
{
    public function id(): string;

    public function schemaVersion(): int;

    public function defaults(): array;

    public function validate(BlockInstance $block, array $locales): void;
}
