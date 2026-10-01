<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class RolesBlock extends InteractiveDefinition
{
    public function id(): string
    {
        return 'core.roles';
    }

    public function defaults(): array
    {
        return ['capacities' => []];
    }

    public function initialState(): string
    {
        return 'open';
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        InteractiveShape::config($block, ['capacities']);
        $ids = InteractiveShape::content($block, $locales, 'text', ['roles' => ['roleId', 1, 12]])['roles'];
        if (! is_array($block->config['capacities'])) {
            throw new ValidationException('Role capacities must be an object.');
        }
        // PHP casts numeric JSON object keys to integers; stable role IDs remain strings.
        $keys = array_map('strval', array_keys($block->config['capacities']));
        sort($keys, SORT_STRING);
        if ($keys !== $ids || $block->solution !== null) {
            throw new ValidationException('Role capacities must exactly match roles and no solution is allowed.');
        }
        foreach ($block->config['capacities'] as $capacity) {
            Shape::integer($capacity, 'roles.capacity', 1, 100);
        }
    }

    public function validateAnswer(BlockInstance $block, array $value): array
    {
        $value = InteractiveShape::answer($value, ['roleId']);

        return ['roleId' => $value['roleId'] === null ? null : InteractiveShape::member($value['roleId'], InteractiveShape::ids($block, 'roles', 'roleId'))];
    }
}
