<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Http\Controllers\Runtime\RuntimeController;
use App\Models\MediaAsset;
use App\Models\MediaOwnerQuota;
use App\Models\MediaVersion;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        $this->owner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $this->owner]);
    }

    public function test_real_png_jpeg_and_webp_uploads_have_private_paths_and_binary_quota_limits(): void
    {
        $used = 0;
        foreach (['png' => 'image/png', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'] as $extension => $mime) {
            $image = UploadedFile::fake()->image('original.'.$extension, 8, 6);
            $bytes = file_get_contents($image->getRealPath());
            $asset = $this->upload($image);
            $version = MediaVersion::findOrFail($asset['currentVersionId']);
            $used += strlen($bytes);
            $this->assertSame($mime, $version->mime);
            $this->assertSame(8, $version->width);
            $this->assertSame(6, $version->height);
            $this->assertSame($bytes, Storage::disk('media')->get($version->storage_key));
            $this->assertSame(hash('sha256', $bytes), $version->sha256);
            $this->assertStringNotContainsString('original', $version->storage_key);
            $this->assertStringNotContainsString('storage_key', json_encode($asset, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString($version->storage_key, json_encode($asset, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('/storage/', $asset['versions'][0]['url']);
            $this->get($asset['versions'][0]['url'])->assertOk()->assertHeader('Content-Type', $mime)
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
            if (PHP_OS_FAMILY !== 'Windows') {
                $path = Storage::disk('media')->path($version->storage_key);
                $this->assertSame(0600, fileperms($path) & 0777);
                $this->assertSame(0700, fileperms(dirname($path)) & 0777);
            }
        }
        $this->getJson('/api/studio/media')->assertOk()->assertJsonCount(3, 'assets')
            ->assertJsonPath('quota.usedBytes', $used)->assertJsonPath('quota.limitBytes', 100 * 1024 * 1024)
            ->assertJsonPath('quota.maxFileBytes', 20 * 1024 * 1024);
        $this->assertSame(1024 * 1024 * 1024, config('lessons.media.account_quota_bytes'));
    }

    public function test_guest_ownership_cannot_be_spoofed_for_upload_read_update_replace_archive_or_file(): void
    {
        $asset = $this->upload();
        $this->assertSame($this->owner, MediaAsset::findOrFail($asset['id'])->owner_key);
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/media')->assertOk()->assertJsonCount(0, 'assets');
        $this->getJson('/api/studio/media/'.$asset['id'].'?owner_key='.$this->owner)->assertNotFound();
        $this->putJson('/api/studio/media/'.$asset['id'], $this->metadata() + ['expectedRevision' => 1, 'owner_key' => $this->owner])->assertNotFound();
        $this->post('/api/studio/media/'.$asset['id'].'/versions', ['file' => UploadedFile::fake()->image('other.png'), 'expectedRevision' => '1'], ['Accept' => 'application/json'])->assertNotFound();
        $this->postJson('/api/studio/media/'.$asset['id'].'/archive', ['expectedRevision' => 1, 'archived' => true])->assertNotFound();
        $this->get($asset['versions'][0]['url'])->assertNotFound();
        $this->withSession(['studio_owner_key' => $this->owner]);
        $this->get('/media/owned/'.Str::uuid().'/'.$asset['currentVersionId'])->assertNotFound();
        $this->get('/media/owned/'.$asset['id'].'/'.Str::uuid())->assertNotFound();
    }

    public function test_spoofed_html_svg_corrupt_and_header_only_images_are_rejected_before_any_write(): void
    {
        $valid = UploadedFile::fake()->image('source.png', 8, 8);
        $headerOnly = substr(file_get_contents($valid->getRealPath()), 0, 33);
        $this->assertNotFalse(getimagesizefromstring($headerOnly));
        foreach (['<html>not an image</html>', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', $headerOnly, 'not png'] as $content) {
            $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => UploadedFile::fake()->createWithContent('claimed.png', $content)], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'invalid_media']]);
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media_owner_quotas', 0);
        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_file_size_dimension_and_pixel_limits_are_server_enforced(): void
    {
        $image = UploadedFile::fake()->image('valid.png', 8, 8);
        $size = filesize($image->getRealPath());
        config(['lessons.media.max_file_bytes' => $size - 1]);
        $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => $image], ['Accept' => 'application/json'])->assertUnprocessable();
        config(['lessons.media.max_file_bytes' => $size, 'lessons.media.max_dimension' => 7]);
        $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => $image], ['Accept' => 'application/json'])->assertUnprocessable();
        config(['lessons.media.max_dimension' => 8, 'lessons.media.max_pixels' => 63]);
        $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => $image], ['Accept' => 'application/json'])->assertUnprocessable();
        config(['lessons.media.max_pixels' => 64]);
        $this->upload($image);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_quota_counts_old_and_archived_versions_and_failure_preserves_existing_files(): void
    {
        $image = UploadedFile::fake()->image('quota.png', 8, 8);
        $size = filesize($image->getRealPath());
        config(['lessons.media.guest_quota_bytes' => $size]);
        $asset = $this->upload($image);
        $before = Storage::disk('media')->allFiles();
        $this->postJson('/api/studio/media/'.$asset['id'].'/archive', ['expectedRevision' => 1, 'archived' => true])->assertOk();
        $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => $image], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'quota_exceeded']]);
        $this->post('/api/studio/media/'.$asset['id'].'/versions', ['file' => $image, 'expectedRevision' => '2'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'quota_exceeded']]);
        $this->assertSame($size, MediaOwnerQuota::findOrFail($this->owner)->used_bytes);
        $this->assertSame($before, Storage::disk('media')->allFiles());
        config(['lessons.media.guest_quota_bytes' => $size * 2]);
        $this->post('/api/studio/media/'.$asset['id'].'/versions', ['file' => $image, 'expectedRevision' => '2'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(2, 'asset.versions');
        $this->assertSame($size * 2, MediaOwnerQuota::findOrFail($this->owner)->used_bytes);
    }

    public function test_partial_storage_write_failure_rolls_back_quota_asset_and_only_its_new_file(): void
    {
        $existing = $this->upload();
        $used = MediaOwnerQuota::findOrFail($this->owner)->used_bytes;
        $disk = Storage::disk('media');
        $before = $disk->allFiles();
        $failedDisk = Mockery::mock($disk);
        $failedDisk->shouldReceive('writeStream')->once()->andReturnUsing(function ($key, $stream, $options) use ($disk): bool {
            $disk->writeStream($key, $stream, $options);

            return false;
        });
        Storage::set('media', $failedDisk);
        $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])
            ->assertStatus(503)->assertExactJson(['error' => ['code' => 'media_storage_failed']]);
        $this->assertSame($before, $disk->allFiles());
        $this->assertSame($used, MediaOwnerQuota::findOrFail($this->owner)->used_bytes);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media_versions', 1);
        $this->assertFileExists($disk->path(MediaVersion::findOrFail($existing['currentVersionId'])->storage_key));
    }

    public function test_stale_replacement_reports_revision_conflict_even_when_the_quota_is_full(): void
    {
        $image = UploadedFile::fake()->image('quota.png', 8, 8);
        $size = filesize($image->getRealPath());
        config(['lessons.media.guest_quota_bytes' => $size]);
        $asset = $this->upload($image);
        $version = MediaVersion::findOrFail($asset['currentVersionId']);
        $attributes = $version->getAttributes();
        $bytes = Storage::disk('media')->get($version->storage_key);
        $files = Storage::disk('media')->allFiles();
        $this->putJson('/api/studio/media/'.$asset['id'], $this->metadata() + ['expectedRevision' => 1])
            ->assertOk()->assertJsonPath('asset.revision', 2);
        $this->post('/api/studio/media/'.$asset['id'].'/versions', ['file' => $image, 'expectedRevision' => '1'], ['Accept' => 'application/json'])
            ->assertConflict()->assertExactJson(['error' => ['code' => 'revision_conflict']]);
        $this->assertSame(2, MediaAsset::findOrFail($asset['id'])->revision);
        $this->assertSame($asset['currentVersionId'], MediaAsset::findOrFail($asset['id'])->current_version_id);
        $this->assertSame($size, MediaOwnerQuota::findOrFail($this->owner)->used_bytes);
        $this->assertSame($attributes, $version->fresh()->getAttributes());
        $this->assertSame($bytes, Storage::disk('media')->get($version->storage_key));
        $this->assertSame($files, Storage::disk('media')->allFiles());
        $this->assertDatabaseCount('media_versions', 1);
    }

    public function test_database_failure_after_file_write_removes_file_and_rolls_back_new_owner_quota(): void
    {
        $event = 'eloquent.creating: '.MediaVersion::class;
        $this->app['events']->listen($event, fn () => throw new RuntimeException('private database detail'));
        try {
            $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])
                ->assertStatus(503)->assertExactJson(['error' => ['code' => 'media_storage_failed']]);
        } finally {
            $this->app['events']->forget($event);
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media_versions', 0);
        $this->assertDatabaseCount('media_owner_quotas', 0);
        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_replace_pins_original_bytes_and_attribution_and_metadata_revision_conflicts(): void
    {
        $asset = $this->upload();
        $old = MediaVersion::findOrFail($asset['currentVersionId']);
        $oldAttributes = $old->getAttributes();
        $oldBytes = Storage::disk('media')->get($old->storage_key);
        $metadata = $this->metadata();
        $metadata['author'] = 'New author for later versions';
        $this->putJson('/api/studio/media/'.$asset['id'], $metadata + ['expectedRevision' => 1])->assertOk()->assertJsonPath('asset.revision', 2);
        $this->putJson('/api/studio/media/'.$asset['id'], $metadata + ['expectedRevision' => 1])
            ->assertConflict()->assertExactJson(['error' => ['code' => 'revision_conflict']]);
        $this->putJson('/api/studio/media/'.$asset['id'], $metadata + ['expectedRevision' => '2'])->assertUnprocessable();
        $replacement = $this->post('/api/studio/media/'.$asset['id'].'/versions', ['file' => UploadedFile::fake()->image('new.jpeg', 9, 9), 'expectedRevision' => '2'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('asset.revision', 3)->json('asset');
        $this->assertNotSame($old->id, $replacement['currentVersionId']);
        $this->assertSame($oldAttributes, $old->fresh()->getAttributes());
        $this->assertSame($oldBytes, Storage::disk('media')->get($old->storage_key));
        $this->assertSame('Test author', $old->fresh()->attribution['author']);
        $this->assertSame('New author for later versions', MediaVersion::findOrFail($replacement['currentVersionId'])->attribution['author']);
        $this->get('/media/owned/'.$asset['id'].'/'.$old->id)->assertOk();
    }

    public function test_media_version_model_rejects_mutation(): void
    {
        $asset = $this->upload();
        $version = MediaVersion::findOrFail($asset['currentVersionId']);
        $version->bytes++;
        $this->expectException(LogicException::class);
        $version->save();
    }

    public function test_archive_restore_and_search_keep_saved_references_available(): void
    {
        $asset = $this->upload();
        $this->postJson('/api/studio/media/'.$asset['id'].'/archive', ['expectedRevision' => 1, 'archived' => true])
            ->assertOk()->assertJsonPath('asset.revision', 2)->assertJsonPath('asset.archived', true);
        $this->postJson('/api/studio/media/'.$asset['id'].'/archive', ['expectedRevision' => 2, 'archived' => true])
            ->assertOk()->assertJsonPath('asset.revision', 2);
        $this->getJson('/api/studio/media?archived=0')->assertOk()->assertJsonCount(0, 'assets');
        $this->getJson('/api/studio/media?archived=1&q=Test&tag=fixture')->assertOk()->assertJsonCount(1, 'assets')
            ->assertJsonPath('media.0.archived', true);
        $this->get($asset['versions'][0]['url'])->assertOk();
        $this->postJson('/api/studio/media/'.$asset['id'].'/archive', ['expectedRevision' => 2, 'archived' => false])->assertOk()->assertJsonPath('asset.revision', 3);
        $this->getJson('/api/studio/media?tag=missing')->assertOk()->assertJsonCount(0, 'assets');
        $this->getJson('/api/studio/media?archived=0')->assertOk()->assertJsonCount(1, 'assets');
    }

    public function test_invalid_or_missing_rights_and_duplicate_tags_are_rejected(): void
    {
        foreach (['author', 'source', 'rightsBasis', 'usageRights'] as $field) {
            $metadata = $this->multipartMetadata();
            unset($metadata[$field]);
            $this->post('/api/studio/media', $metadata + ['file' => UploadedFile::fake()->image('image.png')], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $metadata = $this->multipartMetadata();
        $metadata['tags'] = '["same","same"]';
        $this->post('/api/studio/media', $metadata + ['file' => UploadedFile::fake()->image('image.png')], ['Accept' => 'application/json'])->assertUnprocessable();
        $metadata['tags'] = '[]';
        $asset = $this->post('/api/studio/media', $metadata + ['file' => UploadedFile::fake()->image('image.png')], ['Accept' => 'application/json'])
            ->assertCreated()->json('asset');
        $this->assertSame([], $asset['tags']);
    }

    public function test_catalogue_null_owner_allows_only_builtins_and_private_exact_pair_needs_owner(): void
    {
        $asset = $this->upload();
        $catalogue = $this->app->make(MediaCatalogue::class);
        $this->assertSame('/media/owned/'.$asset['id'].'/'.$asset['currentVersionId'], $catalogue->resolve($asset['id'], $asset['currentVersionId'], $this->owner)['url']);
        $this->assertContains('builtin-conversation', array_column($catalogue->all(), 'assetId'));
        $this->assertNotContains($asset['id'], array_column($catalogue->all(), 'assetId'));
        foreach ([null, (string) Str::uuid()] as $owner) {
            try {
                $catalogue->resolve($asset['id'], $asset['currentVersionId'], $owner);
                $this->fail('Private resolution requires its owner.');
            } catch (ApiProblem $problem) {
                $this->assertSame('invalid_media', $problem->problemCode);
            }
        }
    }

    public function test_runtime_private_image_urls_and_file_access_follow_current_stage_and_pinned_version(): void
    {
        $first = $this->upload();
        $second = $this->upload(UploadedFile::fake()->image('second.jpeg'));
        [$lesson, $session] = $this->start($first, $second);
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $teacherImage = '/media/owned/'.$first['id'].'/'.$first['currentVersionId'];
        $projectorImage = '/media/projection/'.$token.'/'.$first['id'].'/'.$first['currentVersionId'];
        $this->assertSame($teacherImage, $session['document']['stages'][0]['blocks'][0]['resources']['image']);
        $this->getJson('/api/projection/'.$token)->assertOk()->assertJsonPath('session.stage.blocks.0.resources.image', $projectorImage);
        $this->get($projectorImage)->assertOk();
        $this->get('/media/projection/'.$token.'/'.$second['id'].'/'.$second['currentVersionId'])->assertNotFound();
        $this->get('/media/projection/unknown/'.$first['id'].'/'.$first['currentVersionId'])->assertNotFound();

        $this->withSession(['studio_owner_key' => (string) Str::uuid(), RuntimeController::SESSION_PARTICIPANTS_KEY => []]);
        $studentImage = '/media/participation/'.$session['id'].'/'.$first['id'].'/'.$first['currentVersionId'];
        $this->get($studentImage)->assertNotFound();
        $this->withSession([RuntimeController::SESSION_PARTICIPANTS_KEY => [$session['id'] => (string) Str::uuid()]])->get($studentImage)->assertNotFound();
        $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => 'Student'])->assertOk();
        $public = $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonPath('session.stage.blocks.0.resources.image', $studentImage)->json('session');
        $this->assertStringNotContainsString('Teacher private notes', json_encode($public, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('storage_key', json_encode($public, JSON_THROW_ON_ERROR));
        $this->get($studentImage)->assertOk();
        $this->get($teacherImage)->assertNotFound();
        $this->get('/media/participation/'.$session['id'].'/'.$second['id'].'/'.$second['currentVersionId'])->assertNotFound();

        $this->withSession(['studio_owner_key' => $this->owner]);
        $replacement = $this->post('/api/studio/media/'.$first['id'].'/versions', ['file' => UploadedFile::fake()->image('replacement.png', 12, 12), 'expectedRevision' => '1'], ['Accept' => 'application/json'])->assertOk()->json('asset');
        $this->get($studentImage)->assertOk();
        $this->get('/media/participation/'.$session['id'].'/'.$first['id'].'/'.$replacement['currentVersionId'])->assertNotFound();
        $this->get($projectorImage)->assertOk();
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->assertJsonMissingPath('lesson.document.stages.0.blocks.0.resources');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/stage', ['expectedRevision' => 1, 'stageId' => 'stage-2'])->assertOk();
        $this->get($studentImage)->assertNotFound();
        $this->get($projectorImage)->assertNotFound();
        $this->get('/media/participation/'.$session['id'].'/'.$second['id'].'/'.$second['currentVersionId'])->assertOk();
    }

    public function test_media_usage_is_owned_and_tracks_saved_and_released_version_references(): void
    {
        $asset = $this->upload();
        [$lesson] = $this->start($asset, $asset);
        $usage = $this->getJson('/api/studio/media/'.$asset['id'])->assertOk()->json('asset.usages');
        $this->assertCount(2, $usage);
        $this->assertSame($lesson['id'], $usage[0]['lessonId']);
        $this->assertSame('released', $usage[0]['status']);
        $this->assertSame($asset['currentVersionId'], $usage[0]['versionId']);
        $saved = $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->json('lesson');
        $this->postJson('/api/studio/templates', $this->metadata() + ['lessonId' => $lesson['id'], 'expectedLessonRevision' => $saved['revision'], 'blockId' => 'image-1'])
            ->assertCreated();
        $usage = $this->getJson('/api/studio/media/'.$asset['id'])->assertOk()->json('asset.usages');
        $this->assertContains('template', array_column($usage, 'kind'));
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/media/'.$asset['id'])->assertNotFound();
    }

    public function test_upload_mutation_requires_csrf_with_real_web_middleware(): void
    {
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        try {
            $this->withSession(['_token' => Str::random(40)]);
            $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => UploadedFile::fake()->image('csrf.png')],
                ['Accept' => 'application/json', 'Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->assertDatabaseCount('media_assets', 0);
            $this->assertSame([], Storage::disk('media')->allFiles());
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    private function upload(?UploadedFile $file = null): array
    {
        return $this->post('/api/studio/media', $this->multipartMetadata() + ['file' => $file ?? UploadedFile::fake()->image('original.png', 8, 8), 'owner_key' => (string) Str::uuid()], ['Accept' => 'application/json'])
            ->assertCreated()->json('asset');
    }

    private function metadata(): array
    {
        return ['title' => 'Test image', 'tags' => ['fixture'], 'author' => 'Test author', 'source' => 'Generated test fixture',
            'rightsBasis' => 'self_created', 'usageRights' => 'Test use only'];
    }

    private function multipartMetadata(): array
    {
        $metadata = $this->metadata();
        $metadata['tags'] = json_encode($metadata['tags'], JSON_THROW_ON_ERROR);

        return $metadata;
    }

    private function start(array $first, array $second): array
    {
        $document = ['id' => 'test-document', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
            'content' => ['ru' => ['title' => 'Private image lesson']], 'stages' => []];
        foreach ([$first, $second] as $index => $asset) {
            $document['stages'][] = ['id' => 'stage-'.($index + 1), 'content' => ['ru' => ['title' => 'Stage', 'notes' => 'Teacher private notes']],
                'blocks' => [['id' => 'image-'.($index + 1), 'type' => 'core.image', 'schemaVersion' => 1,
                    'content' => ['ru' => ['alt' => 'Test image']], 'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']]]]]];
        }
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 1, 'locale' => 'ru'])->assertCreated()->json('session');

        return [$lesson, $session];
    }
}
