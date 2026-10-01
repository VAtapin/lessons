<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Collaboration\TeacherAccess;
use App\Models\TeachingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class CollaborationIdentityTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
    }

    public function test_login_keeps_scoped_grants_claim_keeps_them_and_logout_drops_them_without_alias(): void
    {
        $session = $this->acceptedSession();
        $cookie = session(TeacherAccess::COOKIE_KEY);
        $user = User::factory()->create(['password' => Hash::make('secure password 123')]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $this->assertSame($cookie, session(TeacherAccess::COOKIE_KEY));
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertOk()->assertJsonPath('actor.kind', 'grant');
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertNotFound();
        $this->postJson('/api/account/guest-claim')->assertOk();
        $this->assertSame($cookie, session(TeacherAccess::COOKIE_KEY));
        $this->assertSame($user->fresh()->owner_key, TeachingSession::query()->findOrFail($session['id'])->owner_key);
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertOk()->assertJsonPath('actor.kind', 'grant');
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->assertJsonPath('actor.kind', 'owner');
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->assertNull(session(TeacherAccess::COOKIE_KEY));
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertNotFound();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertNotFound();
    }

    public function test_registration_preserves_existing_scoped_grant_without_claiming_session(): void
    {
        $session = $this->acceptedSession();
        $cookie = session(TeacherAccess::COOKIE_KEY);
        $this->postJson('/api/auth/register', ['name' => 'Teacher account', 'email' => 'helper-registration@example.test',
            'password' => 'secure password 123', 'passwordConfirmation' => 'secure password 123', 'uiLocale' => 'ru'])->assertCreated();
        $this->assertSame($cookie, session(TeacherAccess::COOKIE_KEY));
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertOk();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertNotFound();
        $this->postJson('/api/account/guest-continue')->assertNoContent();
        $this->assertNull(session(TeacherAccess::COOKIE_KEY));
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertNotFound();
    }

    public function test_invalidated_authenticated_session_drops_grant_map(): void
    {
        $session = $this->acceptedSession();
        $user = User::factory()->create(['password' => Hash::make('secure password 123')]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $user->forceFill(['password' => Hash::make('different password 456')])->save();
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertUnauthorized()->assertJsonMissingPath('session');
        $this->assertNull(session(TeacherAccess::COOKIE_KEY));
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertNotFound();
    }

    private function acceptedSession(): array
    {
        $session = $this->historySession();
        $invitation = $this->postJson('/api/studio/sessions/'.$session['id'].'/collaboration/commands',
            ['commandId' => (string) Str::uuid(), 'expectedRevision' => $session['revision'], 'controlEpoch' => 0, 'action' => 'invite.create', 'payload' => []])->assertOk()->json('invitation');
        $this->postJson('/api/teacher-invitations/accept', ['token' => substr($invitation['url'], strpos($invitation['url'], '#token=') + 7), 'displayName' => 'Helper'])->assertOk();

        return $session;
    }
}
