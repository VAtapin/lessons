<?php

declare(strict_types=1);

namespace App\Application\Collaboration;

use App\Application\Shared\ApiProblem;
use App\Models\SessionCommandReceipt;
use App\Models\TeachingSession;

/** Shared runtime and collaboration commands use one actor-bound UUID namespace. */
final class TeacherReceipts
{
    public function assertEpoch(TeachingSession $session, ?int $epoch): void
    {
        if (($epoch === null && (int) $session->presenter_epoch !== 0)
            || ($epoch !== null && $epoch !== (int) $session->presenter_epoch)) {
            throw new ApiProblem('control_conflict', 409);
        }
    }

    public function replay(TeachingSession $session, TeacherActor $actor, string $commandId, string $fingerprint): bool
    {
        $receipt = SessionCommandReceipt::query()->where('teaching_session_id', $session->id)->where('command_id', strtolower($commandId))->first();
        if ($receipt === null) {
            return false;
        }
        $legacy = $receipt->actor_kind === null && $actor->kind === 'owner' && (int) $session->presenter_epoch === 0;
        if ((! $legacy && ($receipt->actor_kind !== $actor->kind || $receipt->actor_id !== $actor->id
                || (int) $receipt->control_epoch !== (int) $session->presenter_epoch))
            || ! hash_equals($receipt->fingerprint, $fingerprint)) {
            throw new ApiProblem('command_conflict', 409);
        }

        return true;
    }

    public function record(TeachingSession $session, TeacherActor $actor, string $commandId, string $fingerprint): void
    {
        $receipt = new SessionCommandReceipt;
        $receipt->forceFill(['teaching_session_id' => $session->id, 'command_id' => strtolower($commandId),
            'fingerprint' => $fingerprint, 'actor_kind' => $actor->kind, 'actor_id' => $actor->id,
            'control_epoch' => (int) $session->presenter_epoch])->save();
    }
}
