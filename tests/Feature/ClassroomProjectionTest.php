<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Catalog\NeighborUpgradeInstaller;
use App\Application\Runtime\RuntimeConflict;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Models\LessonMaterial;
use App\Models\SessionAnswer;
use App\Models\SessionCommandReceipt;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ClassroomProjectionTest extends TestCase
{
    use RefreshDatabase;

    private RuntimeService $runtime;

    private string $owner;

    private string $token;

    private string $student;

    private string $other;

    private string $lessonId;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();
        app(NeighborInstaller::class)->install();
        app(NeighborUpgradeInstaller::class)->install('v2');
        app(NeighborUpgradeInstaller::class)->install('v3');
        $this->owner = (string) Str::uuid();
        $this->runtime = app(RuntimeService::class);
        $copy = app(CatalogService::class)->use('kto-moi-blizhnii', 'ru', $this->owner, true);
        $this->session = $copy['session'];
        $this->lessonId = $copy['lesson']['id'];
        $this->token = TeachingSession::findOrFail($this->session['id'])->projector_token;
        $this->student = $this->runtime->join($this->session['joinCode'], 'Private first pupil', [])['participant']['id'];
        $this->other = $this->runtime->join($this->session['joinCode'], 'Private second pupil', [])['participant']['id'];
    }

    public function test_qr_can_be_shown_before_begin_and_persists_through_poll_navigation_and_pause_only_on_projector(): void
    {
        $this->assertSame('prepared', $this->session['status']);
        $this->assertFalse($this->session['joinProjectionVisible']);
        $this->assertArrayNotHasKey('joinProjection', $this->projector());
        $this->execute('join.show');
        $expected = ['code' => $this->session['joinCode'], 'url' => url('/ru/join').'?code='.$this->session['joinCode']];
        $this->assertSame($expected, $this->projector()['joinProjection']);
        $this->assertTrue(TeachingSession::findOrFail($this->session['id'])->join_projection);
        $this->assertTrue($this->runtime->teacher($this->owner, $this->session['id'])['joinProjectionVisible']);
        $this->assertSame($expected, $this->projector()['joinProjection']);
        $this->execute('begin');
        $this->execute('stage', ['stageId' => 'neighbor-traveler']);
        $this->execute('pause');
        $this->assertSame($expected, $this->projector()['joinProjection']);
        foreach ([$this->student, $this->other] as $participant) {
            $state = $this->runtime->student($this->session['id'], $participant);
            foreach (['joinCode', 'joinUrl', 'joinProjection', 'joinProjectionVisible', 'projectorUrl'] as $key) {
                $this->assertArrayNotHasKey($key, $state);
            }
            $this->assertStringNotContainsString($this->session['joinCode'], json_encode($state));
            $this->assertStringNotContainsString($expected['url'], json_encode($state));
        }
        $this->execute('join.hide');
        $this->assertFalse(TeachingSession::findOrFail($this->session['id'])->join_projection);
        $this->assertArrayNotHasKey('joinProjection', $this->projector());
    }

    public function test_qr_receipt_retries_acknowledge_current_state_without_reapplying_or_advancing_revision(): void
    {
        $id = (string) Str::uuid();
        $before = $this->session['revision'];
        $result = $this->runtime->command($this->owner, $this->session['id'], $id, $before, 'join.show', []);
        $this->session = $result['session'];
        $this->assertSame($id, $result['acknowledgedCommandId']);
        $this->assertSame($before + 1, $this->session['revision']);
        $replay = $this->runtime->command($this->owner, $this->session['id'], $id, $before, 'join.show', []);
        $this->assertSame($id, $replay['acknowledgedCommandId']);
        $this->assertSame($this->session['revision'], $replay['session']['revision']);
        $this->assertSame(1, SessionCommandReceipt::where('teaching_session_id', $this->session['id'])->count());
        $this->execute('join.hide');
        $replay = $this->runtime->command($this->owner, $this->session['id'], $id, $before, 'join.show', []);
        $this->assertFalse($replay['session']['joinProjectionVisible']);
        $this->assertSame($this->session['revision'], $replay['session']['revision']);
        $this->assertArrayNotHasKey('joinProjection', $this->projector());
        $this->expectProblem('command_conflict', fn () => $this->runtime->command($this->owner, $this->session['id'], $id, $before, 'join.hide', []));
        $this->expectProblem('revision_conflict', fn () => $this->runtime->command($this->owner, $this->session['id'], (string) Str::uuid(), $before, 'join.show', []));
    }

    public function test_qr_commands_reject_foreign_owner_non_presenter_and_untrusted_payload_without_mutation(): void
    {
        foreach (['join.show', 'join.hide'] as $action) {
            $this->expectProblem('not_found', fn () => $this->runtime->command((string) Str::uuid(), $this->session['id'], (string) Str::uuid(), $this->session['revision'], $action, []));
            $this->expectProblem('invalid_action', fn () => $this->execute($action, ['url' => 'https://injected.example']));
        }
        $this->assertDatabaseCount('session_command_receipts', 0);
        $model = TeachingSession::findOrFail($this->session['id']);
        $model->forceFill(['presenter_is_owner' => false, 'presenter_epoch' => 1])->save();
        $this->expectProblem('presenter_required', fn () => $this->runtime->command($this->owner, $this->session['id'], (string) Str::uuid(), $this->session['revision'], 'join.show', [], controlEpoch: 1));
        $this->assertSame(1, $model->fresh()->revision);
        $this->assertFalse($model->fresh()->join_projection);
        $this->assertDatabaseCount('session_command_receipts', 0);
    }

    public function test_rehearsal_rejects_qr_and_exposes_no_invitation(): void
    {
        $this->withSession(['studio_owner_key' => $this->owner]);
        $rehearsal = $this->postJson('/api/studio/lessons/'.$this->lessonId.'/rehearsals', ['expectedRevision' => LessonMaterial::findOrFail($this->lessonId)->revision])->assertCreated()->json('session');
        $this->assertSame('rehearsal', $rehearsal['mode']);
        foreach (['join.show', 'join.hide'] as $action) {
            $this->expectProblem('invalid_state', fn () => $this->runtime->command($this->owner, $rehearsal['id'], (string) Str::uuid(), $rehearsal['revision'], $action, []));
        }
        foreach (['student', 'projector'] as $audience) {
            $state = $this->runtime->rehearsalPreview($this->owner, $rehearsal['id'], $audience);
            $this->assertArrayNotHasKey('joinProjection', $state);
            $this->assertArrayNotHasKey('joinCode', $state);
        }
        $this->assertFalse(TeachingSession::findOrFail($rehearsal['id'])->join_projection);
        $this->assertSame($rehearsal['revision'], TeachingSession::findOrFail($rehearsal['id'])->revision);
    }

    public function test_finish_clears_qr_message_wave_and_returns_the_saved_closing_from_another_stage_without_private_notes(): void
    {
        $this->execute('begin');
        $this->execute('stage', ['stageId' => 'neighbor-newcomer']);
        $showId = (string) Str::uuid();
        $showRevision = $this->session['revision'];
        $this->session = $this->runtime->command($this->owner, $this->session['id'], $showId, $showRevision, 'join.show', [])['session'];
        $this->execute('message.set', ['text' => 'Temporary classroom message']);
        $this->execute('wave');
        $this->assertArrayNotHasKey('closing', $this->projector());
        $this->execute('finish');
        $model = TeachingSession::findOrFail($this->session['id']);
        $this->assertFalse($model->join_projection);
        $this->assertNull($model->message);
        $this->assertNull($model->wave_id);
        $closing = collect($model->version->document['stages'][12]['blocks'])->firstWhere('id', 'lesson-closing');
        foreach ([$this->projector(), $this->runtime->student($this->session['id'], $this->student)] as $state) {
            $this->assertSame('finished', $state['status']);
            $this->assertSame('neighbor-newcomer', $state['currentStageId']);
            $this->assertArrayNotHasKey('joinProjection', $state);
            $this->assertNull($state['message']);
            $this->assertNull($state['wave']);
            $this->assertSame($closing['content']['ru'], $state['closing']['content']);
            foreach (['teacherNotes', 'documentation', 'notes', 'Private first pupil', 'Private second pupil'] as $private) {
                $this->assertStringNotContainsString($private, json_encode($state));
            }
        }
        $retry = $this->runtime->command($this->owner, $this->session['id'], $showId, $showRevision, 'join.show', []);
        $this->assertSame('finished', $retry['session']['status']);
        $this->assertFalse($retry['session']['joinProjectionVisible']);
        $this->assertSame($this->session['revision'], $retry['session']['revision']);
        $this->expectProblem('invalid_state', fn () => $this->execute('join.show'));
    }

    public function test_school_mode_answers_persist_independently_and_change_without_kindness_or_public_answer_leaks(): void
    {
        $this->execute('stage', ['stageId' => 'neighbor-newcomer']);
        $this->expectProblem('invalid_state', fn () => $this->submit($this->student, ['modeId' => 'help']));
        $this->execute('begin');
        $this->execute('presentation.mode', ['blockId' => 'newcomer-discussion', 'modeId' => 'help']);
        $this->submit($this->student, ['modeId' => 'excuse']);
        $this->submit($this->other, ['modeId' => 'help']);
        $answer = SessionAnswer::where('session_participant_id', $this->student)->firstOrFail();
        $this->assertSame(1, $answer->revision);
        $this->assertSame(0, $answer->kindness_points);
        $this->assertSame(['modeId' => 'excuse'], $this->own($this->student)['value']);
        $this->assertSame(['modeId' => 'help'], $this->own($this->other)['value']);
        $this->submit($this->student, ['modeId' => 'help']);
        $this->assertSame(2, $answer->fresh()->revision);
        $this->submit($this->student, ['modeId' => 'help']);
        $this->assertSame(2, $answer->fresh()->revision);
        $this->assertSame(2, SessionAnswer::where('teaching_session_id', $this->session['id'])->count());
        $this->execute('stage', ['stageId' => 'neighbor-books']);
        $this->execute('stage', ['stageId' => 'neighbor-newcomer']);
        $this->assertSame(['modeId' => 'help'], $this->own($this->student)['value']);
        foreach ([$this->student, $this->other] as $participant) {
            $state = $this->runtime->student($this->session['id'], $participant);
            $this->assertSame(0, $state['kindnessPoints']);
            $this->assertCount(1, $state['ownAnswers']);
            $this->assertNull($state['ownAnswers'][0]['grade']);
        }
        $this->execute('wave');
        $state = $this->projector();
        $this->assertSame(['points' => 0, 'participants' => 0], $state['wave']['aggregate']);
        $this->assertArrayNotHasKey('ownAnswers', $state);
        $shared = collect($state['stage']['blocks'])->firstWhere('id', 'newcomer-discussion');
        $this->assertSame('help', $shared['runtime']['presentation']['modeId']);
        $this->assertArrayNotHasKey('results', $shared['runtime']);
        foreach (['participantId', 'Private first pupil', 'Private second pupil'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($state));
        }
        foreach ([['modeId' => 'unknown'], ['modeId' => 'help', 'published' => true], ['modeId' => null]] as $value) {
            $this->expectProblem('invalid_action', fn () => $this->submit($this->student, $value));
        }
        $this->assertSame(2, $answer->fresh()->revision);
        $this->execute('pause');
        $this->expectProblem('invalid_state', fn () => $this->submit($this->student, ['modeId' => 'excuse']));
        $this->execute('resume');
        $this->execute('block.close', ['blockId' => 'newcomer-discussion']);
        $this->expectProblem('invalid_state', fn () => $this->submit($this->student, ['modeId' => 'excuse']));
    }

    public function test_hidden_reveal_does_not_send_the_separate_quote_or_source_until_teacher_reveals_it(): void
    {
        $this->execute('begin');
        $this->execute('stage', ['stageId' => 'neighbor-question']);
        foreach ([$this->projector(), $this->runtime->student($this->session['id'], $this->student)] as $state) {
            $reveal = collect($state['stage']['blocks'])->firstWhere('id', 'neighbor-reveal');
            $this->assertSame('', $reveal['content']['text']);
            $this->assertSame('', $reveal['content']['quote'] ?? '');
            $this->assertSame('', $reveal['content']['source'] ?? '');
        }
        $this->execute('choice.select', ['blockId' => 'neighbor-choice', 'optionId' => 'samaritan']);
        $reveal = collect($this->projector()['stage']['blocks'])->firstWhere('id', 'neighbor-reveal');
        $this->assertSame('«Иди, и ты поступай так же»', $reveal['content']['quote']);
        $this->assertSame('Лк 10:37', $reveal['content']['source']);
    }

    private function projector(): array
    {
        return $this->runtime->projector($this->token);
    }

    private function execute(string $action, array $payload = []): void
    {
        $this->session = $this->runtime->command($this->owner, $this->session['id'], (string) Str::uuid(), $this->session['revision'], $action, $payload)['session'];
    }

    private function submit(string $participant, array $value): void
    {
        $this->runtime->answer($this->session['id'], $participant, 'neighbor-newcomer', 'newcomer-discussion', ['stageId' => 'neighbor-newcomer', 'blockId' => 'newcomer-discussion', 'value' => $value]);
    }

    private function own(string $participant): array
    {
        return collect($this->runtime->student($this->session['id'], $participant)['ownAnswers'])->firstWhere('blockId', 'newcomer-discussion');
    }

    private function expectProblem(string $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the operation to fail.');
        } catch (ApiProblem|RuntimeConflict $problem) {
            $this->assertSame($code, $problem->problemCode);
        }
    }
}
