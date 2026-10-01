<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingSession extends Model
{
    use HasUuids;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['lesson_version_id', 'owner_key', 'locale', 'current_stage_id', 'revision', 'join_code', 'projector_token', 'status', 'mode', 'started_at', 'visited_stage_ids'];

    protected $hidden = ['owner_key', 'join_code', 'projector_token'];

    protected function casts(): array
    {
        return [
            'revision' => 'integer', 'timer_remaining_seconds' => 'integer', 'timer_resume_on_session_resume' => 'boolean',
            'timer_ends_at' => 'immutable_datetime', 'wave_expires_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime',
            'details_purged_at' => 'immutable_datetime', 'public_access_closed_at' => 'immutable_datetime',
            'visited_stage_ids' => 'array', 'final_aggregates' => 'array',
            'presenter_is_owner' => 'boolean', 'presenter_epoch' => 'integer', 'join_projection' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LessonVersion::class, 'lesson_version_id');
    }

    public function commandReceipts(): HasMany
    {
        return $this->hasMany(SessionCommandReceipt::class);
    }
}
