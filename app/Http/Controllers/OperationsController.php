<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Catalog\AdminAccess;
use App\Models\OperationRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class OperationsController
{
    public function __construct(private AdminAccess $access) {}

    public function index(Request $request): JsonResponse
    {
        $this->access->require($request->user());
        $runs = OperationRun::query()->whereIn('operation', ['retention', 'backup'])->orderByDesc('started_at')->orderByDesc('id')->limit(20)->get()->map(fn (OperationRun $run) => [
            'id' => $run->id, 'operation' => $run->operation, 'status' => in_array($run->status, ['running', 'succeeded', 'failed'], true) ? $run->status : 'failed',
            'dryRun' => $run->dry_run, 'counts' => OperationRun::aggregateCounts($run->counts, $run->operation),
            'errorCode' => $run->error_code === null ? null : (in_array($run->error_code, ['retention_failed', 'backup_failed', 'operation_record_failed'], true) ? $run->error_code : ($run->operation === 'backup' ? 'backup_failed' : 'retention_failed')),
            'startedAt' => $run->started_at->utc()->toIso8601String(), 'finishedAt' => $run->finished_at?->utc()->toIso8601String(),
        ]);
        $failed = DB::table('failed_jobs')->whereIn('queue', ['retention', 'backups'])->orderByDesc('failed_at')->orderByDesc('id')->limit(20)->get(['id', 'failed_at', 'queue'])->map(fn ($job) => [
            'id' => (int) $job->id, 'failedAt' => CarbonImmutable::parse($job->failed_at, 'UTC')->toIso8601String(), 'queue' => $job->queue,
        ]);

        return response()->json(['backup' => [
            'enabled' => config('operations.backup.enabled') === true, 'time' => config('operations.backup.time'),
        ], 'retention' => [
            'enabled' => config('operations.retention.enabled') === true,
            'restoreVerified' => config('operations.retention.restore_verified') === true,
            'dryRun' => config('operations.retention.dry_run') !== false,
            'batch' => config('operations.retention.batch'), 'time' => config('operations.retention.time'),
        ], 'runs' => $runs, 'failedJobs' => $failed]);
    }
}
