<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Models\GuestWorkspaceClaim;
use App\Models\MediaOwnerQuota;
use App\Models\TeachingSession;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OwnerMutation
{
    public static function transaction(array $ownerKeys, Closure $mutation, bool $allowClaimed = false): mixed
    {
        $keys = array_values(array_unique(array_map(strtolower(...), $ownerKeys)));
        sort($keys, SORT_STRING);

        return DB::transaction(function () use ($keys, $mutation, $allowClaimed): mixed {
            foreach ($keys as $key) {
                // A no-op duplicate-key UPDATE obtains an exclusive InnoDB lock.
                // INSERT IGNORE would take a shared duplicate lock, risking an
                // upgrade deadlock when several waiting writers are released together.
                DB::table('media_owner_quotas')->upsert(
                    ['owner_key' => $key, 'used_bytes' => 0, 'created_at' => now(), 'updated_at' => now()],
                    ['owner_key'], ['owner_key'],
                );
                MediaOwnerQuota::query()->whereKey($key)->lockForUpdate()->firstOrFail();
            }
            if (! $allowClaimed && GuestWorkspaceClaim::query()->whereIn('source_owner_key', $keys)->exists()) {
                throw new ApiProblem('identity_changed', 409);
            }

            return $mutation();
        });
    }

    /** Retry ownership discovery if claim committed before the owner mutex was acquired. */
    public static function forSession(string $sessionId, Closure $mutation): mixed
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $owner = TeachingSession::query()->whereKey($sessionId)->value('owner_key')
                ?? throw new ApiProblem('not_found', 404);
            try {
                return self::transaction([$owner], function () use ($owner, $sessionId, $mutation): mixed {
                    $session = TeachingSession::query()->whereKey($sessionId)->lockForUpdate()->first()
                        ?? throw new ApiProblem('not_found', 404);
                    if ($session->owner_key !== $owner) {
                        throw new OwnerChanged;
                    }

                    return $mutation($session);
                }, true);
            } catch (OwnerChanged) {
                // A different cookie may have claimed this session. Membership is checked by the caller.
            }
        }

        throw new ApiProblem('identity_changed', 409);
    }
}

final class OwnerChanged extends RuntimeException {}
