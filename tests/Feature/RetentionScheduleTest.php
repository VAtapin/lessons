<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\History\RetentionService;
use App\Application\Operations\OperationsLock;
use App\Jobs\ScheduledRetention;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\HistoryFixture;
use Tests\Support\OperationsLockFixture;
use Tests\TestCase;

final class RetentionScheduleTest extends TestCase
{
    use HistoryFixture, OperationsLockFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOperationsLock();
    }

    protected function tearDown(): void
    {
        $this->removeOperationsLock();
        parent::tearDown();
    }

    public function test_automatic_schedule_is_disabled_without_restore_acceptance(): void
    {
        foreach ([[false, false], [true, false], [false, true]] as [$enabled, $verified]) {
            config(['operations.retention.enabled' => $enabled, 'operations.retention.restore_verified' => $verified]);
            $this->assertSame([], $this->schedule()->events());
        }
    }

    public function test_accepted_schedule_is_daily_utc_database_queue_with_overlap_guard(): void
    {
        config(['operations.retention.enabled' => true, 'operations.retention.restore_verified' => true]);
        $events = $this->schedule()->events();
        $this->assertCount(1, $events);
        $this->assertSame('15 3 * * *', $events[0]->expression);
        $this->assertSame('UTC', $events[0]->timezone);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame('lessons-retention', $events[0]->description);
        $job = new ScheduledRetention;
        $this->assertTrue($job->dryRun);
        $this->assertSame(100, $job->batch);
        $this->assertSame(1, $job->tries);
    }

    public function test_invalid_schedule_settings_fail_closed(): void
    {
        config(['operations.retention.enabled' => true, 'operations.retention.restore_verified' => true]);
        foreach ([[0, '03:15'], [1001, '03:15'], [100, '25:01'], [100, '* * * * *']] as [$batch, $time]) {
            config(['operations.retention.batch' => $batch, 'operations.retention.time' => $time]);
            $this->assertSame([], $this->schedule()->events());
        }
    }

    public function test_worker_rechecks_gates_and_logs_counts_only_in_default_dry_run(): void
    {
        Log::spy();
        config(['operations.retention.enabled' => true, 'operations.retention.restore_verified' => false]);
        (new ScheduledRetention)->handle(app(RetentionService::class), app(OperationsLock::class));
        Log::shouldNotHaveReceived('info');
        config(['operations.retention.restore_verified' => true]);
        (new ScheduledRetention)->handle(app(RetentionService::class), app(OperationsLock::class));
        Log::shouldHaveReceived('info')->once()->withArgs(fn ($message, $context) => $message === 'Scheduled lesson retention completed.' && $context['dryRun'] === true && array_sum($context['counts']) === 0);
    }

    public function test_old_queued_write_job_respects_current_dry_run_and_smaller_batch(): void
    {
        $this->historyIdentity();
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
        try {
            for ($index = 0; $index < 2; $index++) {
                $session = $this->historySession();
                $this->historyCommand($session, 'finish');
                DB::table('teaching_sessions')->where('id', $session['id'])->update(['finished_at' => '2026-09-01 12:00:00']);
            }
            config(['operations.retention.enabled' => true, 'operations.retention.restore_verified' => true, 'operations.retention.batch' => 1, 'operations.retention.dry_run' => true]);
            $job = new ScheduledRetention(100, false);
            $job->handle(app(RetentionService::class), app(OperationsLock::class));
            $this->assertSame(2, TeachingSession::count());
            config(['operations.retention.dry_run' => false]);
            $job->handle(app(RetentionService::class), app(OperationsLock::class));
            $this->assertSame(1, TeachingSession::count());
            config(['operations.retention.enabled' => false]);
            $job->handle(app(RetentionService::class), app(OperationsLock::class));
            $this->assertSame(1, TeachingSession::count());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function schedule(): Schedule
    {
        $schedule = new Schedule;
        $this->app->instance(Schedule::class, $schedule);
        require base_path('routes/console.php');

        return $schedule;
    }
}
