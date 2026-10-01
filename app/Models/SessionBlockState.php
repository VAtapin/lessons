<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionBlockState extends Model
{
    protected $fillable = ['teaching_session_id', 'block_id', 'status', 'attempt_no'];

    protected function casts(): array
    {
        return ['attempt_no' => 'integer'];
    }
}
