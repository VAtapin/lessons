<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class RehearsalTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
        Storage::fake('media');
    }

    public function test_rehearsal_snapshot_preserves_saved_draft_and_is_internal_immutable_without_invitation(): void
    {
        $lesson = $this->historyLesson();
        $before = LessonMaterial::findOrFail($lesson['id'])->getAttributes();
        $document = LessonVersion::findOrFail($lesson['versionId'])->document;
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => 1])->assertCreated()->json('session');
        $this->assertSame('rehearsal', $session['mode']);
        foreach (['joinCode', 'joinUrl', 'projectorUrl'] as $invitation) {
            $this->assertArrayNotHasKey($invitation, $session);
        }
        $this->assertSame($before, LessonMaterial::findOrFail($lesson['id'])->getAttributes());
        $this->assertSame($document, LessonVersion::findOrFail($lesson['versionId'])->document);
        $version = TeachingSession::findOrFail($session['id'])->version;
        $this->assertSame('rehearsal', $version->purpose);
        $this->assertSame('released', $version->status);
        $this->assertSame($version->id, $version->document['id']);
        $this->assertNotSame($lesson['versionId'], $version->id);
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions')->assertJsonCount(1, 'versions')->assertJsonPath('versions.0.id', $lesson['versionId']);
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions/'.$version->id)->assertNotFound();
        $lesson['document']['content']['ru']['title'] = 'Edited saved draft';
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $lesson['document']])->assertOk();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertJsonPath('session.document.content.title', 'History fixture');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/again', [])->assertConflict()->assertJsonPath('error.code', 'invalid_state');
        $this->expectException(LogicException::class);
        $version->purpose = 'authoring';
        $version->save();
    }

    public function test_only_owner_can_preview_and_submit_real_answers_with_regular_reveal_projection(): void
    {
        $lesson = $this->historyLesson();
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => 1])->assertCreated()->json('session');
        $model = TeachingSession::findOrFail($session['id']);
        $preview = SessionParticipant::where('teaching_session_id', $model->id)->firstOrFail();
        $this->assertSame(1, SessionParticipant::where('teaching_session_id', $model->id)->count());
        $this->postJson('/api/join', ['code' => $model->join_code, 'name' => 'Real pupil'])->assertNotFound();
        $this->withSession(['lesson_participants' => [$model->id => $preview->id]]);
        $this->getJson('/api/participation/'.$model->id)->assertNotFound();
        $this->postJson('/api/participation/'.$model->id.'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'a']])->assertNotFound();
        $this->getJson('/api/projection/'.$model->projector_token)->assertNotFound();
        $url = '/api/studio/rehearsals/'.$model->id;
        $before = $this->getJson($url.'/preview/student')->assertOk()->assertJsonMissingPath('session.stage.blocks.0.solution')->json('session');
        $this->assertStringNotContainsString('Authored private note', json_encode($before));
        $this->postJson($url.'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'a'], 'participantId' => $preview->id])->assertUnprocessable();
        $this->postJson($url.'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'optionId' => 'a'])->assertUnprocessable();
        $this->postJson($url.'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'a']])->assertOk()->assertJsonPath('session.ownAnswers.0.grade', null);
        $this->assertDatabaseHas('session_answers', ['teaching_session_id' => $model->id, 'session_participant_id' => $preview->id, 'block_id' => 'choice']);
        $this->historyCommand($session, 'block.close', ['blockId' => 'choice']);
        $this->historyCommand($session, 'block.reveal', ['blockId' => 'choice']);
        $this->getJson($url.'/preview/student')->assertOk()->assertJsonPath('session.ownAnswers.0.grade', true)->assertJsonPath('session.stage.blocks.0.runtime.results.optionId', 'a');
        $this->getJson($url.'/preview/projector')->assertOk()->assertJsonMissingPath('session.ownAnswers');
        $this->getJson($url.'/preview/teacher')->assertUnprocessable();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson($url.'/preview/student')->assertNotFound();
        $this->postJson($url.'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'b']])->assertNotFound();
    }

    public function test_rehearsal_private_media_is_owner_current_stage_exact_version_even_after_replacement(): void
    {
        $metadata = ['title' => 'Fixture image', 'tags' => '[]', 'author' => 'Test', 'source' => 'Test', 'rightsBasis' => 'self_created', 'usageRights' => 'Test'];
        $asset = $this->post('/api/studio/media', $metadata + ['file' => UploadedFile::fake()->image('image.png', 8, 8)], ['Accept' => 'application/json'])->assertCreated()->json('asset');
        $futureAsset = $this->post('/api/studio/media', $metadata + ['file' => UploadedFile::fake()->image('future.png', 8, 8)], ['Accept' => 'application/json'])->assertCreated()->json('asset');
        $document = $this->historyDocument();
        $image = ['id' => 'image', 'type' => 'core.image', 'schemaVersion' => 1, 'content' => ['ru' => ['alt' => 'Fixture image', 'caption' => '']], 'config' => [], 'teacherNotes' => ['ru' => ''], 'solution' => null,
            'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']]]];
        $document['stages'][0]['blocks'][] = $image;
        $future = $image;
        $future['id'] = 'future-image';
        $future['media']['image'] = ['assetId' => $futureAsset['id'], 'versionId' => $futureAsset['currentVersionId']];
        $document['stages'][1]['blocks'][] = $future;
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => 1])->assertCreated()->json('session');
        $model = TeachingSession::findOrFail($session['id']);
        $file = '/media/rehearsal/'.$model->id.'/'.$asset['id'].'/'.$asset['currentVersionId'];
        $futureFile = '/media/rehearsal/'.$model->id.'/'.$futureAsset['id'].'/'.$futureAsset['currentVersionId'];
        $this->get($futureFile)->assertNotFound();
        $response = $this->get($file)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['student', 'projector'] as $audience) {
            $this->getJson('/api/studio/rehearsals/'.$model->id.'/preview/'.$audience)->assertOk()->assertJsonPath('session.stage.blocks.4.resources.image', $file);
        }
        $replacement = $this->post('/api/studio/media/'.$asset['id'].'/versions', ['expectedRevision' => '1', 'file' => UploadedFile::fake()->image('new.png', 9, 9)], ['Accept' => 'application/json'])->assertOk()->json('asset');
        $this->get($file)->assertOk();
        $this->get('/media/rehearsal/'.$model->id.'/'.$asset['id'].'/'.$replacement['currentVersionId'])->assertNotFound();
        $this->get('/media/projection/'.$model->projector_token.'/'.$asset['id'].'/'.$asset['currentVersionId'])->assertNotFound();
        $preview = SessionParticipant::where('teaching_session_id', $model->id)->firstOrFail();
        $this->withSession(['lesson_participants' => [$model->id => $preview->id], 'studio_owner_key' => (string) Str::uuid()]);
        $this->get($file)->assertNotFound();
        $this->get('/media/participation/'.$model->id.'/'.$asset['id'].'/'.$asset['currentVersionId'])->assertNotFound();
        $this->withSession(['studio_owner_key' => $this->historyOwner]);
        $this->historyCommand($session, 'stage', ['stageId' => 'second']);
        $this->get($file)->assertNotFound();
        $this->get($futureFile)->assertOk();
        $this->historyCommand($session, 'stage', ['stageId' => 'first']);
        $this->postJson('/api/studio/rehearsals/'.$model->id.'/answers', ['stageId' => 'second', 'blockId' => 'choice', 'value' => ['optionId' => 'a']])->assertUnprocessable();
    }

    public function test_stale_revision_invalid_locale_and_foreign_owner_do_not_create_internal_versions(): void
    {
        $lesson = $this->historyLesson();
        $url = '/api/studio/lessons/'.$lesson['id'].'/rehearsals';
        $this->postJson($url, ['expectedRevision' => 99])->assertConflict();
        $this->postJson($url, ['expectedRevision' => 1, 'locale' => 'fr'])->assertUnprocessable();
        $this->postJson($url, ['expectedRevision' => 1, 'ownerKey' => $this->historyOwner])->assertUnprocessable();
        $this->assertDatabaseCount('lesson_versions', 1);
        $this->assertDatabaseCount('teaching_sessions', 0);
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->postJson($url, ['expectedRevision' => 1])->assertNotFound();
    }
}
