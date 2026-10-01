<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupBundle;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\DatabaseDumpProcess;
use App\Application\Operations\OperationsLock;
use App\Application\Operations\PrivateBackupFiles;
use App\Application\Shared\ApiProblem;
use App\Jobs\ScheduledBackup;
use App\Models\OperationRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/** The dump executable is faked here; real dump/import evidence remains BackupRestoreTest. */
final class ScheduledBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = str_replace('\\', '/', sys_get_temp_dir()).'/lessons-scheduled-backup-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        config(['operations.backup.directory' => $this->directory.'/bundles', 'filesystems.disks.media.root' => $this->directory.'/media']);
    }

    protected function tearDown(): void
    {
        $this->removeFixture($this->directory);
        parent::tearDown();
    }

    public function test_backup_is_off_by_default_and_worker_rechecks_gate_before_record_or_io(): void
    {
        $this->assertFalse(config('operations.backup.enabled'));
        (new ScheduledBackup)->handle($this->bundle(), $this->manager(false), $this->lock());
        $this->assertDatabaseCount('operation_runs', 0);
        $this->assertDirectoryDoesNotExist($this->directory.'/bundles');
        $this->assertSame([], $this->schedule()->events());
    }

    public function test_daily_backup_uses_its_dedicated_long_reservation_queue(): void
    {
        config(['operations.backup.enabled' => true]);
        $events = $this->schedule()->events();
        $this->assertCount(1, $events);
        $this->assertSame('30 2 * * *', $events[0]->expression);
        $this->assertSame('UTC', $events[0]->timezone);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame('lessons-backup', $events[0]->description);
        Queue::fake();
        $events[0]->run($this->app);
        Queue::assertPushed(ScheduledBackup::class, fn ($job) => $job->connection === 'operations-backups' && $job->queue === 'backups' && $job->timeout < config('queue.connections.operations-backups.retry_after') && $job->tries === 1);
        $this->assertSame(90, config('queue.connections.database.retry_after'));
        config(['operations.backup.time' => '25:00']);
        $this->assertSame([], $this->schedule()->events());
    }

    public function test_worker_skips_maintenance_without_mutating_application_or_backup_state(): void
    {
        config(['operations.backup.enabled' => true]);
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->once()->andReturnTrue();
        $this->app->instance(MaintenanceMode::class, $maintenance);
        (new ScheduledBackup)->handle($this->bundle(), $this->manager(false), $this->lock());
        $this->assertDatabaseCount('operation_runs', 0);
        $this->assertDirectoryDoesNotExist($this->directory.'/bundles');
    }

    public function test_background_backup_records_start_and_success_aggregate_counts_only(): void
    {
        config(['operations.backup.enabled' => true]);
        $started = false;
        (new ScheduledBackup)->handle($this->bundle(function () use (&$started): void {
            $record = OperationRun::sole();
            $this->assertSame('running', $record->status);
            $this->assertNull($record->finished_at);
            $this->assertNull($record->counts);
            $started = true;
        }), $this->manager(), $this->lock());
        $this->assertTrue($started);
        $record = OperationRun::sole();
        $this->assertSame('backup', $record->operation);
        $this->assertSame('succeeded', $record->status);
        $this->assertFalse($record->dry_run);
        $this->assertNull($record->error_code);
        $this->assertNotNull($record->finished_at);
        $this->assertSame(['databaseBytes' => 52, 'mediaBytes' => 0, 'mediaVersions' => 0], $record->counts);
        $this->assertCount(1, glob($this->directory.'/bundles/*'));
        $this->assertStringNotContainsString($this->directory, json_encode($record->getAttributes()));
    }

    public function test_failed_background_backup_preserves_previous_bundle_and_rethrows_only_generic_error(): void
    {
        config(['operations.backup.enabled' => true]);
        (new ScheduledBackup)->handle($this->bundle(), $this->manager(), $this->lock());
        $previous = glob($this->directory.'/bundles/*');
        try {
            (new ScheduledBackup)->handle($this->bundle(fn () => throw new RuntimeException('private SQL password exception answer')), $this->manager(), $this->lock());
            $this->fail('Background failure must reach the failed-job handler.');
        } catch (ApiProblem $problem) {
            $this->assertSame('backup_failed', $problem->problemCode);
            $this->assertNull($problem->getPrevious());
        }
        $record = OperationRun::where('status', 'failed')->sole();
        $this->assertSame('backup_failed', $record->error_code);
        $this->assertNull($record->counts);
        $this->assertNotNull($record->finished_at);
        $this->assertSame($previous, glob($this->directory.'/bundles/*'));
        $this->assertStringNotContainsString('private SQL', json_encode($record->getAttributes()));
    }

    private function bundle(?callable $duringDump = null): BackupBundle
    {
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldReceive('run')->andReturnUsing(function (array $arguments) use ($duringDump): void {
            $this->assertNotContains('--databases', $arguments);
            if ($duringDump !== null) {
                $duringDump();
            }
            $result = collect($arguments)->first(fn ($argument) => str_starts_with($argument, '--result-file='));
            file_put_contents(substr($result, strlen('--result-file=')), "-- synthetic scheduled backup fixture, not real SQL\n");
        });
        $finder = Mockery::mock(ExecutableFinder::class);
        $finder->shouldReceive('find')->andReturn('/trusted/mariadb-dump');

        return new BackupBundle(new DatabaseBackup($process, $finder), new PrivateBackupFiles);
    }

    private function manager(bool $called = true): DatabaseManager
    {
        $database = DB::connection();
        if ($database->getDriverName() === 'sqlite') {
            $database = new SQLiteConnection($database->getPdo(), '', '', ['driver' => 'mysql', 'database' => 'lessons_test', 'username' => 'synthetic', 'password' => 'synthetic-test-only']);
        }
        $manager = Mockery::mock(DatabaseManager::class);
        if ($called) {
            $manager->shouldReceive('connection')->once()->andReturn($database);
        } else {
            $manager->shouldNotReceive('connection');
        }

        return $manager;
    }

    private function schedule(): Schedule
    {
        $schedule = new Schedule;
        $this->app->instance(Schedule::class, $schedule);
        require base_path('routes/console.php');

        return $schedule;
    }

    private function lock(): OperationsLock
    {
        return new OperationsLock(new PrivateBackupFiles, $this->directory.'/operations');
    }

    public function test_an_active_deployment_lock_defers_background_backup_without_success_record(): void
    {
        config(['operations.backup.enabled' => true]);
        $lock = $this->lock();
        $this->assertTrue($lock->run(function (): void {
            (new ScheduledBackup)->handle($this->bundle(), $this->manager(false), $this->lock());
            $this->assertDatabaseCount('operation_runs', 0);
            $this->assertDirectoryDoesNotExist($this->directory.'/bundles');
        }));
        (new ScheduledBackup)->handle($this->bundle(), $this->manager(), $this->lock());
        $this->assertSame('succeeded', OperationRun::sole()->status);
    }

    private function removeFixture(string $path): void
    {
        $root = str_replace('\\', '/', realpath($this->directory));
        $actual = str_replace('\\', '/', realpath($path));
        $this->assertFalse(is_link($path));
        $this->assertTrue($actual === $root || str_starts_with($actual, $root.'/'));
        if (is_dir($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $name) {
                $this->removeFixture($path.'/'.$name);
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}
