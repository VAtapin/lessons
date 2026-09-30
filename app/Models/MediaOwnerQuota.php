<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MediaOwnerQuota extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'owner_key';

    protected $keyType = 'string';

    protected $fillable = ['owner_key', 'used_bytes'];

    protected function casts(): array
    {
        return ['used_bytes' => 'integer'];
    }
}
