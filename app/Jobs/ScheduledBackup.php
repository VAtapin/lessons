<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Operations\BackupBundle;
use App\Application\Operations\BackupFailure;
use App\Application\Operations\OperationsLock;
use App\Application\Shared\ApiProblem;
use App\Models\OperationRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ScheduledBackup implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 82800;

    public function uniqueId(): string
    {
        return 'lessons-backup';
    }

    public function handle(BackupBundle $backup, DatabaseManager $databases, OperationsLock $lock): void
    {
        if (config('operations.backup.enabled') !== true) {
            return;
        }
        try {
            $ran = $lock->run(function () use ($backup, $databases): void {
                // Check maintenance only while holding the same lock as the entire deployment.
                if (config('operations.backup.enabled') === true && ! app()->isDownForMaintenance()) {
                    $this->create($backup, $databases);
                }
            });
        } catch (BackupFailure) {
            throw new ApiProblem('backup_lock_failed', 503);
        }
        if (! $ran) {
            Log::info('Scheduled lesson backup deferred: another operation owns the private lock.');
        }
    }

    private function create(BackupBundle $backup, DatabaseManager $databases): void
    {
        try {
            $run = OperationRun::create(['operation' => 'backup', 'status' => 'running', 'dry_run' => false,
                'counts' => null, 'error_code' => null, 'started_at' => CarbonImmutable::now('UTC'), 'finished_at' => null]);
        } catch (Throwable) {
            throw new ApiProblem('operation_record_failed', 503);
        }
        try {
            $disk = config('filesystems.disks.'.config('lessons.media.disk'));
            $directory = config('operations.backup.directory');
            if (! is_array($disk) || ($disk['driver'] ?? null) !== 'local' || ! is_string($disk['root'] ?? null) || ! is_string($directory)) {
                throw new BackupFailure('Background backup requires private local media and an absolute destination.');
            }
            $bundle = $backup->create($databases->connection(), $directory, base_path(), $disk['root'], 300);
            $manifest = json_decode(file_get_contents($bundle.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $counts = OperationRun::aggregateCounts(['databaseBytes' => $manifest['database']['bytes'],
                'mediaBytes' => array_sum(array_column($manifest['media'], 'bytes')), 'mediaVersions' => count($manifest['media'])], 'backup');
        } catch (Throwable) {
            try {
                $run->update(['status' => 'failed', 'counts' => null, 'error_code' => 'backup_failed', 'finished_at' => CarbonImmutable::now('UTC')]);
            } catch (Throwable) {
                throw new ApiProblem('operation_record_failed', 503);
            }
            throw new ApiProblem('backup_failed', 503);
        }
        try {
            $run->update(['status' => 'succeeded', 'counts' => $counts, 'finished_at' => CarbonImmutable::now('UTC')]);
        } catch (Throwable) {
            throw new ApiProblem('operation_record_failed', 503);
        }
        Log::info('Scheduled lesson backup completed.', ['counts' => $counts]);
    }
}
