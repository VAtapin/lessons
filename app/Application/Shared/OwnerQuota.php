<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Models\MediaOwnerQuota;
use App\Models\User;

final class OwnerQuota
{
    public static function limit(string $ownerKey): int
    {
        return (int) config(User::query()->where('owner_key', $ownerKey)->exists()
            ? 'lessons.media.account_quota_bytes' : 'lessons.media.guest_quota_bytes');
    }

    public static function present(string $ownerKey): array
    {
        return ['usedBytes' => MediaOwnerQuota::query()->find($ownerKey)?->used_bytes ?? 0,
            'limitBytes' => self::limit($ownerKey), 'maxFileBytes' => config('lessons.media.max_file_bytes')];
    }
}
