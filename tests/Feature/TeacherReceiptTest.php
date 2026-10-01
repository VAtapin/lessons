<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Collaboration\TeacherActor;
use App\Application\Collaboration\TeacherReceipts;
use App\Application\Runtime\RuntimeCommands;
use App\Application\Shared\ApiProblem;
use App\Models\SessionCommandReceipt;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class TeacherReceiptTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    public function test_receipt_is_bound_to_actor_and_epoch_and_rejects_another_grant(): void
    {
        $this->historyIdentity();
        $state = $this->historySession();
        $session = TeachingSession::query()->findOrFail($state['id']);
        $receipts = app(TeacherReceipts::class);
        $actor = TeacherActor::grant((string) Str::uuid(), (string) Str::uuid());
        $uuid = (string) Str::uuid();
        $fingerprint = app(RuntimeCommands::class)->fingerprint(1, 'wave', [], ['controlEpoch' => 0]);
        $receipts->record($session, $actor, $uuid, $fingerprint);
        $this->assertTrue($receipts->replay($session, $actor, $uuid, $fingerprint));
        $this->rejects(fn () => $receipts->replay($session, TeacherActor::grant((string) Str::uuid(), (string) Str::uuid()), $uuid, $fingerprint));
        $this->rejects(fn () => $receipts->replay($session, $actor, $uuid, hash('sha256', 'different body')));
        $session->presenter_epoch = 2;
        $this->rejects(fn () => $receipts->replay($session, $actor, $uuid, $fingerprint));
        $this->rejects(fn () => $receipts->assertEpoch($session, 0));
        $this->rejects(fn () => $receipts->assertEpoch($session, null));
        $receipts->assertEpoch($session, 2);
        $this->assertDatabaseCount('session_command_receipts', 1);
    }

    public function test_legacy_null_receipts_only_replay_for_owner_at_epoch_zero(): void
    {
        $this->historyIdentity();
        $state = $this->historySession();
        $session = TeachingSession::query()->findOrFail($state['id']);
        $receipts = app(TeacherReceipts::class);
        $uuid = (string) Str::uuid();
        $fingerprint = app(RuntimeCommands::class)->fingerprint(1, 'wave', []);
        SessionCommandReceipt::create(['teaching_session_id' => $session->id, 'command_id' => $uuid, 'fingerprint' => $fingerprint]);
        $actor = TeacherActor::owner($this->historyOwner);
        $this->assertTrue($receipts->replay($session, $actor, $uuid, $fingerprint));
        $receipts->assertEpoch($session, null);
        $this->rejects(fn () => $receipts->replay($session, TeacherActor::grant((string) Str::uuid(), (string) Str::uuid()), $uuid, $fingerprint));
        $session->presenter_epoch = 1;
        $this->rejects(fn () => $receipts->replay($session, $actor, $uuid, $fingerprint));
        $this->rejects(fn () => $receipts->assertEpoch($session, null));
    }

    private function rejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Stale or different actor must not receive a replay.');
        } catch (ApiProblem $problem) {
            $this->assertSame(409, $problem->status);
        }
    }
}
