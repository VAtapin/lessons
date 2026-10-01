<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Collaboration\TeacherAccess;
use App\Application\Collaboration\TeacherActor;
use App\Application\Collaboration\TeacherInvitations;
use App\Application\Shared\ApiProblem;
use App\Models\TeacherGrant;
use App\Models\TeacherInvitation;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class TeacherInvitationTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T12:00:00Z'));
    }

    public function test_invitation_and_grant_store_only_hashes_and_retry_requires_same_cookie(): void
    {
        [$session, $invite, $token] = $this->invite();
        $service = app(TeacherInvitations::class);
        $accepted = $service->accept($token, ' Teacher ', []);
        $grant = TeacherGrant::query()->findOrFail($accepted['grant']['id']);
        $stored = TeacherInvitation::query()->findOrFail($invite['id']);
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertSame(hash('sha256', $accepted['cookie']['proof']), $grant->proof_hash);
        $this->assertSame('Teacher', $grant->display_name);
        $this->assertSame('2026-10-01T16:00:00.000000Z', $accepted['grant']['expiresAt']);
        $this->assertArrayNotHasKey('proof_hash', $grant->toArray());
        $this->assertArrayNotHasKey('token_hash', $stored->toArray());
        $this->assertSame($accepted, $service->accept($token, 'Changed ignored', [$session->id => $accepted['cookie']]));
        $this->unavailable(fn () => $service->accept($token, 'Other browser', []));
        $this->travel(61)->minutes();
        $this->assertSame($accepted, $service->accept($token, 'After invitation expiry', [$session->id => $accepted['cookie']]));
        $this->travelTo(CarbonImmutable::parse('2026-10-01T16:00:00Z'));
        $this->unavailable(fn () => $service->accept($token, 'Expired', [$session->id => $accepted['cookie']]));
        $this->assertDatabaseCount('teacher_grants', 1);
    }

    public function test_other_installed_grant_does_not_consume_new_invitation(): void
    {
        [$session, , $token] = $this->invite();
        $first = app(TeacherInvitations::class)->accept($token, 'First', []);
        $second = app(TeacherInvitations::class)->create($session, []);
        $this->unavailable(fn () => app(TeacherInvitations::class)->accept($this->token($second), 'Second', [$session->id => $first['cookie']]));
        $this->assertNull(TeacherInvitation::query()->findOrFail($second['id'])->accepted_at);
        $this->assertDatabaseCount('teacher_grants', 1);
    }

    public function test_expiry_revocation_finished_and_rehearsal_prevent_acceptance(): void
    {
        [$session, $invite, $token] = $this->invite();
        $stored = TeacherInvitation::query()->findOrFail($invite['id']);
        $stored->revoked_at = now();
        $stored->save();
        $this->unavailable(fn () => app(TeacherInvitations::class)->accept($token, 'Teacher', []));
        $stored->revoked_at = null;
        $stored->expires_at = now();
        $stored->save();
        $this->unavailable(fn () => app(TeacherInvitations::class)->accept($token, 'Teacher', []));
        $stored->expires_at = now()->addHour();
        $stored->save();
        foreach (['finished', 'rehearsal'] as $case) {
            $session->forceFill($case === 'finished' ? ['status' => 'finished'] : ['status' => 'running', 'mode' => 'rehearsal'])->save();
            $this->unavailable(fn () => app(TeacherInvitations::class)->accept($token, 'Teacher', []));
        }
        $this->assertDatabaseCount('teacher_grants', 0);
    }

    public function test_http_reinvitation_replaces_expired_access_in_the_same_durable_cookie_map(): void
    {
        $this->reinviteAfter('expiry');
    }

    public function test_http_reinvitation_replaces_revoked_access_in_the_same_durable_cookie_map(): void
    {
        $this->reinviteAfter('revoke');
    }

    public function test_http_live_installed_grant_blocks_replacement_and_invalid_proof_does_not_consume_invitation(): void
    {
        [$session, , $token] = $this->invite();
        $first = $this->postJson('/api/teacher-invitations/accept', ['token' => $token, 'displayName' => 'First'])->assertOk()->json('grant');
        $fresh = app(TeacherInvitations::class)->create($session, []);
        $freshToken = $this->token($fresh);
        $this->postJson('/api/teacher-invitations/accept', ['token' => $freshToken, 'displayName' => 'Replacement'])->assertConflict()
            ->assertJsonPath('error.code', 'grant_already_installed')->assertJsonMissingPath('session');
        $this->assertSame($first['id'], session(TeacherAccess::COOKIE_KEY)[$session->id]['grantId']);
        $this->assertNull(TeacherInvitation::query()->findOrFail($fresh['id'])->accepted_at);
        $this->travel(61)->minutes();
        $this->postJson('/api/teacher-invitations/accept', ['token' => $token, 'displayName' => 'Retry'])->assertOk()
            ->assertJsonPath('grant.id', $first['id'])->assertJsonPath('grant.expiresAt', $first['expiresAt']);
        $stillFresh = app(TeacherInvitations::class)->create($session, []);
        $map = session(TeacherAccess::COOKIE_KEY);
        $map[$session->id]['proof'] = (string) Str::uuid();
        $this->withSession([TeacherAccess::COOKIE_KEY => $map]);
        $this->postJson('/api/teacher-invitations/accept', ['token' => $this->token($stillFresh), 'displayName' => 'Replacement'])->assertNotFound()
            ->assertJsonMissingPath('session')->assertJsonMissingPath('grant')->assertJsonMissingPath('cookie');
        $this->assertNull(TeacherInvitation::query()->findOrFail($stillFresh['id'])->accepted_at);
        $this->assertDatabaseCount('teacher_grants', 1);
    }

    public function test_proof_expiry_and_revocation_checked_on_every_access_without_owner_fallback(): void
    {
        [$session, , $token] = $this->invite();
        $accepted = app(TeacherInvitations::class)->accept($token, 'Teacher', []);
        $actor = TeacherActor::grant($accepted['grant']['id'], $accepted['cookie']['proof']);
        $access = app(TeacherAccess::class);
        $this->assertSame(['moderate'], $access->metadata($session, $actor)['actor']['capabilities']);
        $this->unavailable(fn () => $access->assert($session, TeacherActor::grant($actor->id, (string) Str::uuid())));
        $session->forceFill(['presenter_is_owner' => false, 'presenter_grant_id' => $actor->id])->save();
        $session->refresh();
        $this->assertTrue($access->metadata($session, $actor)['actor']['isPresenter']);
        $this->assertFalse($access->metadata($session, TeacherActor::owner($this->historyOwner))['actor']['isPresenter']);
        $grant = TeacherGrant::query()->findOrFail($actor->id);
        $grant->revoked_at = now();
        $grant->save();
        $this->unavailable(fn () => $access->assert($session, $actor));
        $this->assertSame(['kind' => 'vacant'], $access->presenter($session));
        $grant->revoked_at = null;
        $grant->expires_at = now();
        $grant->save();
        $this->unavailable(fn () => $access->assert($session, $actor));
    }

    private function invite(): array
    {
        $state = $this->historySession();
        $session = TeachingSession::query()->findOrFail($state['id']);
        $invite = app(TeacherInvitations::class)->create($session, []);

        return [$session, $invite, $this->token($invite)];
    }

    private function reinviteAfter(string $reason): void
    {
        [$session, , $oldToken] = $this->invite();
        $old = $this->postJson('/api/teacher-invitations/accept', ['token' => $oldToken, 'displayName' => 'First'])->assertOk()->json('grant');
        if ($reason === 'expiry') {
            $this->travelTo(CarbonImmutable::parse('2026-10-01T16:00:00Z'));
        } else {
            $this->postJson('/api/studio/sessions/'.$session->id.'/collaboration/commands', ['commandId' => (string) Str::uuid(),
                'expectedRevision' => 1, 'controlEpoch' => 0, 'action' => 'grant.revoke', 'payload' => ['id' => $old['id']]])->assertOk();
        }
        $this->getJson('/api/conduct/sessions/'.$session->id)->assertNotFound()->assertJsonMissingPath('session');
        $this->assertSame($old['id'], session(TeacherAccess::COOKIE_KEY)[$session->id]['grantId']);
        $this->postJson('/api/teacher-invitations/accept', ['token' => $oldToken, 'displayName' => 'Old retry'])->assertNotFound();
        $fresh = app(TeacherInvitations::class)->create($session, []);
        $new = $this->postJson('/api/teacher-invitations/accept', ['token' => $this->token($fresh), 'displayName' => 'Replacement'])->assertOk()
            ->assertJsonMissingPath('cookie')->assertJsonMissingPath('proof')->json('grant');
        $this->assertNotSame($old['id'], $new['id']);
        $this->assertSame($new['id'], session(TeacherAccess::COOKIE_KEY)[$session->id]['grantId']);
        $this->assertSame(now()->addHours(4)->utc()->toISOString(), $new['expiresAt']);
        $this->getJson('/api/conduct/sessions/'.$session->id)->assertOk()->assertJsonPath('actor.kind', 'grant');
        $this->postJson('/api/teacher-invitations/accept', ['token' => $oldToken, 'displayName' => 'Old retry'])->assertNotFound();
        $this->assertDatabaseCount('teacher_grants', 2);
    }

    private function token(array $invite): string
    {
        return substr($invite['url'], strpos($invite['url'], '#token=') + 7);
    }

    private function unavailable(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The operation must reject inaccessible invitation or grant.');
        } catch (ApiProblem $problem) {
            $this->assertContains($problem->status, [404, 409]);
        }
    }
}
