<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Optional capabilities of a trusted definition; runtime controls access and state. */
interface InteractiveBlockType extends BlockType
{
    public function validateAnswer(BlockInstance $block, array $value): array;

    public function grade(BlockInstance $block, array $value): ?bool;

    public function publicResult(BlockInstance $block): ?array;

    public function initialState(): string;
}
