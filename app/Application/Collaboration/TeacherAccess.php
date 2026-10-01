<?php

declare(strict_types=1);

namespace App\Application\Collaboration;

use App\Application\History\RetentionPolicy;
use App\Application\Shared\ApiProblem;
use App\Models\TeacherGrant;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class TeacherAccess
{
    public const COOKIE_KEY = 'lesson_teacher_grants';

    public function __construct(private RetentionPolicy $retention) {}

    public function cookieActor(Request $request, string $sessionId): TeacherActor
    {
        $map = $request->session()->get(self::COOKIE_KEY, []);
        $entry = is_array($map) ? ($map[$sessionId] ?? null) : null;
        if (! is_array($entry) || ! is_string($entry['grantId'] ?? null) || ! is_string($entry['proof'] ?? null)) {
            throw new ApiProblem('not_found', 404);
        }

        return TeacherActor::grant($entry['grantId'], $entry['proof']);
    }

    /** Call again under the session row lock for every read and mutation. */
    public function assert(TeachingSession $session, TeacherActor $actor): ?TeacherGrant
    {
        $this->retention->assertOwnerReadable($session);
        if ($actor->kind === 'owner') {
            if (! hash_equals($session->owner_key, $actor->id)) {
                throw new ApiProblem('not_found', 404);
            }

            return null;
        }
        $grant = TeacherGrant::query()->whereKey($actor->id)->where('teaching_session_id', $session->id)->first();
        if ($session->mode !== 'lesson' || $grant === null || ! $this->valid($grant)
            || ! hash_equals($grant->proof_hash, hash('sha256', $actor->proof ?? ''))) {
            throw new ApiProblem('not_found', 404);
        }

        return $grant;
    }

    public function valid(TeacherGrant $grant): bool
    {
        return $grant->revoked_at === null && $grant->expires_at->greaterThan(CarbonImmutable::now('UTC'));
    }

    public function presenter(TeachingSession $session): array
    {
        if ($session->presenter_is_owner) {
            return ['kind' => 'owner'];
        }
        $grant = TeacherGrant::query()->whereKey($session->presenter_grant_id)->where('teaching_session_id', $session->id)->first();

        return $grant !== null && $this->valid($grant)
            ? ['kind' => 'grant', 'grantId' => $grant->id, 'displayName' => $grant->display_name]
            : ['kind' => 'vacant'];
    }

    public function metadata(TeachingSession $session, TeacherActor $actor): array
    {
        $grant = $this->assert($session, $actor);
        $presenter = $this->presenter($session);
        $active = ($actor->kind === 'owner' && $presenter['kind'] === 'owner')
            || ($actor->kind === 'grant' && ($presenter['grantId'] ?? null) === $actor->id);
        $capabilities = ['moderate'];
        if ($active) {
            $capabilities[] = 'present';
        }
        if ($actor->kind === 'owner') {
            $capabilities = [...$capabilities, 'finish', 'manageCollaboration'];
        }

        return ['actor' => ['kind' => $actor->kind, 'isPresenter' => $active, 'capabilities' => $capabilities]
            + ($grant !== null ? ['expiresAt' => $grant->expires_at->utc()->toISOString()] : []),
            'collaboration' => ['controlEpoch' => (int) $session->presenter_epoch, 'presenter' => $presenter]];
    }

    public function assertAction(TeachingSession $session, TeacherActor $actor, string $action): void
    {
        $capabilities = $this->metadata($session, $actor)['actor']['capabilities'];
        $required = match (true) {
            $action === 'finish' => 'finish',
            in_array($action, ['answer.moderate', 'answer.publish', 'answer.unpublish', 'answer.reply', 'role.assign', 'signal.ack'], true) => 'moderate',
            default => 'present',
        };
        if (! in_array($required, $capabilities, true)) {
            throw new ApiProblem('presenter_required', 409);
        }
    }
}
