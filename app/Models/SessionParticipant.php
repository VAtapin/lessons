<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SessionParticipant extends Model
{
    use HasUuids;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['teaching_session_id', 'name', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime'];
    }
}
