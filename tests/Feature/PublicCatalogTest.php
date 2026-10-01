<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Shared\ApiProblem;
use App\Application\Studio\StudioService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\CatalogEntry;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function source(): array
    {
        return require resource_path('content/kto-moi-blizhnii.php');
    }

    private function install(): CatalogEntry
    {
        return app(NeighborInstaller::class)->install()['entry'];
    }

    public function test_real_topic_installs_once_as_immutable_release_with_bilingual_strict_blocks_and_45_minute_plan(): void
    {
        $this->artisan('lessons:install-neighbor')->expectsOutput('Installed: kto-moi-blizhnii')->assertSuccessful();
        $entry = CatalogEntry::firstOrFail();
        $before = $entry->version->getAttributes();
        $this->artisan('lessons:install-neighbor')->expectsOutput('Already installed: kto-moi-blizhnii')->assertSuccessful();
        $this->assertDatabaseCount('catalog_entries', 1);
        $this->assertDatabaseCount('lesson_materials', 1);
        $this->assertDatabaseCount('lesson_versions', 1);
        $this->assertSame($before, $entry->version->fresh()->getAttributes());
        $document = LessonDocument::fromArray($entry->version->fresh()->document, app(BlockRegistry::class));
        $this->assertCount(13, $document->stages);
        $this->assertSame(2700, array_sum(array_map(fn ($stage) => $stage->config['durationSeconds'], $document->stages)));
        $this->assertSame(['ru', 'de'], $document->locales);
        $this->assertSame($this->source()['versionId'], $document->id);
        $this->assertSame(['optionId' => 'compassion'], $document->stages[4]->blocks[1]->solution);
        $this->assertSame(['itemIds' => ['notice', 'approach', 'help', 'bring', 'continue-care']], $document->stages[6]->blocks[1]->solution);
        $this->assertSame(['optionId' => 'samaritan'], $document->stages[7]->blocks[0]->solution);
        $types = [];
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                $types[$block->type] = true;
                if ($block->type === 'core.free-response' || $block->type === 'core.poll') {
                    $this->assertNull($block->solution);
                }
                foreach (['ru', 'de'] as $locale) {
                    $public = $block->project(Audience::Projector, $locale);
                    $this->assertArrayNotHasKey('teacherNotes', $public);
                    $this->assertArrayNotHasKey('solution', $public);
                    $this->assertArrayNotHasKey('origin', $public);
                }
            }
        }
        $this->assertCount(9, $types);
        $this->assertSame(100, $document->stages[11]->blocks[1]->config['maxLength']);
    }

    public function test_catalog_is_empty_until_explicit_approval_and_personal_release_stays_private(): void
    {
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'entries');
        $owner = (string) Str::uuid();
        $material = app(StudioService::class)->create($owner, $this->source()['document']);
        app(StudioService::class)->release($owner, $material->id, 1);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'entries');
        $entry = $this->install();
        $entry->update(['status' => 'pending']);
        foreach (['/api/catalog/kto-moi-blizhnii', '/api/catalog/'.$material->id, '/api/catalog/'.$entry->lesson_version_id] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->postJson('/api/catalog/kto-moi-blizhnii/use')->assertNotFound();
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'entries');
        $this->assertFalse(app(NeighborInstaller::class)->install()['created']);
        $this->assertSame('pending', $entry->fresh()->status);
        $entry->update(['status' => 'approved', 'approved_at' => null]);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'entries');
    }

    public function test_filters_use_real_translated_metadata_and_detail_never_exposes_private_fields(): void
    {
        $entry = $this->install();
        $this->getJson('/api/catalog?locale=de&q=Samariter&age=8-10&topic=mercy&audience=school&format=interactive&duration=standard')
            ->assertOk()->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.title', 'Wer ist mein Nächster?')
            ->assertJsonPath('entries.0.locales', ['ru', 'de'])->assertJsonPath('pagination.total', 1);
        foreach (['age=15%2B', 'topic=holidays', 'audience=adults', 'format=worksheet', 'duration=short', 'q=nonexistent', 'page=2'] as $filter) {
            $this->getJson('/api/catalog?'.$filter)->assertOk()->assertJsonCount(0, 'entries');
        }
        $this->getJson('/api/catalog?locale=fr')->assertUnprocessable();
        $this->getJson('/api/catalog?duration=unknown')->assertUnprocessable();
        $this->getJson('/api/catalog?page=0')->assertUnprocessable();
        foreach (['ru', 'de'] as $locale) {
            $response = $this->getJson('/api/catalog/kto-moi-blizhnii?locale='.$locale)->assertOk()
                ->assertJsonCount(13, 'entry.stages')->assertJsonPath('entry.versionId', $entry->lesson_version_id)
                ->assertJsonPath('entry.durationMinutes', 45)->assertJsonCount(3, 'entry.details.goals')
                ->assertJsonPath('preview.stages.0.blocks.0.resources.image', '/media/builtin/builtin-neighbor-road-v1');
            foreach (['teacherNotes', 'solution', 'approved_by', 'owner_key', 'source_hash', 'storage_key'] as $private) {
                $this->assertStringNotContainsString('"'.$private.'"', $response->getContent());
            }
            $this->get('/media/builtin/builtin-neighbor-road-v1')->assertOk()->assertHeader('Content-Type', 'image/webp');
        }
    }

    public function test_use_creates_independent_guest_copies_and_editing_does_not_mutate_pinned_release(): void
    {
        $entry = $this->install();
        $released = $entry->version->document;
        $owner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $owner]);
        $first = $this->postJson('/api/catalog/kto-moi-blizhnii/use', ['locale' => 'de'])->assertCreated()->json('lesson');
        $second = $this->postJson('/api/catalog/kto-moi-blizhnii/use', ['locale' => 'ru'])->assertCreated()->json('lesson');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertNotSame($first['versionId'], $second['versionId']);
        $this->assertSame('de', $first['document']['defaultLocale']);
        $this->assertSame($owner, LessonMaterial::findOrFail($first['id'])->owner_key);
        $first['document']['content']['de']['title'] = 'Eigene Bearbeitung';
        $this->putJson('/api/studio/lessons/'.$first['id'], ['expectedRevision' => 1, 'document' => $first['document']])->assertOk();
        $this->getJson('/api/studio/lessons/'.$first['id'])->assertOk()->assertJsonPath('lesson.document.content.de.title', 'Eigene Bearbeitung');
        $this->assertSame($released, $entry->version->fresh()->document);
        $this->getJson('/api/studio/lessons/'.$second['id'])->assertOk()->assertJsonPath('lesson.document.content.de.title', 'Wer ist mein Nächster?');
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/lessons/'.$first['id'])->assertNotFound();
        $this->getJson('/api/studio/lessons/'.$second['id'])->assertNotFound();
    }

    public function test_start_uses_owned_runtime_and_creates_fresh_prepared_sessions_for_guest_and_account(): void
    {
        $entry = $this->install();
        $first = $this->postJson('/api/catalog/kto-moi-blizhnii/start', ['locale' => 'de'])->assertCreated()->json();
        $second = $this->postJson('/api/catalog/kto-moi-blizhnii/start', ['locale' => 'ru'])->assertCreated()->json();
        $this->assertSame('prepared', $first['session']['status']);
        $this->assertSame('de', $first['session']['locale']);
        $this->assertNotSame($first['session']['id'], $second['session']['id']);
        $this->assertNotSame($first['session']['joinCode'], $second['session']['joinCode']);
        $this->assertNotSame($first['lesson']['versionId'], $entry->lesson_version_id);
        $this->assertDatabaseCount('session_answers', 0);
        $this->assertDatabaseCount('session_participants', 0);
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $account = $this->postJson('/api/catalog/kto-moi-blizhnii/start', ['locale' => 'ru'])->assertCreated()->json();
        $this->assertSame($user->fresh()->owner_key, LessonMaterial::findOrFail($account['lesson']['id'])->owner_key);
        $this->getJson('/api/studio/sessions/'.$first['session']['id'])->assertNotFound();
        $this->getJson('/api/studio/sessions/'.$account['session']['id'])->assertOk();
        $this->assertSame(LessonDocument::fromArray($this->source()['document'], app(BlockRegistry::class))->toArray(), $entry->version->fresh()->document);
    }

    public function test_partial_or_private_media_releases_cannot_be_approved_or_read_from_catalog(): void
    {
        Storage::fake('media');
        $owner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $owner]);
        $asset = $this->post('/api/studio/media', ['file' => UploadedFile::fake()->image('private.png', 8, 8),
            'title' => 'Private', 'tags' => '[]', 'author' => 'Test', 'source' => 'Fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Private'], ['Accept' => 'application/json'])
            ->assertCreated()->json('asset');
        $document = $this->source()['document'];
        $document['stages'][0]['blocks'][0]['media']['image'] = ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']];
        $material = app(StudioService::class)->create($owner, $document);
        $version = app(StudioService::class)->release($owner, $material->id, 1);
        try {
            app(CatalogService::class)->approve($version, $this->source()['metadata'], 'reviewer', 'private-media');
            $this->fail('Private media approval must be rejected.');
        } catch (ApiProblem $problem) {
            $this->assertSame('invalid_media', $problem->problemCode);
        }
        // Even an incorrectly inserted approval cannot make a private file public.
        CatalogEntry::create(['slug' => 'private-media', 'lesson_version_id' => $version->id, 'metadata' => $this->source()['metadata'], 'status' => 'approved', 'approved_at' => now(), 'approved_by' => 'reviewer']);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'entries');
        $this->getJson('/api/catalog/private-media')->assertNotFound();
        $this->postJson('/api/catalog/private-media/start')->assertNotFound();
        $other = app(StudioService::class)->create($owner, $this->source()['document']);
        try {
            app(CatalogService::class)->approve($other->currentVersion, $this->source()['metadata'], 'reviewer', 'draft');
            $this->fail('Draft approval must be rejected.');
        } catch (ApiProblem $problem) {
            $this->assertSame('invalid_catalog_entry', $problem->problemCode);
        }
        $partial = $this->source()['document'];
        $partial['content']['de']['title'] = '';
        $this->postJson('/api/studio/lessons', ['document' => $partial])->assertUnprocessable();
    }

    public function test_catalog_mutations_require_actual_csrf_and_share_existing_write_throttle(): void
    {
        $this->install();
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        try {
            $token = Str::random(40);
            $this->withSession(['_token' => $token]);
            foreach (['use', 'start'] as $action) {
                $this->postJson('/api/catalog/kto-moi-blizhnii/'.$action, [], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            }
            $this->assertDatabaseCount('lesson_materials', 1);
            $this->assertDatabaseCount('teaching_sessions', 0);
            $this->postJson('/api/catalog/kto-moi-blizhnii/use', [], ['Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => $token])->assertCreated()->assertHeader('X-RateLimit-Limit', '60');
        } finally {
            $this->app->instance('env', $environment);
        }
        $key = 'catalog-test-'.Str::uuid();
        RateLimiter::for('studio-write', fn () => Limit::perMinute(1)->by($key));
        $this->postJson('/api/catalog/kto-moi-blizhnii/use')->assertCreated();
        $this->postJson('/api/catalog/kto-moi-blizhnii/start')->assertTooManyRequests();
        $this->assertDatabaseCount('teaching_sessions', 0);
    }

    public function test_source_receipt_and_stable_identifiers_prevent_overwrite_and_pinned_release_cannot_change(): void
    {
        $entry = $this->install();
        $before = $entry->version->getAttributes();
        // Deliberately tamper through SQL to model stale receipts, outside the immutable model API.
        DB::table('catalog_entries')->where('id', $entry->id)->update(['source_hash' => str_repeat('0', 64)]);
        $this->artisan('lessons:install-neighbor')->assertFailed();
        $this->assertSame($before, $entry->version->fresh()->getAttributes());
        $entry->refresh();
        try {
            $entry->lesson_version_id = (string) Str::uuid();
            $entry->save();
            $this->fail('Pinned catalog version must be immutable.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('immutable', $error->getMessage());
        }
    }

    public function test_install_collision_does_not_overwrite_user_material_or_create_partial_content(): void
    {
        $source = $this->source();
        $owner = (string) Str::uuid();
        $material = new LessonMaterial(['owner_key' => $owner, 'revision' => 17]);
        $material->id = $source['materialId'];
        $material->save();
        try {
            app(NeighborInstaller::class)->install();
            $this->fail('Stable ID collision must block installation.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('No resources were overwritten', $error->getMessage());
        }
        $this->assertSame($owner, $material->fresh()->owner_key);
        $this->assertSame(17, $material->fresh()->revision);
        $this->assertDatabaseCount('catalog_entries', 0);
        $this->assertDatabaseCount('lesson_versions', 0);
    }

    public function test_all_eight_approved_illustrations_are_reusable_builtin_exact_byte_copies_with_honest_rights(): void
    {
        foreach (['road', 'wounded', 'priest', 'levite', 'samaritan', 'newcomer', 'books', 'game'] as $scene) {
            $entry = collect(config('media_builtin'))->firstWhere('assetId', 'builtin-neighbor-'.$scene);
            $this->assertNotNull($entry);
            $this->assertSame('permission', $entry['attribution']['rightsBasis']);
            $this->assertSame(hash_file('sha256', base_path('OLD/kto-moi-blizhnii/assets/scene-'.$scene.'.webp')), hash_file('sha256', base_path($entry['file'])));
            $this->get('/media/builtin/'.$entry['versionId'])->assertOk()->assertHeader('Content-Type', 'image/webp');
        }
    }
}
