<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LessonSaveReceipt extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['lesson_material_id', 'save_id', 'fingerprint', 'applied_revision', 'applied_version_id'];

    protected function casts(): array
    {
        return ['applied_revision' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(LessonMaterial::class, 'lesson_material_id');
    }
}
