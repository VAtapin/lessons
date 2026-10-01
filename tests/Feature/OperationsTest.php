<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Account\AccountIdentity;
use App\Application\History\RetentionService;
use App\Application\Operations\OperationRunRetention;
use App\Application\Operations\OperationsLock;
use App\Application\Shared\ApiProblem;
use App\Jobs\ScheduledRetention;
use App\Models\OperationRun;
use App\Models\TeachingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Support\HistoryFixture;
use Tests\Support\OperationsLockFixture;
use Tests\TestCase;

final class OperationsTest extends TestCase
{
    use HistoryFixture, OperationsLockFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOperationsLock();
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        $this->removeOperationsLock();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_disabled_or_invalid_worker_gates_do_not_create_execution_records(): void
    {
        foreach ([[false, false, 100], [true, false, 100], [false, true, 100], [true, true, 0], [true, true, 1001]] as [$enabled, $verified, $batch]) {
            config(['operations.retention.enabled' => $enabled, 'operations.retention.restore_verified' => $verified, 'operations.retention.batch' => $batch]);
            (new ScheduledRetention)->handle(app(RetentionService::class), app(OperationsLock::class));
            $this->assertDatabaseCount('operation_runs', 0);
        }
    }

    public function test_successful_dry_run_records_real_utc_execution_and_numeric_counts_without_details(): void
    {
        $this->enable();
        (new ScheduledRetention)->handle(app(RetentionService::class), app(OperationsLock::class));
        $run = OperationRun::sole();
        $this->assertSame('retention', $run->operation);
        $this->assertSame('succeeded', $run->status);
        $this->assertTrue($run->dry_run);
        $this->assertNull($run->error_code);
        $this->assertSame('2026-10-01T12:00:00+00:00', $run->started_at->toIso8601String());
        $this->assertSame($run->started_at->toIso8601String(), $run->finished_at->toIso8601String());
        $this->assertEqualsCanonicalizing(OperationRun::COUNT_KEYS, array_keys($run->counts));
        $this->assertTrue(collect($run->counts)->every(fn ($count) => is_int($count) && $count === 0));
    }

    public function test_deployment_or_backup_lock_defers_cleanup_without_execution_record(): void
    {
        $this->enable(false);
        $this->historyIdentity();
        $this->expiredSessions(1);
        $lock = app(OperationsLock::class);
        $this->assertTrue($lock->run(function () use ($lock): void {
            (new ScheduledRetention(100, false))->handle(app(RetentionService::class), $lock);
            $this->assertSame(1, TeachingSession::count());
            $this->assertDatabaseCount('operation_runs', 0);
        }));
        (new ScheduledRetention(100, false))->handle(app(RetentionService::class), $lock);
        $this->assertSame(0, TeachingSession::count());
        $this->assertSame('succeeded', OperationRun::sole()->status);
    }

    public function test_worker_rechecks_maintenance_under_shared_lock_before_cleanup_or_record(): void
    {
        $this->enable(false);
        $this->historyIdentity();
        $this->expiredSessions(1);
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->once()->andReturnTrue();
        $this->app->instance(MaintenanceMode::class, $maintenance);
        (new ScheduledRetention(100, false))->handle(app(RetentionService::class), app(OperationsLock::class));
        $this->assertSame(1, TeachingSession::count());
        $this->assertDatabaseCount('operation_runs', 0);
    }

    public function test_partial_failure_is_visible_generically_and_completed_batches_remain_committed(): void
    {
        $this->enable(false);
        $this->historyIdentity();
        $this->expiredSessions(2);
        $dispatcher = TeachingSession::getEventDispatcher();
        TeachingSession::setEventDispatcher(clone $dispatcher);
        $deletions = 0;
        TeachingSession::deleting(function () use (&$deletions): void {
            if (++$deletions === 2) {
                throw new RuntimeException('private SQL password answer pupil-name');
            }
        });
        try {
            try {
                (new ScheduledRetention(100, false))->handle(app(RetentionService::class), app(OperationsLock::class));
                $this->fail('Retention failure must reach the failed-job handler.');
            } catch (ApiProblem $problem) {
                $this->assertSame('retention_failed', $problem->problemCode);
                $this->assertSame('retention_failed', $problem->getMessage());
                $this->assertNull($problem->getPrevious());
            }
            $this->assertSame(1, TeachingSession::count());
            $run = OperationRun::sole();
            $this->assertSame('failed', $run->status);
            $this->assertSame('retention_failed', $run->error_code);
            $this->assertNull($run->counts, 'Partial committed counts are unknown on failure, not zero.');
            $this->assertNotNull($run->finished_at);
            $this->assertStringNotContainsString('private SQL', json_encode($run->getAttributes()));
        } finally {
            TeachingSession::setEventDispatcher($dispatcher);
        }
    }

    public function test_record_storage_failure_prevents_cleanup_and_never_claims_success(): void
    {
        $this->enable(false);
        $this->historyIdentity();
        $this->expiredSessions(1);
        $dispatcher = OperationRun::getEventDispatcher();
        OperationRun::setEventDispatcher(clone $dispatcher);
        OperationRun::creating(fn () => throw new RuntimeException('private database password failure'));
        try {
            try {
                (new ScheduledRetention(100, false))->handle(app(RetentionService::class), app(OperationsLock::class));
                $this->fail('Missing execution record must prevent writes.');
            } catch (ApiProblem $problem) {
                $this->assertSame('operation_record_failed', $problem->problemCode);
                $this->assertNull($problem->getPrevious());
            }
            $this->assertSame(1, TeachingSession::count());
            $this->assertDatabaseCount('operation_runs', 0);
        } finally {
            OperationRun::setEventDispatcher($dispatcher);
        }
    }

    public function test_finished_operation_history_expires_in_bounded_batches_after_30_days_only(): void
    {
        $old = '2026-09-01 12:00:00';
        foreach (['succeeded', 'failed', 'running'] as $status) {
            $this->operationFixture($status, $old, $status === 'running' ? null : $old);
        }
        $recent = $this->operationFixture('succeeded', $old, '2026-09-01 12:00:00.000001');
        $retention = app(OperationRunRetention::class);
        $this->assertSame(1, $retention->run(true, 1));
        $this->assertDatabaseCount('operation_runs', 4);
        $this->assertSame(1, $retention->run(false, 1));
        $this->assertSame(1, $retention->run(false, 1));
        $this->assertSame(0, $retention->run(false, 1));
        $this->assertSame(1, OperationRun::where('status', 'running')->count());
        $this->assertNotNull($recent->fresh());
    }

    public function test_record_update_failure_reports_unknown_state_after_real_completed_cleanup(): void
    {
        $this->enable(false);
        $this->historyIdentity();
        $this->expiredSessions(1);
        $dispatcher = OperationRun::getEventDispatcher();
        OperationRun::setEventDispatcher(clone $dispatcher);
        OperationRun::updating(fn () => throw new RuntimeException('private failed SQL record update'));
        try {
            try {
                (new ScheduledRetention(100, false))->handle(app(RetentionService::class), app(OperationsLock::class));
                $this->fail('Record update failure must not report complete recorded success.');
            } catch (ApiProblem $problem) {
                $this->assertSame('operation_record_failed', $problem->problemCode);
                $this->assertNull($problem->getPrevious());
            }
            $this->assertSame(0, TeachingSession::count());
            $run = OperationRun::sole();
            $this->assertSame('running', $run->status);
            $this->assertNull($run->counts);
            $this->assertNull($run->finished_at);
        } finally {
            OperationRun::setEventDispatcher($dispatcher);
        }
    }

    public function test_backup_execution_history_uses_the_same_terminal_30_day_policy(): void
    {
        foreach (['succeeded', 'failed', 'running'] as $status) {
            OperationRun::create(['operation' => 'backup', 'status' => $status, 'dry_run' => false, 'counts' => null,
                'error_code' => $status === 'failed' ? 'backup_failed' : null, 'started_at' => '2026-09-01 12:00:00',
                'finished_at' => $status === 'running' ? null : '2026-09-01 12:00:00']);
        }
        $this->assertSame(1, app(OperationRunRetention::class)->run(true, 1));
        $this->assertDatabaseCount('operation_runs', 3);
        $this->assertSame(1, app(OperationRunRetention::class)->run(false, 1));
        $this->assertSame(1, app(OperationRunRetention::class)->run(false, 1));
        $this->assertSame(0, app(OperationRunRetention::class)->run(false, 1));
        $this->assertSame('running', OperationRun::sole()->status);
    }

    public function test_admin_endpoint_denies_guest_account_unverified_and_revoked_admin(): void
    {
        $this->getJson('/api/admin/operations')->assertUnauthorized();
        foreach ([[false, true], [true, false]] as [$admin, $verified]) {
            $this->identity($admin, $verified);
            $this->getJson('/api/admin/operations')->assertForbidden();
        }
        $user = $this->identity(true, true);
        $this->getJson('/api/admin/operations')->assertOk();
        $user->forceFill(['is_admin' => false])->save();
        $this->getJson('/api/admin/operations')->assertForbidden();
        $user->forceFill(['is_admin' => true, 'password' => 'revoked-new-hash'])->save();
        $this->getJson('/api/admin/operations')->assertUnauthorized();
    }

    public function test_admin_sees_recent_runs_and_only_safe_retention_failed_job_metadata(): void
    {
        $this->identity(true, true);
        $this->enable();
        for ($index = 0; $index < 22; $index++) {
            $this->operationFixture('failed', '2026-10-01 12:00:00', '2026-10-01 12:00:00');
            DB::table('failed_jobs')->insert(['uuid' => Str::uuid(), 'connection' => 'private connection string', 'queue' => 'retention',
                'payload' => 'private password answer SQL payload', 'exception' => 'private exception details', 'failed_at' => '2026-10-01 12:00:00']);
        }
        DB::table('failed_jobs')->insert(['uuid' => Str::uuid(), 'connection' => 'database', 'queue' => 'unrelated-private-queue', 'payload' => 'private unrelated', 'exception' => 'private', 'failed_at' => '2026-10-01 12:00:00']);
        $run = OperationRun::first();
        $run->update(['started_at' => '2026-10-01 12:00:01', 'counts' => ['sessionsDeleted' => 1, 'answer' => 'private pupil text', 'receiptsDeleted' => 'private password'], 'error_code' => 'private exception']);
        $response = $this->getJson('/api/admin/operations')->assertOk()->assertJsonCount(20, 'runs')->assertJsonCount(20, 'failedJobs')
            ->assertJsonPath('retention.enabled', true)->assertJsonPath('retention.restoreVerified', true)->assertJsonPath('retention.dryRun', true)
            ->assertJsonPath('runs.0.counts.sessionsDeleted', 1)->assertJsonPath('runs.0.errorCode', 'retention_failed');
        $this->assertStringNotContainsString('private', $response->getContent());
        $this->assertStringNotContainsString('payload', $response->getContent());
        $this->assertSame(['id', 'failedAt', 'queue'], array_keys($response->json('failedJobs.0')));
        $this->assertSame(['id', 'operation', 'status', 'dryRun', 'counts', 'errorCode', 'startedAt', 'finishedAt'], array_keys($response->json('runs.0')));
    }

    public function test_admin_backup_summary_omits_paths_credentials_and_nonaggregate_metadata(): void
    {
        $this->identity(true, true);
        config(['operations.backup.enabled' => true, 'operations.backup.time' => '02:30', 'operations.backup.directory' => '/private-backup-path']);
        OperationRun::create(['operation' => 'backup', 'status' => 'failed', 'dry_run' => false,
            'counts' => ['databaseBytes' => 100, 'mediaBytes' => 30, 'mediaVersions' => 2, 'source' => 'private source', 'answer' => 'private answer', 'sessionsDeleted' => 12],
            'error_code' => 'private SQL credentials exception', 'started_at' => now(), 'finished_at' => now()]);
        DB::table('failed_jobs')->insert(['uuid' => Str::uuid(), 'connection' => 'private database connection', 'queue' => 'backups', 'payload' => 'private payload', 'exception' => 'private SQL exception', 'failed_at' => now()]);
        $response = $this->getJson('/api/admin/operations')->assertOk()->assertJsonPath('backup.enabled', true)->assertJsonPath('backup.time', '02:30')
            ->assertJsonPath('runs.0.operation', 'backup')->assertJsonPath('runs.0.errorCode', 'backup_failed')
            ->assertJsonPath('runs.0.counts', ['databaseBytes' => 100, 'mediaBytes' => 30, 'mediaVersions' => 2])->assertJsonPath('failedJobs.0.queue', 'backups');
        $this->assertStringNotContainsString('private', $response->getContent());
        $this->assertStringNotContainsString('sessionsDeleted', $response->getContent());
    }

    private function enable(bool $dryRun = true): void
    {
        config(['operations.retention.enabled' => true, 'operations.retention.restore_verified' => true, 'operations.retention.dry_run' => $dryRun, 'operations.retention.batch' => 100]);
    }

    private function expiredSessions(int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $session = $this->historySession();
            $this->historyCommand($session, 'finish');
            DB::table('teaching_sessions')->where('id', $session['id'])->update(['finished_at' => '2026-09-01 12:00:00']);
        }
    }

    private function operationFixture(string $status, string $started, ?string $finished): OperationRun
    {
        return OperationRun::create(['operation' => 'retention', 'status' => $status, 'dry_run' => true, 'counts' => null,
            'error_code' => $status === 'failed' ? 'retention_failed' : null, 'started_at' => $started, 'finished_at' => $finished]);
    }

    private function identity(bool $admin, bool $verified): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
        $user->forceFill(['is_admin' => $admin])->save();
        AccountIdentity::owner($user);
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);

        return $user;
    }
}
