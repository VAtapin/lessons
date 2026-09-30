<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class BlockTemplateVersion extends Model
{
    use HasUuids;

    protected $fillable = ['block_template_record_id', 'version_no', 'block', 'locales', 'default_locale', 'attribution'];

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            if ($version->isDirty()) {
                throw new LogicException('Template versions are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['version_no' => 'integer', 'block' => 'array', 'locales' => 'array', 'attribution' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BlockTemplateRecord::class, 'block_template_record_id');
    }
}
