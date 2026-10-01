<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['lesson_material_id', 'status', 'document', 'purpose', 'editor_draft'])]
final class LessonVersion extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            if (($version->getOriginal('status') === 'released' || $version->getOriginal('purpose') === 'rehearsal') && $version->isDirty()) {
                throw new LogicException('Released lesson versions are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['document' => 'array', 'editor_draft' => 'array'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(LessonMaterial::class, 'lesson_material_id');
    }
}
