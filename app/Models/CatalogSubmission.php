<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CatalogSubmission extends Model
{
    use HasUuids;

    protected $fillable = ['owner_key', 'lesson_version_id', 'slug', 'metadata', 'revision', 'status', 'reason', 'reviewed_by', 'reviewed_at', 'catalog_entry_id'];

    protected static function booted(): void
    {
        self::updating(function (self $submission): void {
            if ($submission->isDirty(['owner_key', 'lesson_version_id', 'slug', 'metadata'])) {
                throw new LogicException('Submitted snapshots are immutable. Submit a new revision for another review.');
            }
        });
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'revision' => 'integer', 'reviewed_at' => 'immutable_datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LessonVersion::class, 'lesson_version_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CatalogEntry::class, 'catalog_entry_id');
    }
}
