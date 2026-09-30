<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Runtime\RuntimeService;
use App\Http\Controllers\Runtime\RuntimeController;
use App\Models\SessionCommandReceipt;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

final class RuntimeControlsTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $this->owner]);
        $this->travelTo(CarbonImmutable::parse('2026-09-30T12:00:00.250000Z'));
    }

    public function test_prepared_running_paused_finished_states_enforce_participation(): void
    {
        $session = $this->start(true);
        $this->assertSame('prepared', $session['status']);
        $participant = $this->join($session);
        $this->answer($session)->assertConflict()->assertJsonPath('error.code', 'invalid_state');
        $this->execute($session, 'begin');
        $this->assertSame('running', $session['status']);
        $this->answer($session)->assertOk();
        $this->execute($session, 'pause');
        $this->answer($session)->assertConflict();
        $this->execute($session, 'resume');
        $this->answer($session)->assertOk();
        $this->execute($session, 'finish');
        $this->assertSame('finished', $session['status']);
        $this->answer($session)->assertConflict();
        $this->getJson('/api/participation/'.$session['id'])->assertOk()
            ->assertJsonPath('session.status', 'finished')->assertJsonPath('session.ownAnswers.0.optionId', 'first');
        $this->assertSame($participant['id'], $this->join($session)['id']);
        $this->withSession([RuntimeController::SESSION_PARTICIPANTS_KEY => []]);
        $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => 'New'])->assertConflict();
        $this->assertDatabaseCount('session_participants', 1);
    }

    public function test_invalid_state_commands_do_not_save_receipts_or_change_revision(): void
    {
        $session = $this->start();
        foreach (['begin', 'resume', 'timer.pause', 'timer.resume'] as $action) {
            $this->command($session, $action)->assertConflict()->assertJsonPath('error.code', 'invalid_state')
                ->assertJsonPath('session.revision', 1);
        }
        $this->assertDatabaseCount('session_command_receipts', 0);
        $this->execute($session, 'pause');
        $this->command($session, 'pause')->assertConflict();
        $this->command($session, 'timer.start', ['seconds' => 10])->assertConflict();
        $this->execute($session, 'finish');
        foreach (['resume', 'stage', 'wave', 'message.clear', 'timer.clear', 'finish'] as $action) {
            $this->command($session, $action, $action === 'stage' ? ['stageId' => 'stage-2'] : [])->assertConflict();
        }
        $this->assertDatabaseCount('session_command_receipts', 2);
        $prepared = $this->start(true);
        $this->execute($prepared, 'finish');
        $this->assertSame('finished', $prepared['status']);
    }

    public function test_replayed_uuid_returns_current_state_without_reapplying_wave(): void
    {
        $session = $this->start();
        $commandId = (string) Str::uuid();
        $original = $session;
        $response = $this->command($session, 'wave', [], $commandId)->assertOk()->assertJsonPath('acknowledgedCommandId', $commandId);
        $session = $response->json('session');
        $wave = $session['wave'];
        $this->travel(2)->seconds();
        $this->execute($session, 'message.set', ['text' => 'Latest']);
        $this->command($original, 'wave', [], $commandId)->assertOk()
            ->assertJsonPath('session.revision', 3)->assertJsonPath('session.message', 'Latest')->assertJsonPath('session.wave', $wave);
        $this->command($original, 'wave', [], strtoupper($commandId))->assertOk()->assertJsonPath('acknowledgedCommandId', $commandId);
        $this->travel(5)->seconds();
        $this->command($original, 'wave', [], $commandId)->assertOk()->assertJsonPath('session.wave', null)->assertJsonPath('session.revision', 3);
        $this->assertDatabaseCount('session_command_receipts', 2);
    }

    public function test_uuid_body_conflicts_and_stale_revision_return_current_owner_state(): void
    {
        $session = $this->start();
        $original = $session;
        $commandId = (string) Str::uuid();
        $session = $this->command($session, 'message.set', ['text' => 'Original'], $commandId)->assertOk()->json('session');
        $this->command($original, 'message.set', ['text' => 'Changed'], $commandId)->assertConflict()
            ->assertJsonPath('error.code', 'command_conflict')->assertJsonPath('session.message', 'Original');
        $this->command($session, 'message.set', ['text' => 'Original'], $commandId)->assertConflict()->assertJsonPath('error.code', 'command_conflict');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', [
            'commandId' => $commandId, 'expectedRevision' => 1, 'action' => 'message.set', 'payload' => ['text' => 'Original'], 'unexpected' => true,
        ])->assertConflict()->assertJsonPath('error.code', 'command_conflict');
        $rejectedId = (string) Str::uuid();
        $this->command($original, 'pause', [], $rejectedId)->assertConflict()
            ->assertJsonPath('error.code', 'revision_conflict')->assertJsonPath('session.revision', 2);
        $session = $this->command($session, 'pause', [], $rejectedId)->assertOk()->json('session');
        $this->assertSame('paused', $session['status']);
        $this->assertDatabaseCount('session_command_receipts', 2);
    }

    public function test_receipt_and_conflict_state_are_unavailable_to_a_foreign_owner(): void
    {
        $session = $this->start();
        $commandId = (string) Str::uuid();
        $this->command($session, 'wave', [], $commandId)->assertOk();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $response = $this->command($session, 'wave', [], $commandId)->assertNotFound();
        $this->assertSame(['error' => ['code' => 'not_found']], $response->json());
        $this->assertDatabaseCount('session_command_receipts', 1);
    }

    public function test_invalid_payloads_roll_back_without_receipts_and_the_uuid_can_be_corrected(): void
    {
        $session = $this->start();
        $bad = [
            ['timer.start', ['seconds' => 0]], ['timer.start', ['seconds' => 7201]],
            ['timer.start', ['seconds' => '10']], ['timer.start', ['seconds' => 1.5]],
            ['message.set', ['text' => str_repeat('я', 1001)]], ['message.set', ['text' => 'OK', 'solution' => 'secret']],
            ['stage', ['stageId' => 'unknown']], ['wave', ['force' => true]], ['unknown', []],
        ];
        $id = (string) Str::uuid();
        foreach ($bad as [$action, $payload]) {
            $this->command($session, $action, $payload, $id)->assertUnprocessable()->assertJsonPath('error.code', 'invalid_action');
        }
        $this->assertDatabaseCount('session_command_receipts', 0);
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'revision' => 1, 'timer_status' => 'idle', 'message' => null]);
        $this->command($session, 'message.set', ['text' => '  <b>literal</b>  '], $id)->assertOk()->assertJsonPath('session.message', '  <b>literal</b>  ');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', [
            'commandId' => (string) Str::uuid(), 'expectedRevision' => '2', 'action' => 'wave', 'payload' => [],
        ])->assertUnprocessable();
        $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', [
            'commandId' => (string) Str::uuid(), 'expectedRevision' => 2, 'action' => 'wave', 'payload' => [], 'unexpected' => true,
        ])->assertUnprocessable()->assertJsonPath('error.code', 'invalid_action');
    }

    public function test_session_pause_freezes_and_resumes_a_running_timer_across_reload(): void
    {
        $session = $this->start();
        $this->join($session);
        $this->execute($session, 'timer.start', ['seconds' => 60]);
        $initialEnd = $session['timer']['endsAt'];
        $this->travel(10)->seconds();
        $this->execute($session, 'pause');
        $this->assertSame(['status' => 'paused', 'endsAt' => null, 'remainingSeconds' => 50], $session['timer']);
        $this->travel(30)->seconds();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->assertJsonPath('session.timer.remainingSeconds', 50);
        $this->execute($session, 'resume');
        $this->assertSame('running', $session['timer']['status']);
        $this->assertNotSame($initialEnd, $session['timer']['endsAt']);
        $this->answer($session)->assertOk();
        $this->getJson('/api/projection/'.$this->token($session))->assertOk()->assertJsonPath('session.timer.endsAt', $session['timer']['endsAt']);
    }

    public function test_independent_timer_pause_allows_answers_and_is_not_resumed_by_session_resume(): void
    {
        $session = $this->start();
        $this->join($session);
        $this->execute($session, 'timer.start', ['seconds' => 40]);
        $this->travel(7)->seconds();
        $this->execute($session, 'timer.pause');
        $this->answer($session)->assertOk();
        $this->execute($session, 'pause');
        $this->travel(20)->seconds();
        $this->execute($session, 'resume');
        $this->assertSame('paused', $session['timer']['status']);
        $this->assertSame(33, $session['timer']['remainingSeconds']);
        $this->execute($session, 'timer.resume');
        $this->assertSame('running', $session['timer']['status']);
        $this->execute($session, 'timer.clear');
        $this->assertSame(['status' => 'idle', 'endsAt' => null, 'remainingSeconds' => 0], $session['timer']);
    }

    public function test_timer_expiration_uses_server_deadline_without_finishing_or_changing_revision(): void
    {
        $session = $this->start();
        $this->join($session);
        $this->execute($session, 'timer.start', ['seconds' => 1]);
        $end = $session['timer']['endsAt'];
        $this->travelTo(CarbonImmutable::parse($end)->subMicrosecond());
        $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonPath('session.timer.remainingSeconds', 1);
        $this->travelTo(CarbonImmutable::parse($end));
        $this->getJson('/api/participation/'.$session['id'])->assertOk()
            ->assertJsonPath('session.timer.status', 'expired')->assertJsonPath('session.timer.remainingSeconds', 0)
            ->assertJsonPath('session.status', 'running')->assertJsonPath('session.revision', 2);
        $this->answer($session)->assertOk();
        $this->command($session, 'timer.pause')->assertConflict();
    }

    public function test_stage_commands_and_legacy_navigation_preserve_timer_and_answers(): void
    {
        $session = $this->start();
        $this->join($session);
        $this->answer($session)->assertOk();
        $this->execute($session, 'timer.start', ['seconds' => 60]);
        $end = $session['timer']['endsAt'];
        $this->execute($session, 'stage', ['stageId' => 'stage-2']);
        $this->assertSame($end, $session['timer']['endsAt']);
        $this->execute($session, 'stage', ['stageId' => 'stage-2']);
        $this->assertSame(4, $session['revision']);
        $this->postJson('/api/studio/sessions/'.$session['id'].'/stage', ['expectedRevision' => 4, 'stageId' => 'stage-2'])
            ->assertOk()->assertJsonPath('session.revision', 4);
        $this->execute($session, 'stage', ['stageId' => 'stage-1']);
        $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonPath('session.ownAnswers.0.optionId', 'first');
    }

    public function test_activity_is_throttled_and_connected_is_only_a_recent_activity_indicator(): void
    {
        $session = $this->start();
        $participant = $this->join($session);
        $seen = SessionParticipant::findOrFail($participant['id'])->last_seen_at;
        $this->travel(4)->seconds();
        $this->getJson('/api/participation/'.$session['id'])->assertOk();
        $this->assertTrue($seen->equalTo(SessionParticipant::findOrFail($participant['id'])->last_seen_at));
        $this->travel(1)->seconds();
        $this->getJson('/api/participation/'.$session['id'])->assertOk();
        $newSeen = SessionParticipant::findOrFail($participant['id'])->last_seen_at;
        $this->assertTrue($newSeen->greaterThan($seen));
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->assertJsonPath('session.participants.0.connected', true);
        $this->travel(21)->seconds();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()
            ->assertJsonPath('session.participants.0.connected', false)->assertJsonPath('session.participants.0.lastSeenAt', $newSeen->utc()->toISOString());
        $this->assertSame(1, TeachingSession::findOrFail($session['id'])->revision);
    }

    public function test_public_control_fields_do_not_expose_credentials_notes_or_command_receipts(): void
    {
        $session = $this->start();
        $this->join($session);
        $this->execute($session, 'message.set', ['text' => '<script>plain text</script>']);
        $this->execute($session, 'wave');
        $teacherStage = $session['publicStage'];
        $student = $this->getJson('/api/participation/'.$session['id'])->assertOk()->json('session');
        $projector = $this->getJson('/api/projection/'.$this->token($session))->assertOk()->json('session');
        $this->assertSame($teacherStage, $projector['stage']);
        $this->assertSame($teacherStage, $student['stage']);
        $this->assertSame($student['wave'], $projector['wave']);
        $this->assertSame('<script>plain text</script>', $student['message']);
        foreach ([$student, $projector] as $state) {
            $json = json_encode($state, JSON_THROW_ON_ERROR);
            foreach (['Teacher secret', 'solution', 'origin', 'joinCode', 'joinUrl', 'projectorUrl', 'participants', 'lastSeenAt', 'fingerprint', 'command_id', 'acknowledgedCommandId', $this->owner] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
        $this->travel(6)->seconds();
        $this->getJson('/api/projection/'.$this->token($session))->assertOk()->assertJsonPath('session.wave', null);
        $this->execute($session, 'message.clear');
        $this->assertNull($session['message']);
    }

    public function test_independent_sessions_do_not_share_state_timer_message_wave_or_receipts(): void
    {
        $first = $this->start();
        $second = $this->start();
        $id = (string) Str::uuid();
        $first = $this->command($first, 'wave', [], $id)->assertOk()->json('session');
        $second = $this->command($second, 'wave', [], $id)->assertOk()->json('session');
        $this->assertNotSame($first['wave']['id'], $second['wave']['id']);
        $this->execute($first, 'timer.start', ['seconds' => 10]);
        $this->execute($first, 'message.set', ['text' => 'First only']);
        $this->execute($first, 'pause');
        $this->getJson('/api/projection/'.$this->token($second))->assertOk()
            ->assertJsonPath('session.status', 'running')->assertJsonPath('session.message', null)
            ->assertJsonPath('session.timer.status', 'idle')->assertJsonPath('session.revision', 2);
    }

    public function test_command_requires_csrf_and_does_not_record_an_unverified_request(): void
    {
        $session = $this->start();
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        $this->withMiddleware();
        try {
            $token = Str::random(40);
            $this->withSession(['_token' => $token]);
            $body = ['commandId' => (string) Str::uuid(), 'expectedRevision' => 1, 'action' => 'pause', 'payload' => []];
            $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', $body, ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->assertDatabaseCount('session_command_receipts', 0);
            $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', $body, [
                'Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => $token,
            ])->assertOk()->assertJsonPath('session.status', 'paused');
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    public function test_receipt_failure_rolls_back_the_session_effect_and_the_same_uuid_can_retry(): void
    {
        $session = $this->start();
        $id = (string) Str::uuid();
        SessionCommandReceipt::creating(fn () => throw new RuntimeException('Simulated receipt failure'));
        try {
            $this->app->make(RuntimeService::class)->command($this->owner, $session['id'], $id, 1, 'message.set', ['text' => 'Atomic']);
            $this->fail('Receipt failure must abort the command.');
        } catch (RuntimeException $problem) {
            $this->assertSame('Simulated receipt failure', $problem->getMessage());
        } finally {
            SessionCommandReceipt::flushEventListeners();
        }
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'revision' => 1, 'message' => null]);
        $this->assertDatabaseCount('session_command_receipts', 0);
        $this->command($session, 'message.set', ['text' => 'Atomic'], $id)->assertOk()->assertJsonPath('session.revision', 2);
    }

    public function test_clearing_timer_during_session_pause_prevents_automatic_resume(): void
    {
        $session = $this->start();
        $this->execute($session, 'timer.start', ['seconds' => 60]);
        $this->execute($session, 'pause');
        $this->execute($session, 'timer.clear');
        $this->execute($session, 'resume');
        $this->assertSame('idle', $session['timer']['status']);
    }

    private function start(bool $prepare = false): array
    {
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated()->json('lesson');

        return $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision'], 'prepare' => $prepare])->assertCreated()->json('session');
    }

    private function command(array $session, string $action, array $payload = [], ?string $id = null): TestResponse
    {
        return $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', [
            'commandId' => $id ?? (string) Str::uuid(), 'expectedRevision' => $session['revision'], 'action' => $action, 'payload' => $payload,
        ]);
    }

    private function execute(array &$session, string $action, array $payload = []): void
    {
        $revision = $session['revision'];
        $session = $this->command($session, $action, $payload)->assertOk()->json('session');
        $this->assertSame($revision + 1, $session['revision']);
    }

    private function join(array $session): array
    {
        return $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => 'Student'])->assertOk()->json('participant');
    }

    private function answer(array $session): TestResponse
    {
        return $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'stage-1', 'blockId' => 'choice-1', 'optionId' => 'first']);
    }

    private function token(array $session): string
    {
        return basename($session['projectorUrl']);
    }

    private function document(): array
    {
        $choice = [
            'id' => 'choice-1', 'type' => 'core.single-choice', 'schemaVersion' => 1,
            'content' => ['ru' => ['question' => 'Question', 'options' => [['optionId' => 'first', 'text' => 'First'], ['optionId' => 'second', 'text' => 'Second']]]],
            'solution' => ['optionId' => 'first'], 'origin' => ['templateId' => 'private-template', 'versionId' => 'private-version'],
        ];

        return [
            'id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'], 'content' => ['ru' => ['title' => 'Controls']],
            'stages' => [
                ['id' => 'stage-1', 'content' => ['ru' => ['title' => 'First', 'notes' => 'Teacher secret']], 'config' => ['durationSeconds' => 90], 'blocks' => [$choice]],
                ['id' => 'stage-2', 'content' => ['ru' => ['title' => 'Second']], 'blocks' => [['id' => 'text-2', 'type' => 'core.text', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Later']]]]],
            ],
        ];
    }
}
