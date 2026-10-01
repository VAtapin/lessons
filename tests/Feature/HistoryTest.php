<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\History\HistoryService;
use App\Application\Runtime\RuntimeService;
use App\Models\LessonMaterial;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class HistoryTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_favorites_are_strict_idempotent_and_versions_are_exact_owned_authoring_snapshots(): void
    {
        $lesson = $this->historyLesson();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/favorite', ['favorite' => true])->assertOk()->assertExactJson(['lessonId' => $lesson['id'], 'favorite' => true]);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/favorite', ['favorite' => true])->assertOk();
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->getJson('/api/studio/lessons')->assertJsonPath('lessons.0.favorite', true);
        foreach ([1, 'true', null] as $invalid) {
            $this->postJson('/api/studio/lessons/'.$lesson['id'].'/favorite', ['favorite' => $invalid])->assertUnprocessable();
        }
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/favorite', ['favorite' => false, 'ownerKey' => $this->historyOwner])->assertUnprocessable();
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 1])->assertCreated()->json('session');
        $lesson = $this->getJson('/api/studio/lessons/'.$lesson['id'])->json('lesson');
        $lesson['document']['content']['ru']['title'] = 'New draft title';
        $new = $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => $lesson['revision'], 'document' => $lesson['document']])->assertOk()->json('lesson');
        $versions = $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions')->assertOk()->json('versions');
        $this->assertCount(2, $versions);
        $this->assertEqualsCanonicalizing(['authoring', 'authoring'], array_column($versions, 'purpose'));
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions/'.$session['document']['id'])->assertOk()->assertJsonPath('version.document.content.ru.title', 'History fixture');
        $other = $this->historyLesson();
        $this->getJson('/api/studio/lessons/'.$other['id'].'/versions/'.$new['versionId'])->assertNotFound();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions')->assertNotFound();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/favorite', ['favorite' => true])->assertNotFound();
    }

    public function test_lifecycle_anchors_visited_stages_and_anonymous_finish_snapshot_survive_receipt_replay(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00 UTC');
        $session = $this->historySession(true);
        $history = $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()->json('history');
        $this->assertNull($history['startedAt']);
        $this->assertSame([], $history['visitedStageIds']);
        $this->historyCommand($session, 'begin');
        $started = TeachingSession::findOrFail($session['id'])->started_at->toISOString();
        $this->historyParticipant($session);
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'a']])->assertOk();
        $this->historyCommand($session, 'block.open', ['blockId' => 'free']);
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'free', 'value' => ['text' => 'Private free answer']])->assertOk();
        foreach ([['roles', ['roleId' => 'a']], ['signals', ['ready' => true, 'question' => true]]] as [$block, $value]) {
            $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => $block, 'value' => $value])->assertOk();
        }
        $this->historyCommand($session, 'pause');
        CarbonImmutable::setTestNow('2026-10-01 11:00:00 UTC');
        $this->historyCommand($session, 'resume');
        $this->historyCommand($session, 'stage', ['stageId' => 'second']);
        $body = $this->historyCommand($session, 'finish');
        $snapshot = TeachingSession::findOrFail($session['id']);
        $this->assertSame($started, $snapshot->started_at->toISOString());
        $this->assertSame(['first', 'second'], $snapshot->visited_stage_ids);
        $this->assertSame('2026-10-01T11:00:00.000000Z', $snapshot->finished_at->toISOString());
        $aggregates = $snapshot->final_aggregates;
        $this->assertSame(1, $aggregates[0]['correctCount']);
        $this->assertSame(0, $aggregates[1]['gradedCount']);
        $this->assertSame([['roleId' => 'a', 'count' => 1]], $aggregates[2]['roles']);
        $this->assertSame(['readyCount' => 1, 'questionCount' => 1], $aggregates[3]['signals']);
        foreach (['Private pupil name', 'Private free answer', 'participantId', 'displayText'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($aggregates));
        }
        CarbonImmutable::setTestNow('2026-10-02 10:00:00 UTC');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', $body)->assertOk();
        $this->assertSame($aggregates, $snapshot->fresh()->final_aggregates);
        $this->assertSame($snapshot->finished_at->toISOString(), $snapshot->fresh()->finished_at->toISOString());
    }

    public function test_finished_history_notes_have_revision_conflicts_and_do_not_modify_authored_document(): void
    {
        $session = $this->historySession();
        $this->historyCommand($session, 'finish');
        $snapshot = TeachingSession::findOrFail($session['id'])->version->document;
        $url = '/api/studio/sessions/'.$session['id'].'/history';
        $this->patchJson($url, ['expectedRevision' => $session['revision'], 'teacherNotes' => "  Session note\n"])->assertOk()->assertJsonPath('history.teacherNotes', "  Session note\n");
        $this->patchJson($url, ['expectedRevision' => $session['revision'], 'teacherNotes' => 'Stale'])->assertConflict()->assertJsonPath('error.code', 'revision_conflict')->assertJsonPath('history.teacherNotes', "  Session note\n");
        $this->patchJson($url, ['expectedRevision' => $session['revision'] + 1, 'teacherNotes' => ''])->assertOk()->assertJsonPath('history.teacherNotes', '');
        $this->assertSame($snapshot, TeachingSession::findOrFail($session['id'])->version->document);
        $this->patchJson($url, ['expectedRevision' => 4, 'teacherNotes' => str_repeat('a', 5001)])->assertUnprocessable();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->patchJson($url, ['expectedRevision' => 4, 'teacherNotes' => 'Foreign'])->assertNotFound();
    }

    public function test_history_pagination_is_owner_scoped_stable_and_query_count_does_not_grow_per_session(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
        $first = $this->historySession();
        $runtime = $this->app->make(RuntimeService::class);
        $version = TeachingSession::findOrFail($first['id'])->version;
        $counts = [];
        foreach ([2, 33] as $target) {
            while (TeachingSession::query()->where('owner_key', $this->historyOwner)->count() < $target) {
                $runtime->startSnapshot($this->historyOwner, $version, 'ru', 'lesson');
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $page = $this->app->make(HistoryService::class)->list($this->historyOwner, []);
                $counts[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(4, $counts[1]);
        $this->assertCount(30, $page['sessions']);
        $second = $this->getJson('/api/studio/sessions?cursor='.rawurlencode($page['nextCursor']))->assertOk()->json();
        $this->assertCount(3, $second['sessions']);
        $this->assertNull($second['nextCursor']);
        $this->assertCount(33, array_unique([...array_column($page['sessions'], 'id'), ...array_column($second['sessions'], 'id')]));
        $this->getJson('/api/studio/sessions?mode=rehearsal&cursor='.rawurlencode($page['nextCursor']))->assertUnprocessable();
        $this->getJson('/api/studio/sessions?cursor=not-a-cursor')->assertUnprocessable();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/sessions')->assertOk()->assertExactJson(['sessions' => [], 'nextCursor' => null]);
        $this->getJson('/api/studio/sessions?cursor='.rawurlencode($page['nextCursor']))->assertUnprocessable();
        $this->getJson('/api/studio/sessions/'.$first['id'].'/history')->assertNotFound();
    }

    public function test_again_uses_same_source_version_with_new_invitation_and_clean_runtime(): void
    {
        $session = $this->historySession();
        $this->historyParticipant($session);
        $this->historyCommand($session, 'timer.start', ['seconds' => 90]);
        $this->historyCommand($session, 'message.set', ['text' => 'Old message']);
        $this->historyCommand($session, 'finish');
        $old = TeachingSession::findOrFail($session['id']);
        $new = $this->postJson('/api/studio/sessions/'.$session['id'].'/again', [])->assertCreated()->json('session');
        $this->assertNotSame($old->id, $new['id']);
        $this->assertNotSame($old->join_code, $new['joinCode']);
        $this->assertNotSame($old->projector_token, TeachingSession::findOrFail($new['id'])->projector_token);
        $this->assertSame($old->lesson_version_id, TeachingSession::findOrFail($new['id'])->lesson_version_id);
        $this->assertSame('lesson', $new['mode']);
        $this->assertSame(1, $new['revision']);
        $this->assertSame([], $new['participants']);
        $this->assertSame([], $new['answers']);
        $this->assertSame('idle', $new['timer']['status']);
        $this->assertNull($new['message']);
        $this->postJson('/api/studio/sessions/'.$session['id'].'/again', ['ownerKey' => $this->historyOwner])->assertUnprocessable();
    }

    public function test_legacy_unknown_anchors_are_not_guessed_and_account_cutoffs_are_enforced_before_cleanup(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
        $legacy = $this->historySession();
        DB::table('teaching_sessions')->where('id', $legacy['id'])->update(['status' => 'finished', 'finished_at' => null, 'started_at' => null, 'visited_stage_ids' => null, 'created_at' => '2020-01-01']);
        $this->getJson('/api/studio/sessions/'.$legacy['id'].'/history')->assertOk()->assertJsonPath('history.startedAt', null)->assertJsonPath('history.visitedStageIds', null)->assertJsonPath('history.historyExpiresAt', null);
        $this->historyIdentity(true);
        $session = $this->historySession();
        $participant = $this->historyParticipant($session);
        $this->historyCommand($session, 'finish');
        $model = TeachingSession::findOrFail($session['id']);
        CarbonImmutable::setTestNow($model->finished_at->addDays(30));
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()->assertJsonPath('history.detailsAvailable', false)->assertJsonPath('history.participants', [])->assertJsonPath('history.answers', []);
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->assertJsonPath('session.participants', []);
        $this->getJson('/api/participation/'.$session['id'])->assertNotFound();
        $this->getJson('/api/projection/'.$model->projector_token)->assertNotFound();
        $this->postJson('/api/join', ['code' => $model->join_code, 'name' => 'Late'])->assertNotFound();
        $this->assertDatabaseHas('session_participants', ['id' => $participant['id']]);
        CarbonImmutable::setTestNow($model->finished_at->addYearsNoOverflow(2));
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertNotFound();
        $this->getJson('/api/studio/sessions')->assertJsonPath('sessions', []);
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id']]);
    }
}
