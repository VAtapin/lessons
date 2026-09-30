<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MediaAsset extends Model
{
    use HasUuids;

    protected $fillable = ['owner_key', 'title', 'tags', 'author', 'source', 'rights_basis', 'usage_rights', 'revision', 'archived', 'current_version_id'];

    protected $hidden = ['owner_key'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'revision' => 'integer', 'archived' => 'boolean'];
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(MediaVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MediaVersion::class);
    }
}
