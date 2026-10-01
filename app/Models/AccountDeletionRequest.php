<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'status', 'revision', 'requested_at', 'cancelled_at'])]
final class AccountDeletionRequest extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['revision' => 'integer', 'requested_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
