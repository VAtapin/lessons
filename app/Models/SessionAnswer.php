<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionAnswer extends Model
{
    protected $fillable = ['teaching_session_id', 'session_participant_id', 'block_id', 'option_id',
        'value', 'revision', 'moderation_status', 'display_text', 'published', 'acknowledged', 'private_reply', 'discussed', 'kindness_points'];

    protected function casts(): array
    {
        return ['value' => 'array', 'revision' => 'integer', 'published' => 'boolean', 'acknowledged' => 'boolean', 'discussed' => 'boolean', 'kindness_points' => 'integer'];
    }
}
