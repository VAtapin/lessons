<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommonTemplate extends Model
{
    use HasUuids;

    protected $fillable = ['block_template_record_id', 'labels', 'visible'];

    protected function casts(): array
    {
        return ['labels' => 'array', 'visible' => 'boolean'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BlockTemplateRecord::class, 'block_template_record_id');
    }
}
