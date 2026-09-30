<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

use App\Domain\Lessons\Types\ImageBlock;
use App\Domain\Lessons\Types\SingleChoiceBlock;
use App\Domain\Lessons\Types\TextBlock;

final class BlockRegistry
{
    /** @var array<string, array<int, BlockType>> */
    private array $types = [];

    public static function core(): self
    {
        $registry = new self;
        $registry->register(new TextBlock);
        $registry->register(new ImageBlock);
        $registry->register(new SingleChoiceBlock);

        return $registry;
    }

    public function register(BlockType $type): void
    {
        Shape::id($type->id(), 'blockType.id');
        if ($type->schemaVersion() < 1) {
            throw new ValidationException('Block type schema version must be positive.');
        }

        if (isset($this->types[$type->id()][$type->schemaVersion()])) {
            throw new ValidationException('Block type/version is already registered.');
        }

        $this->types[$type->id()][$type->schemaVersion()] = $type;
    }

    public function resolve(string $id, int $schemaVersion): BlockType
    {
        return $this->types[$id][$schemaVersion]
            ?? throw new ValidationException('Unsupported block type or schema version.');
    }
}
