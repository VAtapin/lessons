<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class LessonTrashTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
    }

    public function test_only_the_selected_copy_is_trashed_and_restoration_preserves_its_document_and_favorite(): void
    {
        $first = $this->historyLesson();
        $second = $this->historyLesson();
        $this->assertNotSame($first['id'], $second['id']);
        $this->postJson('/api/studio/lessons/'.$first['id'].'/favorite', ['favorite' => true])->assertOk();
        $before = LessonVersion::query()->get()->toArray();
        $this->archive($first['id'], 1, true)->assertOk()->assertJsonPath('lesson.archived', true)->assertJsonPath('lesson.revision', 2);
        $this->getJson('/api/studio/lessons')->assertOk()->assertJsonCount(1, 'lessons')->assertJsonPath('lessons.0.id', $second['id']);
        $this->getJson('/api/studio/lessons?archived=1')->assertOk()->assertJsonCount(1, 'lessons')->assertJsonPath('lessons.0.id', $first['id'])->assertJsonPath('lessons.0.favorite', true);
        $this->getJson('/api/studio/lessons/'.$first['id'])->assertConflict()->assertJsonPath('error.code', 'lesson_in_trash');
        $this->archive($first['id'], 2, false)->assertOk()->assertJsonPath('lesson.archived', false)->assertJsonPath('lesson.revision', 3);
        $this->getJson('/api/studio/lessons/'.$first['id'])->assertOk()->assertJsonPath('lesson.versionId', $first['versionId'])->assertJsonPath('lesson.document', $first['document']);
        $this->getJson('/api/studio/lessons?archived=1')->assertOk()->assertExactJson(['lessons' => []]);
        $this->assertSame($before, LessonVersion::query()->get()->toArray());
        $this->assertDatabaseCount('lesson_materials', 2);
    }

    public function test_trash_and_restore_reject_stale_revisions_and_a_foreign_owner(): void
    {
        $lesson = $this->historyLesson();
        $this->archive($lesson['id'], 99, true)->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $this->assertFalse(LessonMaterial::findOrFail($lesson['id'])->archived);
        $this->archive($lesson['id'], 1, true)->assertOk();
        $this->archive($lesson['id'], 1, false)->assertConflict();
        $this->archive($lesson['id'], 2, true)->assertOk()->assertJsonPath('lesson.revision', 2);
        $this->archive($lesson['id'], 2, false)->assertOk();
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $lesson['document']])->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 1])->assertConflict();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->archive($lesson['id'], 3, true)->assertNotFound();
        $this->archive($lesson['id'], 3, false)->assertNotFound();
        $this->getJson('/api/studio/lessons?archived=1')->assertExactJson(['lessons' => []]);
        $this->assertSame(3, LessonMaterial::findOrFail($lesson['id'])->revision);
    }

    public function test_archive_requires_a_strict_envelope_and_filters_are_validated(): void
    {
        $lesson = $this->historyLesson();
        $path = '/api/studio/lessons/'.$lesson['id'].'/archive';
        foreach ([[], ['expectedRevision' => 1, 'archived' => 'true'], ['expectedRevision' => '1', 'archived' => true], ['expectedRevision' => 0, 'archived' => true], ['expectedRevision' => 1, 'archived' => true, 'owner_key' => $this->historyOwner]] as $body) {
            $this->postJson($path, $body)->assertUnprocessable();
        }
        $this->getJson('/api/studio/lessons?archived=invalid')->assertUnprocessable();
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertFalse(LessonMaterial::findOrFail($lesson['id'])->archived);
    }

    public function test_existing_class_and_history_survive_trash_but_new_runs_and_rehearsals_are_blocked(): void
    {
        $session = $this->historySession();
        $this->historyParticipant($session);
        $version = LessonVersion::findOrFail($session['document']['id']);
        $version->load('material'); // Deliberately retain a stale, non-archived relation.
        $material = $version->material;
        $before = $version->document;
        $this->archive($material->id, $material->revision, true)->assertOk();
        foreach (['/api/studio/lessons/'.$material->id.'/release', '/api/studio/lessons/'.$material->id.'/sessions', '/api/studio/lessons/'.$material->id.'/rehearsals'] as $path) {
            $this->postJson($path, ['expectedRevision' => $material->revision])->assertConflict()->assertJsonPath('error.code', 'lesson_in_trash');
        }
        $this->putJson('/api/studio/lessons/'.$material->id, ['expectedRevision' => $material->revision, 'document' => $before])->assertConflict()->assertJsonPath('error.code', 'lesson_in_trash');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/again', [])->assertConflict()->assertJsonPath('error.code', 'lesson_in_trash');
        try {
            app(RuntimeService::class)->startSnapshot($this->historyOwner, $version, 'ru', 'lesson');
            $this->fail('A stale material relation must not bypass the trash guard.');
        } catch (ApiProblem $problem) {
            $this->assertSame('lesson_in_trash', $problem->problemCode);
        }
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk();
        $this->getJson('/api/projection/'.basename($session['projectorUrl']))->assertOk();
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'a']])->assertOk();
        $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonPath('session.ownAnswers.0.optionId', 'a');
        $this->historyCommand($session, 'pause');
        $this->historyCommand($session, 'resume');
        $this->historyCommand($session, 'finish');
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()->assertJsonPath('history.lessonArchived', true)->assertJsonCount(1, 'history.answers');
        $this->getJson('/api/studio/sessions')->assertOk()->assertJsonPath('sessions.0.lessonArchived', true);
        $this->getJson('/api/studio/lessons/'.$material->id.'/versions/'.$version->id)->assertOk()->assertJsonPath('version.document', $before);
        $this->assertDatabaseCount('teaching_sessions', 1);
        $this->assertDatabaseCount('lesson_versions', 1);
        $this->assertDatabaseCount('session_answers', 1);
        $this->assertSame($before, $version->fresh()->document);
        $this->archive($material->id, $material->revision + 1, false)->assertOk();
        $this->postJson('/api/studio/sessions/'.$session['id'].'/again', [])->assertCreated();
    }

    public function test_an_old_acknowledged_editor_save_cannot_untrash_or_overwrite_the_copy(): void
    {
        $lesson = $this->historyLesson();
        $save = ['saveId' => (string) Str::uuid(), 'expectedRevision' => 1, 'document' => $lesson['document']];
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $save)->assertOk()->assertJsonPath('lesson.revision', 2);
        $before = LessonVersion::findOrFail($lesson['versionId'])->toArray();
        $this->archive($lesson['id'], 2, true)->assertOk();
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $save)->assertConflict()->assertJsonPath('error.code', 'lesson_in_trash');
        $this->assertTrue(LessonMaterial::findOrFail($lesson['id'])->archived);
        $this->assertSame(3, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertSame($before, LessonVersion::findOrFail($lesson['versionId'])->toArray());
        $this->assertDatabaseCount('lesson_save_receipts', 1);
    }

    private function archive(string $id, int $revision, bool $archived): TestResponse
    {
        return $this->postJson('/api/studio/lessons/'.$id.'/archive', ['expectedRevision' => $revision, 'archived' => $archived]);
    }
}
