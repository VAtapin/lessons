<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\ApiProblem;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;

final class SessionTimer
{
    public function project(TeachingSession $session, CarbonImmutable $now): array
    {
        if ($session->timer_status === 'running') {
            $remaining = max(0, (int) ceil($now->diffInSeconds($session->timer_ends_at, false)));

            return ['status' => $remaining > 0 ? 'running' : 'expired', 'endsAt' => $session->timer_ends_at->utc()->toISOString(), 'remainingSeconds' => $remaining];
        }

        return ['status' => $session->timer_status, 'endsAt' => null, 'remainingSeconds' => $session->timer_remaining_seconds];
    }

    public function start(TeachingSession $session, int $seconds, CarbonImmutable $now): void
    {
        $session->timer_status = 'running';
        $session->timer_ends_at = $now->addSeconds($seconds);
        $session->timer_remaining_seconds = 0;
        $session->timer_resume_on_session_resume = false;
    }

    public function pause(TeachingSession $session, CarbonImmutable $now): void
    {
        $timer = $this->project($session, $now);
        if ($timer['status'] !== 'running') {
            throw new ApiProblem('invalid_state', 409);
        }
        $session->timer_status = 'paused';
        $session->timer_remaining_seconds = $timer['remainingSeconds'];
        $session->timer_ends_at = null;
    }

    public function resume(TeachingSession $session, CarbonImmutable $now): void
    {
        if ($session->timer_status !== 'paused' || $session->timer_remaining_seconds < 1) {
            throw new ApiProblem('invalid_state', 409);
        }
        $this->start($session, $session->timer_remaining_seconds, $now);
    }

    public function clear(TeachingSession $session): void
    {
        $session->timer_status = 'idle';
        $session->timer_remaining_seconds = 0;
        $session->timer_ends_at = null;
        $session->timer_resume_on_session_resume = false;
    }
}
