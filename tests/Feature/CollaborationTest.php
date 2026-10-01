<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SessionCommandReceipt;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class CollaborationTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T12:00:00Z'));
    }

    public function test_invite_secret_only_first_success_accept_cookie_only_and_scoped_teacher_dto(): void
    {
        $session = $this->historySession();
        $uuid = (string) Str::uuid();
        $first = $this->govern($session, 'invite.create', [], 0, $uuid)->assertOk();
        $url = $first->json('invitation.url');
        $this->assertIsString($url);
        $this->govern($session, 'invite.create', [], 0, $uuid)->assertOk()->assertJsonMissingPath('invitation.url');
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $accepted = $this->accept($url)->assertOk()->assertJsonMissingPath('proof')->assertJsonMissingPath('cookie');
        $this->assertSame($session['id'], $accepted->json('sessionId'));
        $teacher = $this->getJson('/api/conduct/sessions/'.$session['id'])->assertOk()
            ->assertJsonPath('actor.kind', 'grant')->assertJsonPath('actor.isPresenter', false)
            ->assertJsonPath('actor.capabilities', ['moderate']);
        $this->assertStringEndsWith('/conduct/'.$session['id'].'/projector', $teacher->json('session.projectorUrl'));
        $json = $teacher->getContent();
        $this->assertStringContainsString('Authored private note', $json);
        $this->assertStringNotContainsString(TeachingSession::query()->findOrFail($session['id'])->projector_token, $json);
        $this->assertStringNotContainsString($this->historyOwner, $json);
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertNotFound();
        $this->getJson('/api/studio/sessions/'.$session['id'].'/collaboration')->assertNotFound();
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertNotFound();
        $this->get('/ru/conduct/'.$session['id'])->assertOk();
        $this->get('/de/conduct/'.$session['id'].'/control')->assertOk();
        $this->get('/ru/control/'.$session['id'])->assertNotFound();
        $this->accept($url)->assertOk()->assertJsonPath('grant.id', $accepted->json('grant.id'));
        $other = $this->historySession();
        $this->getJson('/api/conduct/sessions/'.$other['id'])->assertNotFound();
    }

    public function test_transfer_reclaim_and_stale_tabs_never_ack_old_epoch_commands(): void
    {
        $session = $this->historySession();
        $grant = $this->helper($session);
        $this->owner();
        $ownerUuid = (string) Str::uuid();
        $original = $session;
        $session = $this->runtimeCommand($session, 'wave', [], 0, $ownerUuid)->assertOk()->json('session');
        $session = $this->govern($session, 'presenter.transfer', ['grantId' => $grant['id']], 0)->assertOk()
            ->assertJsonPath('actor.isPresenter', false)->assertJsonPath('collaboration.controlEpoch', 1)->json('session');
        $this->runtimeCommand($session, 'pause', [], 1)->assertConflict()->assertJsonPath('error.code', 'presenter_required');
        $this->grantCommand($session, 'message.set', ['text' => 'Presenter'], 1)->assertOk();
        $session = $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->json('session');
        $session = $this->govern($session, 'presenter.reclaim', [], 1)->assertOk()
            ->assertJsonPath('collaboration.controlEpoch', 2)->json('session');
        $this->runtimeCommand($original, 'wave', [], 0, $ownerUuid)->assertConflict()
            ->assertJsonPath('error.code', 'control_conflict')->assertJsonMissingPath('acknowledgedCommandId');
        $this->grantCommand($session, 'wave', [], 1)->assertConflict()->assertJsonMissingPath('acknowledgedCommandId');
        $this->assertSame(5, SessionCommandReceipt::query()->where('teaching_session_id', $session['id'])->count());
    }

    public function test_revoked_grant_loses_teacher_projector_and_commands_without_session_response(): void
    {
        $session = $this->historySession();
        $grant = $this->helper($session);
        $this->owner();
        $session = $this->govern($session, 'presenter.transfer', ['grantId' => $grant['id']], 0)->assertOk()->json('session');
        $this->getJson('/api/conduct/sessions/'.$session['id'].'/projection')->assertOk();
        $session = $this->govern($session, 'grant.revoke', ['id' => $grant['id']], 1)->assertOk()
            ->assertJsonPath('collaboration.presenter.kind', 'vacant')->assertJsonPath('collaboration.controlEpoch', 2)->json('session');
        foreach (['', '/projection'] as $suffix) {
            $this->getJson('/api/conduct/sessions/'.$session['id'].$suffix)->assertNotFound()->assertJsonMissingPath('session');
        }
        $this->grantCommand($session, 'wave', [], 2)->assertNotFound()->assertJsonMissingPath('session');
        $this->get('/de/conduct/'.$session['id'].'/control')->assertNotFound();
        $this->runtimeCommand($session, 'wave', [], 2)->assertConflict();
        $this->govern($session, 'presenter.reclaim', [], 2)->assertOk()->assertJsonPath('actor.isPresenter', true);
    }

    public function test_expired_presenter_leaves_vacancy_and_finished_grant_read_is_time_limited(): void
    {
        $session = $this->historySession();
        $grant = $this->helper($session);
        $this->owner();
        $session = $this->govern($session, 'presenter.transfer', ['grantId' => $grant['id']], 0)->assertOk()->json('session');
        $session = $this->runtimeCommand($session, 'finish', [], 1)->assertOk()->json('session');
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertOk()->assertJsonPath('session.status', 'finished');
        $this->govern($session, 'invite.create', [], 1)->assertConflict();
        $this->govern($session, 'presenter.transfer', ['grantId' => $grant['id']], 1)->assertConflict();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T16:00:00Z'));
        $this->getJson('/api/conduct/sessions/'.$session['id'])->assertNotFound()->assertJsonMissingPath('session');
        $this->getJson('/api/studio/sessions/'.$session['id'].'/collaboration')->assertOk()
            ->assertJsonPath('collaboration.presenter.kind', 'vacant')->assertJsonPath('actor.isPresenter', false);
        $this->govern($session, 'grant.revoke', ['id' => $grant['id']], 1)->assertOk();
    }

    public function test_helper_can_moderate_and_acknowledge_but_cannot_present_finish_or_govern(): void
    {
        $session = $this->historySession();
        $this->helper($session);
        $this->grantCommand($session, 'wave', [], 0)->assertConflict();
        $this->grantCommand($session, 'finish', [], 0)->assertConflict();
        $this->grantCommand($session, 'block.open', ['blockId' => 'free'], 0)->assertConflict();
        $this->owner();
        $session = $this->runtimeCommand($session, 'block.open', ['blockId' => 'free'], 0)->assertOk()->json('session');
        $participant = $this->historyParticipant($session);
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'free', 'value' => ['text' => 'Private original']])->assertOk();
        $answerId = $this->getJson('/api/conduct/sessions/'.$session['id'])->assertOk()->json('session.answers.0.id');
        $this->grantCommand($session, 'answer.moderate', ['answerId' => $answerId, 'expectedAnswerRevision' => 1, 'status' => 'approved'], 0)->assertOk();
        $session = $this->getJson('/api/conduct/sessions/'.$session['id'])->assertOk()->json('session');
        $session = $this->grantCommand($session, 'role.assign', ['blockId' => 'roles', 'participantId' => $participant['id'], 'roleId' => 'a'], 0)->assertOk()->json('session');
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'signals', 'value' => ['ready' => false, 'question' => true]])->assertOk();
        $this->grantCommand($session, 'signal.ack', ['blockId' => 'signals', 'participantId' => $participant['id']], 0)->assertOk();
        $answers = $this->getJson('/api/participation/'.$session['id'])->assertOk()->json('session.ownAnswers');
        $this->assertTrue(collect($answers)->firstWhere('blockId', 'signals')['acknowledged']);
    }

    public function test_legacy_owner_endpoint_without_epoch_fails_after_any_handoff(): void
    {
        $session = $this->historySession();
        $grant = $this->helper($session);
        $this->owner();
        $session = $this->govern($session, 'presenter.transfer', ['grantId' => $grant['id']], 0)->assertOk()->json('session');
        $session = $this->govern($session, 'presenter.reclaim', [], 1)->assertOk()->json('session');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', ['commandId' => (string) Str::uuid(), 'expectedRevision' => $session['revision'], 'action' => 'wave', 'payload' => []])
            ->assertConflict()->assertJsonPath('error.code', 'control_conflict');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/stage', ['expectedRevision' => $session['revision'], 'stageId' => 'second'])->assertConflict();
    }

    public function test_new_writes_require_csrf_and_strict_control_epoch_without_recording_rejections(): void
    {
        $session = $this->historySession();
        $grant = $this->helper($session);
        $this->owner();
        $session = $this->govern($session, 'presenter.transfer', ['grantId' => $grant['id']], 0)->assertOk()->json('session');
        foreach ([null, '1', -1, 1.5] as $epoch) {
            $body = $this->body($session, 'wave', [], 1);
            $body['controlEpoch'] = $epoch;
            $this->postJson('/api/conduct/sessions/'.$session['id'].'/commands', $body)->assertUnprocessable();
        }
        $receiptsBefore = SessionCommandReceipt::query()->count();
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        $this->withMiddleware();
        try {
            $csrf = Str::random(40);
            $this->withSession(['_token' => $csrf]);
            $this->postJson('/api/conduct/sessions/'.$session['id'].'/commands', $this->body($session, 'wave', [], 1), ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->postJson('/api/studio/sessions/'.$session['id'].'/collaboration/commands', $this->body($session, 'presenter.reclaim', [], 1), ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->postJson('/api/teacher-invitations/accept', ['token' => (string) Str::uuid(), 'displayName' => 'Teacher'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->assertSame($receiptsBefore, SessionCommandReceipt::query()->count());
            $this->postJson('/api/conduct/sessions/'.$session['id'].'/commands', $this->body($session, 'wave', [], 1), ['Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => $csrf])->assertOk();
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    private function helper(array &$session): array
    {
        $response = $this->govern($session, 'invite.create', [], 0)->assertOk();
        $session = $response->json('session');

        return $this->accept($response->json('invitation.url'))->assertOk()->json('grant');
    }

    private function accept(string $url): TestResponse
    {
        return $this->postJson('/api/teacher-invitations/accept', ['token' => substr($url, strpos($url, '#token=') + 7), 'displayName' => 'Helper']);
    }

    private function owner(): void
    {
        $this->withSession(['studio_owner_key' => $this->historyOwner]);
    }

    private function govern(array $session, string $action, array $payload, int $epoch, ?string $uuid = null): TestResponse
    {
        return $this->postJson('/api/studio/sessions/'.$session['id'].'/collaboration/commands', $this->body($session, $action, $payload, $epoch, $uuid));
    }

    private function runtimeCommand(array $session, string $action, array $payload, int $epoch, ?string $uuid = null): TestResponse
    {
        return $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', $this->body($session, $action, $payload, $epoch, $uuid));
    }

    private function grantCommand(array $session, string $action, array $payload, int $epoch): TestResponse
    {
        return $this->postJson('/api/conduct/sessions/'.$session['id'].'/commands', $this->body($session, $action, $payload, $epoch));
    }

    private function body(array $session, string $action, array $payload, int $epoch, ?string $uuid = null): array
    {
        return ['commandId' => $uuid ?? (string) Str::uuid(), 'expectedRevision' => $session['revision'], 'controlEpoch' => $epoch,
            'action' => $action, 'payload' => $payload];
    }
}
