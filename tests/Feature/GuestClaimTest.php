<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Account\AuthService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Application\Studio\StudioService;
use App\Models\BlockTemplateRecord;
use App\Models\GuestWorkspaceClaim;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\MediaAsset;
use App\Models\MediaOwnerQuota;
use App\Models\MediaVersion;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class GuestClaimTest extends TestCase
{
    use RefreshDatabase;

    private string $guest;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('media');
        if (! Route::has('verification.verify')) {
            require base_path('routes/account.php');
        }
        $this->guest = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $this->guest]);
    }

    public function test_verified_claim_moves_all_four_roots_without_touching_versions_files_or_runtime(): void
    {
        [$lesson, $template, $asset, $session] = $this->workspace();
        $versions = LessonVersion::query()->get()->toArray();
        $mediaVersions = MediaVersion::query()->get()->toArray();
        $files = Storage::disk('media')->allFiles();
        $bytes = (int) MediaVersion::sum('bytes');
        $before = TeachingSession::findOrFail($session['id'])->toArray();
        $answer = SessionAnswer::firstOrFail()->toArray();
        $user = $this->login();
        $this->getJson('/api/studio/lessons')->assertJsonCount(0, 'lessons');
        $this->getJson('/api/account/guest-claim')->assertOk()->assertJsonPath('claim.available', true)
            ->assertJsonPath('claim.counts', ['lessons' => 1, 'templates' => 1, 'mediaAssets' => 1, 'sessions' => 1])->assertJsonPath('claim.bytes', $bytes);
        $first = $this->postJson('/api/account/guest-claim')->assertOk()->assertJsonPath('claim.status', 'claimed')->json();
        $this->postJson('/api/account/guest-claim')->assertOk()->assertExactJson($first);
        foreach ([LessonMaterial::class, BlockTemplateRecord::class, MediaAsset::class, TeachingSession::class] as $model) {
            $this->assertSame($user->owner_key, $model::firstOrFail()->owner_key);
        }
        $this->assertSame($versions, LessonVersion::query()->get()->toArray());
        $this->assertSame($mediaVersions, MediaVersion::query()->get()->toArray());
        $this->assertSame($files, Storage::disk('media')->allFiles());
        $after = TeachingSession::findOrFail($session['id'])->toArray();
        unset($before['updated_at'], $after['updated_at']);
        $this->assertSame($before, $after); // owner_key is hidden in both snapshots.
        $this->assertSame($answer, SessionAnswer::firstOrFail()->toArray());
        $this->assertSame($bytes, MediaOwnerQuota::findOrFail($user->owner_key)->used_bytes);
        $this->assertSame(0, MediaOwnerQuota::findOrFail($this->guest)->used_bytes);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk();
        $this->getJson('/api/studio/templates/'.$template['id'])->assertOk();
        $this->get($asset['versions'][0]['url'])->assertOk();
        $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonPath('session.ownAnswers.0.optionId', 'first');
        $this->getJson('/api/account/guest-claim')->assertJsonPath('claim.status', 'claimed')->assertJsonPath('claim.available', false);
    }

    public function test_quota_overflow_and_counter_mismatch_roll_back_claim_and_preserve_guest_access(): void
    {
        [$lesson] = $this->workspace();
        $user = $this->login();
        $bytes = (int) MediaVersion::sum('bytes');
        config(['lessons.media.account_quota_bytes' => $bytes - 1]);
        $this->postJson('/api/account/guest-claim')->assertUnprocessable()->assertJsonPath('error.code', 'quota_exceeded');
        $this->assertSame($this->guest, LessonMaterial::findOrFail($lesson['id'])->owner_key);
        $this->assertDatabaseCount('guest_workspace_claims', 0);
        config(['lessons.media.account_quota_bytes' => 1073741824]);
        MediaOwnerQuota::findOrFail($this->guest)->update(['used_bytes' => $bytes + 1]);
        $this->postJson('/api/account/guest-claim')->assertStatus(503)->assertJsonPath('error.code', 'claim_failed');
        $this->assertSame($this->guest, MediaAsset::firstOrFail()->owner_key);
        $this->assertDatabaseCount('guest_workspace_claims', 0);
        $this->postJson('/api/account/guest-continue')->assertNoContent();
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk();
        $this->assertGuest();
    }

    public function test_failure_after_all_updates_rolls_back_resources_quota_and_receipt(): void
    {
        [$lesson] = $this->workspace();
        $user = $this->login();
        $bytes = (int) MediaVersion::sum('bytes');
        GuestWorkspaceClaim::creating(fn () => throw new RuntimeException('Injected final insert failure'));
        try {
            $this->postJson('/api/account/guest-claim')->assertStatus(503)->assertExactJson(['error' => ['code' => 'claim_failed']]);
        } finally {
            GuestWorkspaceClaim::flushEventListeners();
        }
        foreach ([LessonMaterial::class, BlockTemplateRecord::class, MediaAsset::class, TeachingSession::class] as $model) {
            $this->assertSame($this->guest, $model::firstOrFail()->owner_key);
        }
        $this->assertSame($bytes, MediaOwnerQuota::findOrFail($this->guest)->used_bytes);
        $this->assertNull(MediaOwnerQuota::find($user->owner_key));
        $this->assertDatabaseCount('guest_workspace_claims', 0);
        $this->assertCount(2, Storage::disk('media')->allFiles());
    }

    public function test_claimed_old_browser_cannot_read_account_or_write_or_continue_guest_alias(): void
    {
        [$lesson, , $asset, $session] = $this->workspace();
        $user = $this->login();
        $this->postJson('/api/account/guest-claim')->assertOk();
        $this->postJson('/api/auth/logout')->assertNoContent();
        // A second server cookie still has the formerly valid guest credential.
        $this->withSession(['studio_owner_key' => $this->guest, AuthService::GUEST_PROOF => $this->guest]);
        Auth::guard('web')->forgetUser();
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertNotFound();
        $this->get($asset['versions'][0]['url'])->assertNotFound();
        $this->postJson('/api/account/guest-continue')->assertConflict()->assertJsonPath('error.code', 'claim_unavailable');
        $this->assertNotSame($this->guest, session('studio_owner_key'));
        try {
            app(StudioService::class)->create($this->guest, $this->document());
            $this->fail('A write that resolved ownership before claim must be refused.');
        } catch (ApiProblem $problem) {
            $this->assertSame('identity_changed', $problem->problemCode);
        }
        $this->assertDatabaseCount('lesson_materials', 1);
        $this->getJson('/api/participation/'.$session['id'])->assertOk(); // Independent participant credential still works.
    }

    public function test_foreign_receipt_and_client_selected_source_cannot_claim_another_workspace(): void
    {
        $this->workspace();
        $this->login();
        $this->postJson('/api/account/guest-claim')->assertOk();
        $this->postJson('/api/auth/logout')->assertNoContent();
        $other = $this->login();
        $this->withSession([AuthService::GUEST_PROOF => $this->guest]);
        $this->getJson('/api/account/guest-claim')->assertJsonPath('claim.available', false)->assertJsonPath('claim.counts.lessons', 0);
        $this->postJson('/api/account/guest-claim')->assertConflict()->assertExactJson(['error' => ['code' => 'claim_unavailable']]);
        $this->postJson('/api/account/guest-claim', ['sourceOwnerKey' => $this->guest])->assertUnprocessable();
        $this->assertNotSame($other->owner_key, LessonMaterial::firstOrFail()->owner_key);
    }

    public function test_unverified_account_uses_account_upload_limit_and_owner_guard_rolls_back_on_failure(): void
    {
        $user = $this->login(false);
        config(['lessons.media.guest_quota_bytes' => 1]);
        $asset = $this->upload();
        $this->assertSame($user->owner_key, MediaAsset::findOrFail($asset['id'])->owner_key);
        $this->getJson('/api/studio/media')->assertJsonPath('quota.limitBytes', 1073741824);
        $before = MediaOwnerQuota::findOrFail($user->owner_key)->used_bytes;
        try {
            OwnerMutation::transaction([$user->owner_key], function () use ($user): void {
                MediaOwnerQuota::whereKey($user->owner_key)->update(['used_bytes' => 99]);
                throw new ApiProblem('invalid_action', 422);
            });
        } catch (ApiProblem) {
            $this->assertSame($before, MediaOwnerQuota::findOrFail($user->owner_key)->used_bytes);
        }
    }

    public function test_account_owner_cannot_be_reused_as_a_guest_or_claim_source(): void
    {
        $user = $this->login();
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->withSession(['studio_owner_key' => $user->owner_key, AuthService::GUEST_PROOF => $user->owner_key]);
        $this->getJson('/api/account')->assertOk()->assertJsonPath('user', null)->assertJsonPath('guestClaimAvailable', false);
        $this->assertNotSame($user->owner_key, session('studio_owner_key'));
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();
        $this->withSession([AuthService::GUEST_PROOF => $user->owner_key]);
        $this->getJson('/api/account/guest-claim')->assertJsonPath('claim.available', false)->assertJsonPath('claim.counts.lessons', 0);
        $this->postJson('/api/account/guest-claim')->assertConflict()->assertJsonPath('error.code', 'claim_unavailable');
    }

    private function login(bool $verified = true): User
    {
        $user = User::factory()->create(['password' => 'secure password 123', 'email_verified_at' => $verified ? now() : null]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secure password 123'])->assertOk();

        return $user->fresh();
    }

    private function workspace(): array
    {
        $asset = $this->upload();
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $this->document($asset)])->assertCreated()->json('lesson');
        $template = $this->postJson('/api/studio/templates', $this->metadata() + ['lessonId' => $lesson['id'], 'expectedLessonRevision' => 1, 'blockId' => 'image'])->assertCreated()->json('template');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 1])->assertCreated()->json('session');
        $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => 'Student'])->assertOk();
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'stage', 'blockId' => 'choice', 'optionId' => 'first'])->assertOk();
        $this->post('/api/studio/media/'.$asset['id'].'/versions', ['file' => UploadedFile::fake()->image('second.png', 8, 8), 'expectedRevision' => 1], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/studio/media/'.$asset['id'].'/archive', ['expectedRevision' => 2, 'archived' => true])->assertOk();

        return [$lesson, $template, $asset, $session];
    }

    private function upload(): array
    {
        return $this->post('/api/studio/media', array_replace($this->metadata(), ['tags' => '[]', 'file' => UploadedFile::fake()->image('source.png', 8, 8)]), ['Accept' => 'application/json'])->assertCreated()->json('asset');
    }

    private function metadata(): array
    {
        return ['title' => 'Owned', 'tags' => [], 'author' => 'Teacher', 'source' => 'Own work', 'rightsBasis' => 'self_created', 'usageRights' => 'Own lessons'];
    }

    private function document(?array $asset = null): array
    {
        $blocks = [['id' => 'choice', 'type' => 'core.single-choice', 'schemaVersion' => 1, 'content' => ['ru' => ['question' => 'Choose', 'options' => [['optionId' => 'first', 'text' => 'First'], ['optionId' => 'second', 'text' => 'Second']]]]]];
        if ($asset !== null) {
            $blocks[] = ['id' => 'image', 'type' => 'core.image', 'schemaVersion' => 1, 'content' => ['ru' => ['alt' => 'Own image']], 'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']]]];
        }

        return ['id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'], 'content' => ['ru' => ['title' => 'Own lesson']],
            'stages' => [['id' => 'stage', 'content' => ['ru' => ['title' => 'Stage']], 'blocks' => $blocks]]];
    }
}
