<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class MediaVersion extends Model
{
    use HasUuids;

    protected $fillable = ['media_asset_id', 'version_no', 'storage_key', 'mime', 'bytes', 'width', 'height', 'sha256', 'attribution'];

    protected $hidden = ['storage_key'];

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            if ($version->isDirty()) {
                throw new LogicException('Media versions are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['version_no' => 'integer', 'bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'attribution' => 'array'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
