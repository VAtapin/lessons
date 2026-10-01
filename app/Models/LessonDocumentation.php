<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** First trusted documentation receipt for an existing release; write once. */
#[Fillable(['lesson_version_id', 'payload', 'source_hash'])]
final class LessonDocumentation extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'lesson_version_id';

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Released documentation receipts are immutable.');
        });
    }

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
