<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SessionCommandReceipt extends Model
{
    protected $fillable = ['teaching_session_id', 'command_id', 'fingerprint'];
}
