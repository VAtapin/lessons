<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccountAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        if (! Route::has('verification.verify')) {
            require base_path('routes/account.php');
        }
    }

    public function test_registration_rotates_credentials_preserves_participation_and_does_not_claim(): void
    {
        $guest = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $guest, 'lesson_participants' => ['lesson' => 'participant']]);
        $this->getJson('/api/account')->assertOk()->assertJsonPath('user', null);
        $oldId = session()->getId();
        $oldToken = session()->token();
        $response = $this->postJson('/api/auth/register', $this->registration())->assertCreated()
            ->assertJsonPath('user.verified', false)->assertJsonPath('verificationRequired', true);
        $user = User::findOrFail($response->json('user.id'));
        $this->assertTrue(Str::isUuid($user->owner_key));
        $this->assertNotSame($guest, $user->owner_key);
        $this->assertTrue(Hash::check('secure password 123', $user->password));
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $response->assertSessionHas('pending_guest_owner_key', $guest)->assertSessionHas('lesson_participants', ['lesson' => 'participant']);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->getJson('/api/account')->assertOk()->assertJsonPath('quota.limitBytes', 1073741824)->assertJsonPath('guestClaimAvailable', true);
        $this->postJson('/api/account/guest-claim')->assertForbidden()->assertJsonPath('error.code', 'verification_required');
        $this->assertDatabaseCount('guest_workspace_claims', 0);
        $this->assertStringNotContainsString($user->owner_key, $response->getContent());
    }

    public function test_strict_registration_and_password_unicode_byte_boundaries(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['invalid'], 'password' => 'not valid'])->assertUnprocessable();
        $this->postJson('/api/auth/forgot-password', ['email' => ['invalid']])->assertUnprocessable();
        foreach ([str_repeat('x', 11), str_repeat('ü', 37), "secure password\0"] as $password) {
            $this->postJson('/api/auth/register', array_replace($this->registration(), ['password' => $password, 'passwordConfirmation' => $password]))->assertUnprocessable();
        }
        $this->postJson('/api/auth/register', $this->registration() + ['owner_key' => Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
        $password = '  '.str_repeat('ü', 34).'  ';
        $this->postJson('/api/auth/register', array_replace($this->registration(), ['email' => 'TEACHER@example.test', 'password' => $password, 'passwordConfirmation' => $password]))->assertCreated();
        $this->assertTrue(Hash::check($password, User::firstOrFail()->password));
        $this->assertSame('teacher@example.test', User::firstOrFail()->email);
        $this->postJson('/api/auth/register', $this->registration())->assertUnprocessable()->assertJsonPath('error.code', 'registration_unavailable');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_logout_and_explicit_guest_continue_do_not_alias_account_owner(): void
    {
        $user = User::factory()->create(['password' => 'secure password 123']);
        $guest = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $guest, 'lesson_participants' => ['s' => 'p']]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized()->assertJsonPath('error.code', 'invalid_credentials');
        $this->postJson('/api/auth/login', ['email' => strtoupper($user->email), 'password' => 'secure password 123'])->assertOk();
        $this->assertNotNull($user->fresh()->owner_key);
        $this->postJson('/api/auth/logout')->assertNoContent()->assertSessionMissing('studio_owner_key')->assertSessionHas('pending_guest_owner_key', $guest);
        $this->getJson('/api/account')->assertJsonPath('user', null)->assertJsonPath('quota.limitBytes', 104857600)->assertJsonPath('guestClaimAvailable', true);
        $this->assertSame($guest, session('studio_owner_key'));
        $this->assertNotSame($user->fresh()->owner_key, session('studio_owner_key'));
        $this->postJson('/api/account/guest-continue')->assertNoContent()->assertSessionHas('studio_owner_key', $guest)->assertSessionHas('lesson_participants', ['s' => 'p']);
    }

    public function test_logout_and_workspace_reads_preserve_the_same_unclaimed_guest_proof_across_next_login(): void
    {
        $guest = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $guest]);
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->getJson('/api/studio/lessons')->assertOk();
        $this->assertSame($guest, session('studio_owner_key'));
        $document = ['id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'], 'content' => ['ru' => ['title' => 'Guest after logout']],
            'stages' => [['id' => 'stage', 'content' => ['ru' => ['title' => 'Stage']], 'blocks' => [['id' => 'text', 'type' => 'core.text', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Kept']]]]]]];
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson.id');
        $this->postJson('/api/auth/login', ['email' => 'teacher@example.test', 'password' => 'secure password 123'])->assertOk()->assertSessionHas('pending_guest_owner_key', $guest);
        User::firstOrFail()->markEmailAsVerified();
        $this->postJson('/api/account/guest-claim')->assertOk()->assertJsonPath('claim.counts.lessons', 1);
        $this->getJson('/api/studio/lessons/'.$lesson)->assertOk();
        $this->assertDatabaseCount('lesson_materials', 1);
    }

    public function test_inconsistent_two_guest_proofs_are_preserved_and_login_refuses_to_discard_either(): void
    {
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $first, 'pending_guest_owner_key' => $second]);
        $user = User::factory()->create(['password' => 'secure password 123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertConflict()
            ->assertJsonPath('error.code', 'guest_context_conflict')->assertSessionHas('studio_owner_key', $first)->assertSessionHas('pending_guest_owner_key', $second);
        $this->assertGuest();
    }

    public function test_verification_is_signed_expiring_and_bound_to_current_account(): void
    {
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        $user = User::firstOrFail();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url.'&changed=1')->assertForbidden();
        $wrong = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id + 1, 'hash' => sha1($user->email)]);
        $this->get($wrong)->assertForbidden();
        $this->get($url)->assertRedirect('/ru/account');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect('/ru/account');
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_reset_token_is_hashed_one_use_and_revokes_login_before_first_workspace_request(): void
    {
        $user = User::factory()->create(['password' => 'secure password 123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $oldHash = session('auth_password_hash');
        $known = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertAccepted()->json();
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'absent@example.test'])->assertAccepted()->json();
        $this->assertSame($known, $unknown);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
        $body = ['email' => $user->email, 'token' => $token, 'password' => 'replacement password 456', 'passwordConfirmation' => 'replacement password 456'];
        // Reset happens in a different browser. The old login has not touched workspace yet.
        Auth::guard('web')->forgetUser();
        $this->withSession([Auth::guard('web')->getName() => null, 'auth_password_hash' => null]);
        $this->postJson('/api/auth/reset-password', $body)->assertOk()->assertJsonPath('reset', true);
        $this->postJson('/api/auth/reset-password', $body)->assertUnprocessable()->assertJsonPath('error.code', 'invalid_reset');
        $this->withSession([Auth::guard('web')->getName() => $user->id, 'auth_password_hash' => $oldHash]);
        Auth::guard('web')->forgetUser();
        $this->getJson('/api/studio/lessons')->assertUnauthorized()->assertJsonPath('error.code', 'authentication_required');
        $this->assertTrue(Hash::check('replacement password 456', $user->fresh()->password));
    }

    public function test_revocation_also_covers_private_media_requests(): void
    {
        $user = User::factory()->create(['password' => 'secure password 123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $user->forceFill(['password' => 'changed password 456'])->save();
        $this->get('/media/owned/'.Str::uuid().'/'.Str::uuid())->assertUnauthorized();
    }

    public function test_password_reset_revocation_preserves_unclaimed_guest_workspace_without_account_access(): void
    {
        $guest = (string) Str::uuid();
        $participants = ['independent-session' => 'participant'];
        $this->withSession(['studio_owner_key' => $guest, 'lesson_participants' => $participants]);
        $guestLesson = $this->createLesson('Guest material');
        $user = User::factory()->create(['password' => 'secure password 123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $accountLesson = $this->createLesson('Account material');
        $this->resetInAnotherBrowser($user);

        $this->getJson('/api/studio/lessons/'.$accountLesson)->assertUnauthorized()
            ->assertJsonPath('error.code', 'authentication_required')
            ->assertSessionHas('pending_guest_owner_key', $guest)
            ->assertSessionHas('lesson_participants', $participants)
            ->assertSessionMissing('studio_owner_key')->assertSessionMissing('auth_password_hash');
        $this->assertGuest();
        $this->getJson('/api/studio/lessons/'.$guestLesson)->assertOk();
        $this->assertSame($guest, session('studio_owner_key'));
        $this->getJson('/api/studio/lessons/'.$accountLesson)->assertNotFound();
        $this->getJson('/api/studio/lessons')->assertOk()->assertJsonCount(1, 'lessons')
            ->assertJsonPath('lessons.0.id', $guestLesson);
    }

    public function test_password_reset_revocation_never_restores_claimed_guest_proof(): void
    {
        $guest = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $guest]);
        $lesson = $this->createLesson('Transferred material');
        $user = User::factory()->create(['password' => 'secure password 123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $this->postJson('/api/account/guest-claim')->assertOk();
        $this->resetInAnotherBrowser($user);
        // A stale cookie may still carry the server-issued proof from before claim.
        $this->withSession(['pending_guest_owner_key' => $guest]);

        $this->getJson('/api/studio/lessons/'.$lesson)->assertUnauthorized()
            ->assertSessionMissing('pending_guest_owner_key')->assertSessionMissing('studio_owner_key');
        $this->getJson('/api/studio/lessons/'.$lesson)->assertNotFound();
        $this->assertNotSame($guest, session('studio_owner_key'));
        $this->assertNotSame($user->fresh()->owner_key, session('studio_owner_key'));
    }

    public function test_auth_mutations_require_real_csrf_and_throttles_are_enforced(): void
    {
        $env = $this->app['env'];
        $this->app->instance('env', 'production');
        config(['mail.default' => 'sendmail']);
        try {
            $token = Str::random(40);
            $this->withSession(['_token' => $token]);
            $this->postJson('/api/auth/register', $this->registration(), ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->assertDatabaseCount('users', 0);
            $this->postJson('/api/auth/register', $this->registration(), ['Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => $token])->assertCreated();
        } finally {
            $this->app->instance('env', $env);
        }
        $this->postJson('/api/auth/verification-notification')->assertAccepted();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.2']);
        $this->postJson('/api/auth/verification-notification')->assertTooManyRequests()->assertJsonPath('error.code', 'rate_limited');
    }

    public function test_reset_expiry_and_account_profile_fields_cannot_escalate_access(): void
    {
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        $user = User::firstOrFail();
        $owner = $user->owner_key;
        $token = Password::broker()->createToken($user);
        $this->patchJson('/api/account', ['name' => 'Updated', 'uiLocale' => 'de', 'owner_key' => Str::uuid()])->assertUnprocessable();
        $this->patchJson('/api/account', ['name' => 'Updated', 'uiLocale' => 'de'])->assertOk()->assertJsonPath('user.uiLocale', 'de');
        $this->assertSame($owner, $user->fresh()->owner_key);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->travel(61)->minutes();
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'replacement password 456', 'passwordConfirmation' => 'replacement password 456'])
            ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'invalid_reset']]);
        $this->assertTrue(Hash::check('secure password 123', $user->fresh()->password));
    }

    public function test_production_log_transport_is_not_used_as_real_mail_delivery(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'secure password 123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $env = $this->app['env'];
        $this->app->instance('env', 'production');
        config(['mail.default' => 'log']);
        $csrf = session()->token();
        try {
            $this->postJson('/api/auth/verification-notification', [], ['X-CSRF-TOKEN' => $csrf])->assertStatus(503)->assertJsonPath('error.code', 'mail_unavailable');
            $this->postJson('/api/auth/forgot-password', ['email' => $user->email], ['X-CSRF-TOKEN' => $csrf])->assertAccepted();
            Notification::assertNothingSent();
        } finally {
            $this->app->instance('env', $env);
        }
    }

    private function registration(): array
    {
        return ['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'secure password 123', 'passwordConfirmation' => 'secure password 123', 'uiLocale' => 'ru'];
    }

    private function createLesson(string $title): string
    {
        $document = ['id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'], 'content' => ['ru' => ['title' => $title]],
            'stages' => [['id' => 'stage', 'content' => ['ru' => ['title' => 'Stage']], 'blocks' => [['id' => 'text', 'type' => 'core.text', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Kept']]]]]]];

        return $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson.id');
    }

    private function resetInAnotherBrowser(User $user): void
    {
        $oldHash = session('auth_password_hash');
        $token = Password::broker()->createToken($user);
        Auth::guard('web')->forgetUser();
        $this->withSession([Auth::guard('web')->getName() => null, 'auth_password_hash' => null]);
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => $token,
            'password' => 'replacement password 456', 'passwordConfirmation' => 'replacement password 456'])->assertOk();
        $this->withSession([Auth::guard('web')->getName() => $user->id, 'auth_password_hash' => $oldHash]);
        Auth::guard('web')->forgetUser();
    }
}
