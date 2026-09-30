<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SessionParticipant extends Model
{
    use HasUuids;

    protected $fillable = ['teaching_session_id', 'name'];
}
