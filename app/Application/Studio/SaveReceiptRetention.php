<?php

declare(strict_types=1);

namespace App\Application\Studio;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Models\LessonMaterial;
use App\Models\LessonSaveReceipt;
use Carbon\CarbonImmutable;

final class SaveReceiptRetention
{
    public function run(bool $dryRun, int $batch): int
    {
        $cutoff = CarbonImmutable::now('UTC')->subDays(30)->format('Y-m-d H:i:s.u');
        $candidates = LessonSaveReceipt::query()->where('created_at', '<=', $cutoff)->orderBy('id')->limit($batch)->get(['id', 'lesson_material_id']);
        if ($dryRun) {
            return $candidates->count();
        }
        $deleted = 0;
        foreach ($candidates->groupBy('lesson_material_id') as $materialId => $receipts) {
            // Same ownership-discovery retry as session cleanup; a concurrent claim
            // must never make cleanup lock an old owner and mutate a new workspace.
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $owner = LessonMaterial::query()->whereKey($materialId)->value('owner_key');
                if ($owner === null) {
                    break;
                }
                $result = OwnerMutation::transaction([$owner], function () use ($owner, $materialId, $receipts, $cutoff): ?int {
                    $material = LessonMaterial::query()->lockForUpdate()->find($materialId);
                    if ($material === null) {
                        return 0;
                    }
                    if ($material->owner_key !== $owner) {
                        return null;
                    }

                    return LessonSaveReceipt::query()->where('lesson_material_id', $materialId)->whereIn('id', $receipts->pluck('id'))
                        ->where('created_at', '<=', $cutoff)->delete();
                }, true);
                if ($result !== null) {
                    $deleted += $result;
                    break;
                }
                if ($attempt === 2) {
                    throw new ApiProblem('retention_failed', 503);
                }
            }
        }

        return $deleted;
    }
}
