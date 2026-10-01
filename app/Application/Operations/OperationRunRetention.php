<?php

declare(strict_types=1);

namespace App\Application\Operations;

use App\Models\OperationRun;
use Carbon\CarbonImmutable;

final class OperationRunRetention
{
    public function run(bool $dryRun, int $batch): int
    {
        $cutoff = CarbonImmutable::now('UTC')->subDays(30)->format('Y-m-d H:i:s.u');
        $eligible = fn () => OperationRun::query()->whereIn('status', ['succeeded', 'failed'])->whereNotNull('finished_at')->where('finished_at', '<=', $cutoff);
        $ids = $eligible()->orderBy('finished_at')->orderBy('id')->limit($batch)->pluck('id');

        return $dryRun ? $ids->count() : $eligible()->whereIn('id', $ids)->delete();
    }
}
