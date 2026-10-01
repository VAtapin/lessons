<?php

declare(strict_types=1);

namespace App\Application\Collaboration;

use App\Application\Runtime\RuntimeCommands;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Models\TeacherGrant;
use App\Models\TeacherInvitation;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;

final class CollaborationService
{
    public function __construct(private TeacherAccess $access, private TeacherInvitations $invitations,
        private TeacherReceipts $receipts, private RuntimeCommands $commands, private RuntimeService $runtime) {}

    public function overview(TeacherActor $actor, string $sessionId): array
    {
        return OwnerMutation::forSession($sessionId, function (TeachingSession $session) use ($actor): array {
            $this->owner($session, $actor);

            return $this->state($session, $actor);
        });
    }

    public function command(TeacherActor $actor, string $sessionId, string $commandId, int $revision,
        int $epoch, string $action, array $payload, array $extra = []): array
    {
        return OwnerMutation::forSession($sessionId, function (TeachingSession $session) use ($actor, $commandId, $revision, $epoch, $action, $payload, $extra): array {
            $this->owner($session, $actor);
            try {
                $this->receipts->assertEpoch($session, $epoch);
                $fingerprint = $this->commands->fingerprint($revision, $action, $payload, ['controlEpoch' => $epoch] + $extra);
                if ($this->receipts->replay($session, $actor, $commandId, $fingerprint)) {
                    return $this->state($session, $actor) + ['acknowledgedCommandId' => strtolower($commandId)];
                }
                if ((int) $session->revision !== $revision) {
                    throw new ApiProblem('revision_conflict', 409);
                }
                if ($extra !== []) {
                    throw new ApiProblem('invalid_action', 422);
                }
                $secret = $this->apply($session, $action, $payload);
                $session->revision++;
                $session->save();
                $this->receipts->record($session, $actor, $commandId, $fingerprint);

                return $this->state($session, $actor) + ['acknowledgedCommandId' => strtolower($commandId)] + $secret;
            } catch (ApiProblem $problem) {
                if ($problem->status === 409) {
                    throw new CollaborationConflict($problem->problemCode, $this->state($session, $actor));
                }
                throw $problem;
            }
        });
    }

    private function owner(TeachingSession $session, TeacherActor $actor): void
    {
        $this->access->assert($session, $actor);
        if ($actor->kind !== 'owner' || $session->mode !== 'lesson') {
            throw new ApiProblem('not_found', 404);
        }
    }

    private function apply(TeachingSession $session, string $action, array $payload): array
    {
        if ($session->status === 'finished' && ! in_array($action, ['invite.revoke', 'grant.revoke'], true)) {
            throw new ApiProblem('invalid_state', 409);
        }
        if ($action === 'invite.create') {
            return ['invitation' => $this->invitations->create($session, $payload)];
        }
        $required = match ($action) {
            'invite.revoke', 'grant.revoke' => ['id'], 'presenter.transfer' => ['grantId'],
            'presenter.reclaim' => [], default => throw new ApiProblem('invalid_action', 422),
        };
        if (array_keys($payload) !== $required || ($required !== [] && ! is_string($payload[$required[0]]))) {
            throw new ApiProblem('invalid_action', 422);
        }
        $now = CarbonImmutable::now('UTC');
        if ($action === 'invite.revoke') {
            $invitation = TeacherInvitation::query()->where('teaching_session_id', $session->id)->find($payload['id'])
                ?? throw new ApiProblem('not_found', 404);
            $invitation->revoked_at ??= $now;
            $invitation->save();
        } elseif ($action === 'grant.revoke') {
            $grant = $this->grant($session, $payload['id']);
            $grant->revoked_at ??= $now;
            $grant->save();
            if ($session->presenter_grant_id === $grant->id) {
                $session->presenter_grant_id = null;
                $session->presenter_is_owner = false;
                $session->presenter_epoch++;
            }
        } elseif ($action === 'presenter.transfer') {
            $grant = $this->grant($session, $payload['grantId']);
            if (! $this->access->valid($grant)) {
                throw new ApiProblem('grant_unavailable', 409);
            }
            $session->presenter_is_owner = false;
            $session->presenter_grant_id = $grant->id;
            $session->presenter_epoch++;
        } else {
            $session->presenter_is_owner = true;
            $session->presenter_grant_id = null;
            $session->presenter_epoch++;
        }

        return [];
    }

    private function grant(TeachingSession $session, string $id): TeacherGrant
    {
        return TeacherGrant::query()->where('teaching_session_id', $session->id)->find($id)
            ?? throw new ApiProblem('not_found', 404);
    }

    private function state(TeachingSession $session, TeacherActor $actor): array
    {
        $state = $this->runtime->actorState($session, $actor);
        $state['collaboration']['invitations'] = TeacherInvitation::query()->where('teaching_session_id', $session->id)->orderBy('created_at')->get()
            ->map(fn (TeacherInvitation $invite): array => ['id' => $invite->id, 'expiresAt' => $invite->expires_at->utc()->toISOString(),
                'acceptedAt' => $invite->accepted_at?->utc()->toISOString(), 'revokedAt' => $invite->revoked_at?->utc()->toISOString()])->all();
        $state['collaboration']['grants'] = TeacherGrant::query()->where('teaching_session_id', $session->id)->orderBy('created_at')->get()
            ->map(fn (TeacherGrant $grant): array => ['id' => $grant->id, 'displayName' => $grant->display_name,
                'expiresAt' => $grant->expires_at->utc()->toISOString(), 'revokedAt' => $grant->revoked_at?->utc()->toISOString(),
                'isPresenter' => ! $session->presenter_is_owner && $session->presenter_grant_id === $grant->id && $this->access->valid($grant)])->all();

        return $state;
    }
}
