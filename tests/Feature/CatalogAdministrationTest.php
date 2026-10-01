<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Account\AccountIdentity;
use App\Application\Catalog\CatalogReviewService;
use App\Application\Studio\StudioService;
use App\Models\CatalogEntry;
use App\Models\CatalogSubmission;
use App\Models\LessonVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CatalogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $admin = false, bool $verified = true): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
        $user->forceFill(['is_admin' => $admin])->save();
        AccountIdentity::owner($user);

        return $user;
    }

    private function identity(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
    }

    private function proposal(User $author, string $slug = 'reviewed-neighbor'): array
    {
        $source = require resource_path('content/kto-moi-blizhnii.php');
        $material = app(StudioService::class)->create(AccountIdentity::owner($author), $source['document']);
        $version = app(StudioService::class)->release($author->owner_key, $material->id, 1);

        return ['lessonId' => $material->id, 'versionId' => $version->id, 'expectedLessonRevision' => $material->fresh()->revision, 'slug' => $slug, 'metadata' => $source['metadata']];
    }

    public function test_guests_normal_accounts_unverified_admins_and_revoked_sessions_cannot_administer(): void
    {
        $this->getJson('/api/admin')->assertUnauthorized();
        $this->postJson('/api/studio/catalog/submissions', [])->assertUnauthorized();
        foreach ([$this->user(), $this->user(true, false)] as $user) {
            $this->identity($user);
            foreach (['/api/admin', '/api/admin/submissions', '/api/admin/taxonomy', '/api/admin/templates', '/api/admin/catalog'] as $url) {
                $this->getJson($url)->assertForbidden();
            }
            $this->postJson('/api/admin/submissions/'.Str::uuid().'/review', ['is_admin' => true])->assertForbidden();
        }
        $admin = $this->user(true);
        $this->identity($admin);
        $this->getJson('/api/admin')->assertOk()->assertJsonPath('admin', true);
        $admin->forceFill(['is_admin' => false])->save();
        $this->getJson('/api/admin')->assertForbidden();
        $admin->forceFill(['is_admin' => true, 'password' => 'new unrelated hash'])->save();
        $this->getJson('/api/admin')->assertUnauthorized();
    }

    public function test_explicit_cli_grant_requires_existing_verified_account_and_public_input_cannot_grant_admin(): void
    {
        $ordinary = $this->user();
        $unverified = $this->user(false, false);
        $this->artisan('lessons:grant-admin', ['email' => 'missing-admin@example.test'])->assertFailed();
        $this->artisan('lessons:grant-admin', ['email' => $unverified->email])->assertFailed();
        $this->assertFalse((bool) $unverified->fresh()->is_admin);
        $ordinary->fill(['is_admin' => true])->save();
        $this->assertFalse((bool) $ordinary->fresh()->is_admin);
        $this->identity($ordinary);
        $this->patchJson('/api/account', ['name' => 'Author', 'uiLocale' => 'ru', 'is_admin' => true])->assertUnprocessable();
        $this->assertFalse((bool) $ordinary->fresh()->is_admin);
        $this->artisan('lessons:grant-admin', ['email' => $ordinary->email])->assertSuccessful();
        $this->getJson('/api/admin')->assertOk();
        $this->artisan('lessons:grant-admin', ['email' => $ordinary->email, '--revoke' => true])->assertSuccessful();
        $this->getJson('/api/admin')->assertForbidden();
        $this->assertDatabaseCount('users', 2);
    }

    public function test_owner_submits_exact_release_and_admin_approval_does_not_follow_new_author_draft(): void
    {
        $author = $this->user();
        $admin = $this->user(true);
        $body = $this->proposal($author);
        $pinned = LessonVersion::findOrFail($body['versionId'])->document;
        $this->identity($author);
        $submission = $this->postJson('/api/studio/catalog/submissions', $body)->assertCreated()->assertJsonPath('submission.status', 'pending')->json('submission');
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'entries');
        $this->postJson('/api/studio/catalog/submissions', $body)->assertCreated()->assertJsonPath('submission.id', $submission['id']);
        $this->assertDatabaseCount('catalog_submissions', 1);
        $changed = $pinned;
        $changed['content']['ru']['title'] = 'New private draft';
        app(StudioService::class)->save($author->owner_key, $body['lessonId'], $body['expectedLessonRevision'], $changed);
        $this->identity($admin);
        $this->getJson('/api/admin/submissions/'.$submission['id'])->assertOk()->assertJsonPath('document.id', $body['versionId'])->assertJsonPath('document.content.ru.title', 'Кто мой ближний?');
        $this->postJson('/api/admin/submissions/'.$submission['id'].'/review', ['expectedRevision' => 1, 'decision' => 'approve'])->assertOk()->assertJsonPath('submission.status', 'approved')->assertJsonPath('submission.catalogSlug', $body['slug']);
        $this->getJson('/api/catalog/'.$body['slug'])->assertOk()->assertJsonPath('entry.versionId', $body['versionId']);
        $this->assertSame($pinned, LessonVersion::findOrFail($body['versionId'])->document);
        $this->identity($author);
        $this->getJson('/api/studio/catalog/submissions')->assertOk()->assertJsonPath('submissions.0.status', 'approved');
        $newVersion = app(StudioService::class)->release($author->owner_key, $body['lessonId'], $body['expectedLessonRevision'] + 1);
        $this->assertNotSame($body['versionId'], $newVersion->id);
        $this->assertDatabaseCount('catalog_entries', 1);
        $this->getJson('/api/catalog/'.$body['slug'])->assertOk()->assertJsonPath('entry.versionId', $body['versionId']);
    }

    public function test_cross_owner_wrong_version_stale_revision_and_draft_submissions_fail_without_publication(): void
    {
        $author = $this->user();
        $other = $this->user();
        $body = $this->proposal($author);
        $this->identity($other);
        $this->postJson('/api/studio/catalog/submissions', $body)->assertNotFound();
        $otherBody = $this->proposal($other, 'other-release');
        $this->identity($author);
        $this->postJson('/api/studio/catalog/submissions', array_replace($body, ['versionId' => $otherBody['versionId']]))->assertNotFound();
        $this->postJson('/api/studio/catalog/submissions', array_replace($body, ['expectedLessonRevision' => 999]))->assertConflict();
        foreach (['taxonomy', 'templates'] as $reserved) {
            $this->postJson('/api/studio/catalog/submissions', array_replace($body, ['slug' => $reserved]))->assertUnprocessable();
        }
        $draft = app(StudioService::class)->create($author->owner_key, (require resource_path('content/kto-moi-blizhnii.php'))['document']);
        $this->postJson('/api/studio/catalog/submissions', array_replace($body, ['lessonId' => $draft->id, 'versionId' => $draft->current_version_id, 'expectedLessonRevision' => 1]))->assertUnprocessable();
        $this->identity($this->user(false, false));
        $this->getJson('/api/studio/catalog/submissions')->assertForbidden();
        $this->assertDatabaseCount('catalog_submissions', 0);
        $this->assertDatabaseCount('catalog_entries', 0);
    }

    public function test_return_reason_is_author_only_and_admin_reviews_require_current_submission_revision(): void
    {
        $author = $this->user();
        $admin = $this->user(true);
        $body = $this->proposal($author);
        $this->identity($author);
        $submission = $this->postJson('/api/studio/catalog/submissions', $body)->assertCreated()->json('submission');
        $this->identity($admin);
        $url = '/api/admin/submissions/'.$submission['id'].'/review';
        $this->postJson($url, ['expectedRevision' => 1, 'decision' => 'return'])->assertUnprocessable();
        $this->postJson($url, ['expectedRevision' => 2, 'decision' => 'approve'])->assertConflict();
        $this->postJson($url, ['expectedRevision' => 1, 'decision' => 'return', 'reason' => 'Уточните инструкции / Bitte die Anleitung präzisieren.'])->assertOk()->assertJsonPath('submission.status', 'returned');
        $this->postJson($url, ['expectedRevision' => 1, 'decision' => 'approve'])->assertConflict();
        $this->postJson($url, ['expectedRevision' => 2, 'decision' => 'approve'])->assertConflict();
        $this->identity($author);
        $this->getJson('/api/studio/catalog/submissions')->assertOk()->assertJsonPath('submissions.0.reason', 'Уточните инструкции / Bitte die Anleitung präzisieren.');
        $this->identity($this->user());
        $this->getJson('/api/studio/catalog/submissions')->assertOk()->assertJsonCount(0, 'submissions');
        $this->getJson('/api/admin/submissions/'.$submission['id'])->assertForbidden();
        $this->getJson('/api/catalog/'.$body['slug'])->assertNotFound();
        $this->assertDatabaseCount('catalog_entries', 0);
    }

    public function test_duplicate_public_slug_requires_separate_new_slug_and_visibility_keeps_old_sessions(): void
    {
        $author = $this->user();
        $admin = $this->user(true);
        $body = $this->proposal($author);
        $submission = app(CatalogReviewService::class)->submit($author, $body['lessonId'], $body['versionId'], $body['expectedLessonRevision'], $body['slug'], $body['metadata']);
        app(CatalogReviewService::class)->review($admin, $submission['id'], 1, 'approve', null);
        $this->identity($author);
        $start = $this->postJson('/api/catalog/'.$body['slug'].'/start')->assertCreated()->json('session');
        $this->postJson('/api/studio/catalog/submissions', $body)->assertConflict()->assertJsonPath('error.code', 'catalog_slug_conflict');
        $this->postJson('/api/studio/catalog/submissions', array_replace($body, ['slug' => 'new-approved-slug']))->assertCreated();
        $this->identity($admin);
        $this->postJson('/api/admin/catalog/'.$body['slug'].'/visibility', ['expectedRevision' => 1, 'visible' => false])->assertOk()->assertJsonPath('entry.status', 'retracted');
        $this->getJson('/api/catalog/'.$body['slug'])->assertNotFound();
        $this->postJson('/api/catalog/'.$body['slug'].'/start')->assertNotFound();
        $this->postJson('/api/admin/catalog/'.$body['slug'].'/visibility', ['expectedRevision' => 1, 'visible' => true])->assertConflict();
        $this->postJson('/api/admin/catalog/'.$body['slug'].'/visibility', ['expectedRevision' => 2, 'visible' => true])->assertOk();
        $this->getJson('/api/catalog/'.$body['slug'])->assertOk();
        $this->identity($author);
        $this->getJson('/api/studio/sessions/'.$start['id'])->assertOk();
        $this->assertSame($body['versionId'], CatalogEntry::firstOrFail()->lesson_version_id);
    }

    public function test_taxonomy_edits_are_bilingual_persisted_and_optimistic_with_stable_keys(): void
    {
        $admin = $this->user(true);
        $this->identity($admin);
        $body = ['kind' => 'topic', 'key' => 'hope', 'labels' => ['ru' => 'Надежда', 'de' => 'Hoffnung'], 'active' => true];
        $term = $this->postJson('/api/admin/taxonomy', $body)->assertCreated()->json('term');
        $terms = $this->getJson('/api/catalog/taxonomy?locale=de')->assertOk()->json('terms');
        $this->assertSame('Hoffnung', collect($terms)->firstWhere('key', 'hope')['label']);
        $this->putJson('/api/admin/taxonomy/'.$term['id'], $body + ['expectedRevision' => 2])->assertConflict();
        $this->putJson('/api/admin/taxonomy/'.$term['id'], array_replace($body, ['labels' => ['ru' => 'Надежда']]) + ['expectedRevision' => 1])->assertUnprocessable();
        $this->putJson('/api/admin/taxonomy/'.$term['id'], array_replace($body, ['key' => 'renamed']) + ['expectedRevision' => 1])->assertUnprocessable();
        $this->putJson('/api/admin/taxonomy/'.$term['id'], array_replace($body, ['active' => false]) + ['expectedRevision' => 1])->assertOk();
        $this->assertNotContains('hope', array_column($this->getJson('/api/catalog/taxonomy')->assertOk()->json('terms'), 'key'));
        $this->getJson('/api/admin/taxonomy')->assertOk()->assertJsonFragment(['key' => 'hope', 'active' => false]);
    }

    public function test_admin_and_author_mutations_require_real_csrf_and_snapshot_metadata_is_immutable(): void
    {
        $author = $this->user();
        $this->identity($author);
        $body = $this->proposal($author);
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        try {
            $token = Str::random(40);
            $this->withSession(['_token' => $token]);
            $this->postJson('/api/studio/catalog/submissions', $body, ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->assertDatabaseCount('catalog_submissions', 0);
            $submission = $this->postJson('/api/studio/catalog/submissions', $body, ['Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => $token])->assertCreated()->json('submission');
            $this->identity($this->user(true));
            $this->withSession(['_token' => $token]);
            $this->postJson('/api/admin/submissions/'.$submission['id'].'/review', ['expectedRevision' => 1, 'decision' => 'approve'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->assertDatabaseCount('catalog_entries', 0);
        } finally {
            $this->app->instance('env', $environment);
        }
        $this->assertSame('pending', CatalogSubmission::firstOrFail()->status);
    }
}
