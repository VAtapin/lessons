<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

use App\Domain\Lessons\Types\FreeResponseBlock;
use App\Domain\Lessons\Types\ImageBlock;
use App\Domain\Lessons\Types\MatchingBlock;
use App\Domain\Lessons\Types\MultipleChoiceBlock;
use App\Domain\Lessons\Types\PollBlock;
use App\Domain\Lessons\Types\PromptBlock;
use App\Domain\Lessons\Types\RolesBlock;
use App\Domain\Lessons\Types\SequenceBlock;
use App\Domain\Lessons\Types\SignalsBlock;
use App\Domain\Lessons\Types\SingleChoiceBlock;
use App\Domain\Lessons\Types\StructuredTextBlock;
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
        $registry->register(new StructuredTextBlock);
        $registry->register(new PromptBlock);
        $registry->register(new MultipleChoiceBlock);
        $registry->register(new PollBlock);
        $registry->register(new FreeResponseBlock);
        $registry->register(new SequenceBlock);
        $registry->register(new MatchingBlock);
        $registry->register(new RolesBlock);
        $registry->register(new SignalsBlock);

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
