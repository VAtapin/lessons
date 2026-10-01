<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\History\RetentionPolicy;
use App\Application\History\RetentionService;
use App\Application\Shared\ApiProblem;
use App\Jobs\ApplyRetention;
use App\Models\GuestWorkspaceClaim;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\MediaOwnerQuota;
use App\Models\MediaVersion;
use App\Models\SessionAnswer;
use App\Models\SessionCommandReceipt;
use App\Models\TeachingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class RetentionTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cutoff_blocks_reads_before_job_and_cleanup_preserves_authored_material_versions_and_quota(): void
    {
        Storage::fake('media');
        $asset = $this->post('/api/studio/media', ['title' => 'Retained image', 'tags' => '[]', 'author' => 'Fixture', 'source' => 'Fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Fixture',
            'file' => UploadedFile::fake()->image('retained.png', 8, 8)], ['Accept' => 'application/json'])->assertCreated()->json('asset');
        $document = $this->historyDocument();
        $document['stages'][0]['blocks'][] = ['id' => 'image', 'type' => 'core.image', 'schemaVersion' => 1, 'content' => ['ru' => ['alt' => 'Fixture']], 'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']]]];
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 1])->assertCreated()->json('session');
        $participant = $this->historyParticipant($session);
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'a']])->assertOk();
        $this->historyCommand($session, 'finish');
        $model = TeachingSession::findOrFail($session['id']);
        $version = $model->version->getAttributes();
        $material = $model->version->material->getAttributes();
        $quota = MediaOwnerQuota::findOrFail($this->historyOwner)->getAttributes();
        $mediaVersion = MediaVersion::findOrFail($asset['currentVersionId']);
        $bytes = Storage::disk('media')->get($mediaVersion->storage_key);
        $pair = $asset['id'].'/'.$mediaVersion->id;
        CarbonImmutable::setTestNow($model->finished_at->addDays(30)->subMicrosecond());
        $this->getJson('/api/studio/sessions/'.$model->id.'/history')->assertOk();
        $this->assertSame(0, $this->retention()->run(true)['sessionsDeleted']);
        CarbonImmutable::setTestNow($model->finished_at->addDays(30));
        $this->getJson('/api/studio/sessions/'.$model->id.'/history')->assertNotFound();
        $this->getJson('/api/projection/'.$model->projector_token)->assertNotFound();
        $this->get('/media/projection/'.$model->projector_token.'/'.$pair)->assertNotFound();
        $this->get('/media/participation/'.$model->id.'/'.$pair)->assertNotFound();
        $this->assertDatabaseHas('session_participants', ['id' => $participant['id']]);
        $this->assertSame(1, $this->retention()->run(false)['sessionsDeleted']);
        $this->assertDatabaseMissing('teaching_sessions', ['id' => $model->id]);
        $this->assertDatabaseCount('session_answers', 0);
        $this->assertDatabaseCount('session_participants', 0);
        $this->assertSame($version, LessonVersion::findOrFail($model->lesson_version_id)->getAttributes());
        $this->assertSame($material, LessonMaterial::findOrFail($material['id'])->getAttributes());
        // Locking an existing quota must not modify accounting or timestamps.
        $this->assertSame($quota, MediaOwnerQuota::findOrFail($this->historyOwner)->getAttributes());
        $this->assertDatabaseHas('media_assets', ['id' => $asset['id']]);
        $this->assertDatabaseHas('media_versions', ['id' => $mediaVersion->id]);
        $this->assertSame($bytes, Storage::disk('media')->get($mediaVersion->storage_key));
        $this->get('/media/owned/'.$pair)->assertOk();
        $this->assertSame(0, array_sum($this->retention()->run(false)));
    }

    public function test_account_cutoff_hides_published_text_and_identities_without_job_then_preserves_anonymous_aggregate(): void
    {
        $this->historyIdentity(true);
        $session = $this->historySession();
        $this->historyParticipant($session);
        $this->historyCommand($session, 'block.open', ['blockId' => 'free']);
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'free', 'value' => ['text' => 'Original private text']])->assertOk();
        $answer = SessionAnswer::firstOrFail();
        $this->historyCommand($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 1, 'status' => 'approved', 'displayText' => 'Published private revision']);
        $this->historyCommand($session, 'answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => 2]);
        $this->historyCommand($session, 'finish');
        $model = TeachingSession::findOrFail($session['id']);
        $aggregate = $model->final_aggregates;
        CarbonImmutable::setTestNow($model->finished_at->addDays(30));
        foreach (['/api/studio/sessions/'.$model->id, '/api/studio/sessions/'.$model->id.'/history'] as $url) {
            $response = $this->getJson($url)->assertOk();
            foreach (['Original private text', 'Published private revision', 'Private pupil name'] as $private) {
                $this->assertStringNotContainsString($private, $response->getContent());
            }
        }
        $counts = $this->retention()->run(false);
        $this->assertSame(0, $counts['sessionsDeleted']);
        $this->assertSame(1, $counts['detailsPurged']);
        $model->refresh();
        $this->assertNotNull($model->details_purged_at);
        $this->assertNotNull($model->public_access_closed_at);
        $this->assertSame($aggregate, $model->final_aggregates);
        $this->assertDatabaseCount('session_answers', 0);
        $this->assertDatabaseCount('session_participants', 0);
        $this->assertSame(0, array_sum($this->retention()->run(false)));
    }

    public function test_retention_never_deletes_active_paused_prepared_or_unknown_legacy_sessions_and_does_not_lock_them(): void
    {
        $sessions = [];
        foreach (['running', 'paused', 'prepared', 'finished'] as $status) {
            $session = $this->historySession();
            DB::table('teaching_sessions')->where('id', $session['id'])->update(['status' => $status, 'created_at' => '2020-01-01', 'started_at' => null, 'finished_at' => null]);
            $sessions[] = $session['id'];
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $counts = $this->retention()->run(false, 1);
            $quotaLocks = array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'media_owner_quotas'));
            $this->assertSame([], $quotaLocks);
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame(0, array_sum($counts));
        $this->assertSame(4, TeachingSession::whereIn('id', $sessions)->count());
    }

    public function test_rehearsal_cleanup_is_finished_only_and_removes_only_unreferenced_noncurrent_internal_version(): void
    {
        $lesson = $this->historyLesson();
        $sessions = [];
        foreach ([true, false] as $finish) {
            $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => 1])->assertCreated()->json('session');
            if ($finish) {
                $this->historyCommand($session, 'finish');
            }
            DB::table('teaching_sessions')->where('id', $session['id'])->update(['created_at' => '2026-09-24 12:00:00']);
            $sessions[] = TeachingSession::findOrFail($session['id']);
        }
        $this->getJson('/api/studio/rehearsals/'.$sessions[0]->id.'/preview/student')->assertNotFound();
        $this->getJson('/api/studio/rehearsals/'.$sessions[1]->id.'/preview/student')->assertOk();
        $counts = $this->retention()->run(false);
        $this->assertSame(1, $counts['sessionsDeleted']);
        $this->assertSame(1, $counts['rehearsalVersionsDeleted']);
        $this->assertDatabaseMissing('lesson_versions', ['id' => $sessions[0]->lesson_version_id]);
        $this->assertDatabaseHas('lesson_versions', ['id' => $sessions[1]->lesson_version_id]);
        $this->assertDatabaseHas('lesson_versions', ['id' => $lesson['versionId'], 'purpose' => 'authoring', 'status' => 'draft']);
        $this->assertDatabaseHas('lesson_materials', ['id' => $lesson['id'], 'current_version_id' => $lesson['versionId']]);
    }

    public function test_referenced_or_current_internal_version_is_preserved_when_finished_rehearsal_is_deleted(): void
    {
        $lesson = $this->historyLesson();
        foreach (['referenced', 'current'] as $reason) {
            $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => 1])->assertCreated()->json('session');
            $this->historyCommand($session, 'finish');
            $model = TeachingSession::findOrFail($session['id']);
            DB::table('teaching_sessions')->where('id', $model->id)->update(['created_at' => '2026-09-24 12:00:00']);
            if ($reason === 'referenced') {
                $other = $model->replicate();
                $other->id = (string) Str::uuid();
                $other->join_code = 'KEEP1234';
                $other->projector_token = str_repeat('a', 64);
                $other->status = 'running';
                $other->finished_at = null;
                $other->save();
            } else {
                DB::table('lesson_materials')->where('id', $lesson['id'])->update(['current_version_id' => $model->lesson_version_id]);
            }
            $counts = $this->retention()->run(false);
            $this->assertSame(1, $counts['sessionsDeleted']);
            $this->assertSame(0, $counts['rehearsalVersionsDeleted']);
            $this->assertDatabaseHas('lesson_versions', ['id' => $model->lesson_version_id]);
            DB::table('lesson_materials')->where('id', $lesson['id'])->update(['current_version_id' => $lesson['versionId']]);
        }
    }

    public function test_dry_run_counts_without_writes_and_bounded_batches_are_repeatable_and_operational_output_has_no_details(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $session = $this->historySession();
            $this->historyParticipant($session);
            $this->historyCommand($session, 'finish');
            DB::table('teaching_sessions')->where('id', $session['id'])->update(['finished_at' => '2026-09-01 12:00:00']);
        }
        $before = TeachingSession::query()->get()->map->getAttributes()->all();
        $quotaBefore = MediaOwnerQuota::query()->get()->map->getAttributes()->all();
        $dry = $this->retention()->run(true, 1);
        $this->assertSame(1, $dry['sessionsDeleted']);
        $this->assertSame($before, TeachingSession::query()->get()->map->getAttributes()->all());
        $this->assertSame($quotaBefore, MediaOwnerQuota::query()->get()->map->getAttributes()->all());
        $this->assertSame(0, Artisan::call('lessons:retention', ['--dry-run' => true, '--batch' => 1]));
        $output = Artisan::output();
        $this->assertStringContainsString('"dryRun":true', $output);
        $this->assertStringNotContainsString('Private pupil name', $output);
        $this->assertStringNotContainsString($this->historyOwner, $output);
        foreach ([2, 1, 0] as $remaining) {
            $this->assertSame(1, $this->retention()->run(false, 1)['sessionsDeleted']);
            $this->assertSame($remaining, TeachingSession::count());
        }
        $this->assertSame(0, array_sum($this->retention()->run(false, 1)));
        foreach ([0, 1001, 'bad'] as $batch) {
            $this->assertSame(1, Artisan::call('lessons:retention', ['--batch' => $batch]));
        }
    }

    public function test_calendar_years_and_utc_microsecond_boundaries_are_precise_in_policy_and_candidate_selection(): void
    {
        $this->historyIdentity(true);
        $session = $this->historySession();
        DB::table('teaching_sessions')->where('id', $session['id'])->update(['status' => 'finished', 'finished_at' => '2024-02-29 12:00:00.123456', 'details_purged_at' => '2024-04-01']);
        $model = TeachingSession::findOrFail($session['id']);
        $this->assertSame('2026-02-28T12:00:00.123456Z', $this->app->make(RetentionPolicy::class)->historyExpiresAt($model)->toISOString());
        CarbonImmutable::setTestNow('2026-02-28 12:00:00.123455 UTC');
        $this->assertSame(0, $this->retention()->run(true)['sessionsDeleted']);
        CarbonImmutable::setTestNow('2026-02-28 12:00:00.123456 UTC');
        $this->assertSame(1, $this->retention()->run(false)['sessionsDeleted']);
    }

    public function test_old_receipts_are_removed_for_active_sessions_without_reapplying_successful_command_or_deleting_claim_tombstone(): void
    {
        $session = $this->historySession();
        $body = $this->historyCommand($session, 'message.set', ['text' => 'Original message']);
        $receipt = SessionCommandReceipt::where('teaching_session_id', $session['id'])->firstOrFail();
        $receipt->created_at = CarbonImmutable::now('UTC')->subDays(30);
        $receipt->save();
        $user = User::factory()->create(['owner_key' => (string) Str::uuid()]);
        $claim = GuestWorkspaceClaim::create(['source_owner_key' => (string) Str::uuid(), 'target_user_id' => $user->id, 'target_owner_key' => $user->owner_key, 'result' => ['status' => 'claimed'], 'created_at' => '2020-01-01']);
        $this->assertSame(1, $this->retention()->run(false)['receiptsDeleted']);
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'status' => 'running']);
        $this->assertDatabaseHas('guest_workspace_claims', ['source_owner_key' => $claim->source_owner_key]);
        $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', $body)->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $this->assertSame('Original message', TeachingSession::findOrFail($session['id'])->message);
    }

    public function test_detail_cleanup_failure_rolls_back_and_can_restart_without_private_diagnostics(): void
    {
        $this->historyIdentity(true);
        $session = $this->historySession();
        $participant = $this->historyParticipant($session);
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'choice', 'value' => ['optionId' => 'b']])->assertOk();
        $this->historyCommand($session, 'finish');
        CarbonImmutable::setTestNow(TeachingSession::findOrFail($session['id'])->finished_at->addDays(30));
        TeachingSession::saving(function (TeachingSession $model): void {
            if ($model->details_purged_at !== null) {
                throw new RuntimeException('Private pupil name SQL-secret');
            }
        });
        try {
            $this->retention()->run(false);
            $this->fail('A failure must roll back the entire selected session mutation.');
        } catch (ApiProblem $problem) {
            $this->assertSame('retention_failed', $problem->problemCode);
        } finally {
            TeachingSession::flushEventListeners();
        }
        $this->assertDatabaseHas('session_participants', ['id' => $participant['id']]);
        $this->assertDatabaseCount('session_answers', 1);
        $this->assertNull(TeachingSession::findOrFail($session['id'])->details_purged_at);
        (new ApplyRetention)->handle($this->retention());
        $this->assertDatabaseCount('session_answers', 0);
        $this->assertNotNull(TeachingSession::findOrFail($session['id'])->details_purged_at);
    }

    public function test_owner_discovery_retries_after_claim_without_extending_retention_anchors(): void
    {
        $session = $this->historySession();
        $this->historyParticipant($session);
        $this->historyCommand($session, 'finish');
        $model = TeachingSession::findOrFail($session['id']);
        $finished = $model->finished_at->toISOString();
        $created = $model->created_at->toISOString();
        $source = $this->historyOwner;
        $user = User::factory()->create(['owner_key' => (string) Str::uuid()]);
        CarbonImmutable::setTestNow($model->finished_at->addDays(30));
        $connection = DB::connection();
        $original = $connection->getEventDispatcher();
        $isolated = clone $original;
        $connection->setEventDispatcher($isolated);
        $moved = false;
        $isolated->listen(QueryExecuted::class, function (QueryExecuted $query) use (&$moved, $source, $model, $user): void {
            if (! $moved && preg_match('/select ["`]?owner_key["`]? from ["`]?teaching_sessions/', $query->sql)) {
                // Controlled interleaving after the old owner was read, before its mutex.
                // Real claim concurrency is covered by the separate MariaDB integration suite.
                $moved = true;
                DB::table('teaching_sessions')->where('id', $model->id)->update(['owner_key' => $user->owner_key]);
                DB::table('lesson_materials')->where('id', $model->version->lesson_material_id)->update(['owner_key' => $user->owner_key]);
                GuestWorkspaceClaim::create(['source_owner_key' => $source, 'target_user_id' => $user->id, 'target_owner_key' => $user->owner_key, 'result' => ['status' => 'claimed']]);
            }
        });
        try {
            $counts = $this->retention()->run(false);
        } finally {
            $connection->setEventDispatcher($original);
        }
        $this->assertTrue($moved);
        $this->assertSame(0, $counts['sessionsDeleted']);
        $this->assertSame(1, $counts['detailsPurged']);
        $model->refresh();
        $this->assertSame($user->owner_key, $model->owner_key);
        $this->assertSame($finished, $model->finished_at->toISOString());
        $this->assertSame($created, $model->created_at->toISOString());
        $this->assertNotNull($model->details_purged_at);
        $this->assertDatabaseHas('guest_workspace_claims', ['source_owner_key' => $source]);
    }

    private function retention(): RetentionService
    {
        return $this->app->make(RetentionService::class);
    }
}
