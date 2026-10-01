<?php

declare(strict_types=1);

namespace App\Application\Collaboration;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Models\TeacherGrant;
use App\Models\TeacherInvitation;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class TeacherInvitations
{
    public function __construct(private TeacherAccess $access) {}

    /** Only the first successful creation response contains the invitation secret. */
    public function create(TeachingSession $session, array $payload): array
    {
        if (array_diff(array_keys($payload), ['expiresInSeconds']) !== []
            || ! is_int($payload['expiresInSeconds'] ?? 3600)
            || ($payload['expiresInSeconds'] ?? 3600) < 60 || ($payload['expiresInSeconds'] ?? 3600) > 86400) {
            throw new ApiProblem('invalid_action', 422);
        }
        $token = (string) Str::uuid();
        $invitation = TeacherInvitation::create(['teaching_session_id' => $session->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => CarbonImmutable::now('UTC')->addSeconds($payload['expiresInSeconds'] ?? 3600)]);

        return ['id' => $invitation->id, 'expiresAt' => $invitation->expires_at->utc()->toISOString(),
            'url' => url('/'.app()->getLocale().'/teacher-invitations').'#token='.$token];
    }

    /** Caller installs cookie only after transaction commits; proof never belongs in JSON. */
    public function accept(string $token, string $displayName, array $installed): array
    {
        if (! Str::isUuid($token) || ! mb_check_encoding($displayName, 'UTF-8')
            || trim($displayName) === '' || mb_strlen($displayName) > 80) {
            throw new ApiProblem('invalid_action', 422);
        }
        $hash = hash('sha256', strtolower($token));
        $sessionId = TeacherInvitation::query()->where('token_hash', $hash)->value('teaching_session_id')
            ?? throw new ApiProblem('invitation_unavailable', 404);

        return OwnerMutation::forSession($sessionId, function (TeachingSession $session) use ($hash, $displayName, $installed): array {
            $invitation = TeacherInvitation::query()->where('token_hash', $hash)->where('teaching_session_id', $session->id)->lockForUpdate()->first();
            if ($invitation === null || $invitation->revoked_at !== null || $session->mode !== 'lesson'
                || $session->status === 'finished') {
                throw new ApiProblem('invitation_unavailable', 404);
            }
            $cookie = $installed[$session->id] ?? null;
            if ($invitation->accepted_at !== null) {
                if (! is_array($cookie) || ! is_string($cookie['grantId'] ?? null) || ! is_string($cookie['proof'] ?? null)) {
                    throw new ApiProblem('invitation_unavailable', 404);
                }
                $grant = $this->access->assert($session, TeacherActor::grant($cookie['grantId'], $cookie['proof']));
                if ($grant->teacher_invitation_id !== $invitation->id) {
                    throw new ApiProblem('invitation_unavailable', 404);
                }

                return $this->accepted($session, $grant, $cookie['proof']);
            }
            if ($invitation->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                throw new ApiProblem('invitation_unavailable', 404);
            }
            // Keep a live grant, but allow a fresh invitation to replace stale access.
            // Discovery and proof validation run under the same session lock as acceptance.
            if (is_array($cookie) && is_string($cookie['grantId'] ?? null)) {
                $installedGrant = TeacherGrant::query()->whereKey($cookie['grantId'])
                    ->where('teaching_session_id', $session->id)->first();
                if ($installedGrant !== null && $this->access->valid($installedGrant)) {
                    if (! is_string($cookie['proof'] ?? null)) {
                        throw new ApiProblem('not_found', 404);
                    }
                    $this->access->assert($session, TeacherActor::grant($cookie['grantId'], $cookie['proof']));
                    throw new ApiProblem('grant_already_installed', 409);
                }
            }
            $proof = (string) Str::uuid();
            $now = CarbonImmutable::now('UTC');
            $grant = TeacherGrant::create(['teaching_session_id' => $session->id,
                'teacher_invitation_id' => $invitation->id, 'proof_hash' => hash('sha256', $proof),
                'display_name' => trim($displayName), 'expires_at' => $now->addHours(4)]);
            $invitation->accepted_at = $now;
            $invitation->save();

            return $this->accepted($session, $grant, $proof);
        });
    }

    private function accepted(TeachingSession $session, TeacherGrant $grant, string $proof): array
    {
        return ['sessionId' => $session->id,
            'grant' => ['id' => $grant->id, 'displayName' => $grant->display_name, 'expiresAt' => $grant->expires_at->utc()->toISOString()],
            'teacherUrl' => url('/'.app()->getLocale().'/conduct/'.$session->id),
            'cookie' => ['grantId' => $grant->id, 'proof' => $proof]];
    }
}
