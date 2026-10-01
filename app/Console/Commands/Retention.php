<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\History\RetentionService;
use Illuminate\Console\Command;
use Throwable;

final class Retention extends Command
{
    protected $signature = 'lessons:retention {--dry-run : Show planned counts without writing} {--batch=100 : Maximum candidate sessions inspected per run (1-1000)}';

    protected $description = 'Apply conservative finished-session retention in bounded batches';

    public function handle(RetentionService $retention): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($batch === false) {
            $this->error('Retention batch must be an integer from 1 to 1000.');

            return self::FAILURE;
        }
        try {
            $counts = $retention->run((bool) $this->option('dry-run'), $batch);
            $this->line(json_encode(['dryRun' => (bool) $this->option('dry-run'), 'counts' => $counts], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Retention failed. Inspect private operational configuration; completed earlier batches remain committed.');

            return self::FAILURE;
        }
    }
}
