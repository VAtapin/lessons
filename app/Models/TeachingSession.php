<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingSession extends Model
{
    use HasUuids;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['lesson_version_id', 'owner_key', 'locale', 'current_stage_id', 'revision', 'join_code', 'projector_token', 'status'];

    protected $hidden = ['owner_key', 'join_code', 'projector_token'];

    protected function casts(): array
    {
        return [
            'revision' => 'integer', 'timer_remaining_seconds' => 'integer', 'timer_resume_on_session_resume' => 'boolean',
            'timer_ends_at' => 'immutable_datetime', 'wave_expires_at' => 'immutable_datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LessonVersion::class, 'lesson_version_id');
    }
}
