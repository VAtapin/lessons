<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\RestoreBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RestoreTest extends Command
{
    protected $signature = 'lessons:restore-test {bundle : Private bundle directory} {--manifest-sha256= : Accepted manifest checksum} {--media= : New isolated media directory outside the application} {--timeout=300 : Import timeout (1-3600 seconds)}';

    protected $description = 'Restore a verified bundle into an empty, restricted loopback lessons_restore_test database only';

    public function handle(RestoreBundle $restore): int
    {
        try {
            $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
            if ($timeout === false || ! is_string($this->option('media')) || ! is_string($this->option('manifest-sha256'))) {
                throw new BackupFailure('Explicit media destination, manifest SHA256 and timeout from 1 to 3600 are required.');
            }
            $result = $restore->restore(DB::connection(), app()->environment(), $this->argument('bundle'), $this->option('manifest-sha256'), $this->option('media'), base_path(), $timeout);
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (BackupFailure $failure) {
            $this->error('Isolated restore failed: '.$failure->getMessage());
        } catch (Throwable) {
            $this->error('Isolated restore failed. Inspect private test configuration; keep the target isolated.');
        }

        return self::FAILURE;
    }
}
