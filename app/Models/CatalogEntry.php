<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['slug', 'lesson_version_id', 'metadata', 'status', 'approved_at', 'approved_by', 'source_revision', 'source_hash'])]
final class CatalogEntry extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        self::updating(function (self $entry): void {
            if ($entry->isDirty(['slug', 'lesson_version_id', 'source_revision', 'source_hash'])) {
                throw new LogicException('Catalog source and pinned release are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'approved_at' => 'immutable_datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LessonVersion::class, 'lesson_version_id');
    }
}
