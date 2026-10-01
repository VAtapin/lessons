<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Collaboration\TeacherAccess;
use App\Application\Collaboration\TeacherActor;
use App\Application\Collaboration\TeacherInvitations;
use App\Application\Runtime\RuntimeService;
use App\Models\TeacherGrant;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CollaborationMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_and_projector_use_exact_active_stage_media_and_revocation_closes_all_paths(): void
    {
        Storage::fake('media');
        $owner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $owner]);
        $image = UploadedFile::fake()->image('fixture.png', 8, 8);
        $asset = $this->post('/api/studio/media', ['title' => 'Private fixture', 'tags' => '[]',
            'author' => 'Test author', 'source' => 'Generated fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Tests only', 'file' => $image], ['Accept' => 'application/json'])
            ->assertCreated()->json('asset');
        $oldVersion = $asset['currentVersionId'];
        $newVersion = $this->post('/api/studio/media/'.$asset['id'].'/versions', ['expectedRevision' => 1,
            'file' => UploadedFile::fake()->image('new.png', 9, 9)], ['Accept' => 'application/json'])->assertOk()->json('asset.currentVersionId');
        $block = fn (string $id, string $version): array => ['id' => $id, 'type' => 'core.image', 'schemaVersion' => 1,
            'content' => ['ru' => ['alt' => 'Fixture', 'caption' => '']], 'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $version]]];
        $document = ['id' => 'media-collaboration', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'], 'content' => ['ru' => ['title' => 'Media fixture']],
            'stages' => [['id' => 'first', 'content' => ['ru' => ['title' => 'First']], 'blocks' => [$block('first-image', $oldVersion)]],
                ['id' => 'second', 'content' => ['ru' => ['title' => 'Second']], 'blocks' => [$block('second-image', $newVersion)]]]];
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $state = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 1])->assertCreated()->json('session');
        $session = TeachingSession::query()->findOrFail($state['id']);
        $invitation = app(TeacherInvitations::class)->create($session, []);
        $token = substr($invitation['url'], strpos($invitation['url'], '#token=') + 7);
        $accepted = app(TeacherInvitations::class)->accept($token, 'Teacher', []);
        $this->withSession(['studio_owner_key' => (string) Str::uuid(), TeacherAccess::COOKIE_KEY => [$session->id => $accepted['cookie']]]);
        $teacher = $this->getJson('/api/conduct/sessions/'.$session->id)->assertOk();
        $firstUrl = '/media/conduct/'.$session->id.'/'.$asset['id'].'/'.$oldVersion;
        $secondUrl = '/media/conduct/'.$session->id.'/'.$asset['id'].'/'.$newVersion;
        $teacher->assertJsonPath('session.document.stages.0.blocks.0.resources.image', $firstUrl)
            ->assertJsonPath('session.document.stages.1.blocks.0.resources.image', $secondUrl);
        $projection = $this->getJson('/api/conduct/sessions/'.$session->id.'/projection')->assertOk()
            ->assertJsonPath('session.stage.blocks.0.resources.image', $firstUrl);
        $this->assertStringNotContainsString($session->projector_token, $projection->getContent());
        $this->assertStringNotContainsString('/media/owned/', $teacher->getContent());
        $this->get($firstUrl)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->get($secondUrl)->assertNotFound();
        $this->get('/media/owned/'.$asset['id'].'/'.$oldVersion)->assertNotFound();
        $this->get('/media/conduct/'.$session->id.'/'.Str::uuid().'/'.$oldVersion)->assertNotFound();
        app(RuntimeService::class)->actorCommand(TeacherActor::owner($owner), $session->id, (string) Str::uuid(), 1, 'stage', ['stageId' => 'second'], [], 0);
        $this->get($firstUrl)->assertNotFound();
        $this->get($secondUrl)->assertOk();
        $grant = TeacherGrant::query()->findOrFail($accepted['grant']['id']);
        $grant->revoked_at = CarbonImmutable::now('UTC');
        $grant->save();
        $this->get($secondUrl)->assertNotFound();
        $this->getJson('/api/conduct/sessions/'.$session->id.'/projection')->assertNotFound()->assertJsonMissingPath('session');
        $this->get('/ru/conduct/'.$session->id.'/projector')->assertNotFound();
    }
}
