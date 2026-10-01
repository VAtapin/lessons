<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\History\RetentionService;
use App\Application\Operations\BackupFailure;
use App\Application\Operations\OperationsLock;
use App\Application\Shared\ApiProblem;
use App\Models\OperationRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ScheduledRetention implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 82800;

    public function __construct(public readonly int $batch = 100, public readonly bool $dryRun = true) {}

    public function uniqueId(): string
    {
        return 'lessons-retention';
    }

    public function handle(RetentionService $retention, OperationsLock $lock): void
    {
        // Recheck gates in the worker, including old jobs queued before configuration changed.
        if (config('operations.retention.enabled') !== true || config('operations.retention.restore_verified') !== true) {
            return;
        }
        try {
            $ran = $lock->run(function () use ($retention): void {
                // Deployment holds this same lock before maintenance and schema changes.
                if (! app()->isDownForMaintenance()) {
                    $this->clean($retention);
                }
            });
        } catch (BackupFailure) {
            throw new ApiProblem('retention_lock_failed', 503);
        }
        if (! $ran) {
            Log::info('Scheduled lesson retention deferred: another operation owns the private lock.');
        }
    }

    private function clean(RetentionService $retention): void
    {
        if (config('operations.retention.enabled') !== true || config('operations.retention.restore_verified') !== true) {
            return;
        }
        $configured = config('operations.retention.batch');
        if (! is_int($configured) || $configured < 1 || $configured > 1000 || $this->batch < 1 || $this->batch > 1000) {
            return;
        }
        $dryRun = $this->dryRun || config('operations.retention.dry_run') !== false;
        try {
            // No cleanup starts if its operational execution cannot be recorded.
            $run = OperationRun::create(['operation' => 'retention', 'status' => 'running', 'dry_run' => $dryRun,
                'counts' => null, 'error_code' => null, 'started_at' => CarbonImmutable::now('UTC'), 'finished_at' => null]);
        } catch (Throwable) {
            throw new ApiProblem('operation_record_failed', 503);
        }
        try {
            $counts = OperationRun::aggregateCounts($retention->run($dryRun, min($this->batch, $configured)));
        } catch (Throwable) {
            try {
                // Earlier batches may already be committed; unknown counts are not reported as zero.
                $run->update(['status' => 'failed', 'counts' => null, 'error_code' => 'retention_failed', 'finished_at' => CarbonImmutable::now('UTC')]);
            } catch (Throwable) {
                throw new ApiProblem('operation_record_failed', 503);
            }
            throw new ApiProblem('retention_failed', 503);
        }
        try {
            $run->update(['status' => 'succeeded', 'counts' => $counts, 'finished_at' => CarbonImmutable::now('UTC')]);
        } catch (Throwable) {
            throw new ApiProblem('operation_record_failed', 503);
        }
        Log::info('Scheduled lesson retention completed.', ['dryRun' => $dryRun, 'counts' => $counts]);
    }
}
