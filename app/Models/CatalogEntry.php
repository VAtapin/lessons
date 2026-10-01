<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['slug', 'lesson_version_id', 'metadata', 'status', 'approved_at', 'approved_by', 'source_revision', 'source_hash'])]
final class CatalogEntry extends Model
{
    use HasUuids;

    private bool $trustedSourceRepin = false;

    protected static function booted(): void
    {
        self::updating(function (self $entry): void {
            if ($entry->isDirty('slug') || (! $entry->trustedSourceRepin && $entry->isDirty(['lesson_version_id', 'source_revision', 'source_hash']))) {
                throw new LogicException('Catalog source and pinned release are immutable.');
            }
        });
    }

    /** Explicit reviewed source upgrade only, under the installer's locked transaction. */
    public function repinSourceRelease(string $expectedVersionId, string $expectedRevision, string $expectedHash, LessonVersion $version, string $revision, string $hash): void
    {
        if (! $this->exists || $this->isDirty() || DB::connection($this->getConnectionName())->transactionLevel() < 1
            || $this->lesson_version_id !== $expectedVersionId || $this->source_revision !== $expectedRevision || $this->source_hash !== $expectedHash
            || $version->status !== 'released' || $version->purpose !== 'authoring'
            || $version->lesson_material_id !== $this->version->lesson_material_id
            || $version->id === $expectedVersionId || $revision === '' || $revision === $expectedRevision
            || preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1) {
            throw new LogicException('Trusted source upgrade requires an exact receipt and a new released version of the same material.');
        }
        $this->trustedSourceRepin = true;
        try {
            $this->lesson_version_id = $version->id;
            $this->source_revision = $revision;
            $this->source_hash = $hash;
            $this->save();
            $this->unsetRelation('version');
        } finally {
            $this->trustedSourceRepin = false;
        }
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
