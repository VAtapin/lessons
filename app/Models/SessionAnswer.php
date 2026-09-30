<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionAnswer extends Model
{
    protected $fillable = ['teaching_session_id', 'session_participant_id', 'block_id', 'option_id'];
}
