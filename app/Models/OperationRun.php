<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OperationRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['operation', 'status', 'dry_run', 'counts', 'error_code', 'started_at', 'finished_at'];

    public const COUNT_KEYS = ['sessionsDeleted', 'detailsPurged', 'rehearsalVersionsDeleted', 'receiptsDeleted', 'saveReceiptsDeleted', 'operationRunsDeleted'];

    public const BACKUP_COUNT_KEYS = ['databaseBytes', 'mediaBytes', 'mediaVersions'];

    protected function casts(): array
    {
        return ['dry_run' => 'boolean', 'counts' => 'array', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    public static function aggregateCounts(?array $counts, string $operation = 'retention'): ?array
    {
        if ($counts === null) {
            return null;
        }
        $safe = [];
        foreach ($operation === 'backup' ? self::BACKUP_COUNT_KEYS : self::COUNT_KEYS as $key) {
            if (isset($counts[$key]) && is_int($counts[$key]) && $counts[$key] >= 0) {
                $safe[$key] = $counts[$key];
            }
        }

        return $safe;
    }
}
