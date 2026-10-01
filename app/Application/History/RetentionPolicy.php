<?php

declare(strict_types=1);

namespace App\Application\History;

use App\Application\Shared\ApiProblem;
use App\Models\TeachingSession;
use App\Models\User;
use Carbon\CarbonImmutable;

final class RetentionPolicy
{
    public function detailsExpiresAt(TeachingSession $session): ?CarbonImmutable
    {
        if ($session->status !== 'finished') {
            return null;
        }

        return $session->mode === 'rehearsal' ? $session->created_at->toImmutable()->utc()->addDays(7)
            : $session->finished_at?->utc()->addDays(30);
    }

    public function historyExpiresAt(TeachingSession $session, ?bool $account = null): ?CarbonImmutable
    {
        if ($session->status !== 'finished') {
            return null;
        }
        if ($session->mode === 'rehearsal') {
            return $session->created_at->toImmutable()->utc()->addDays(7);
        }
        if ($session->finished_at === null) {
            return null;
        }

        return ($account ?? User::query()->where('owner_key', $session->owner_key)->exists())
            ? $session->finished_at->utc()->addYearsNoOverflow(2) : $session->finished_at->utc()->addDays(30);
    }

    public function expired(?CarbonImmutable $at): bool
    {
        return $at !== null && CarbonImmutable::now('UTC')->greaterThanOrEqualTo($at);
    }

    public function detailsAvailable(TeachingSession $session): bool
    {
        return $session->details_purged_at === null && ! $this->expired($this->detailsExpiresAt($session));
    }

    public function assertOwnerReadable(TeachingSession $session): void
    {
        if ($this->expired($this->historyExpiresAt($session))) {
            throw new ApiProblem('not_found', 404);
        }
    }

    public function assertPublicReadable(TeachingSession $session): void
    {
        if ($session->mode === 'rehearsal' || $session->public_access_closed_at !== null || ! $this->detailsAvailable($session)) {
            throw new ApiProblem('not_found', 404);
        }
    }
}
