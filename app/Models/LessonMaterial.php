<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_key', 'revision', 'current_version_id', 'favorite', 'archived'])]
#[Hidden(['owner_key'])]
final class LessonMaterial extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['revision' => 'integer', 'favorite' => 'boolean', 'archived' => 'boolean'];
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LessonVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LessonVersion::class);
    }
}
