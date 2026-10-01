<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GuestWorkspaceClaim extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'source_owner_key';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['source_owner_key', 'target_owner_key'];

    protected function casts(): array
    {
        return ['result' => 'array', 'target_user_id' => 'integer'];
    }
}
