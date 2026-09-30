<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Operations\BackupBundle;
use App\Application\Operations\BackupFailure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class Backup extends Command
{
    protected $signature = 'lessons:backup {--directory= : Private absolute directory outside the application} {--timeout=300 : Dump timeout in seconds (1-3600)}';

    protected $description = 'Create a private database and immutable media backup bundle before migrations';

    public function handle(BackupBundle $backup): int
    {
        try {
            $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
            if ($timeout === false) {
                throw new BackupFailure('Backup timeout must be an integer from 1 to 3600 seconds.');
            }
            $disk = config('filesystems.disks.'.config('lessons.media.disk'));
            if (! is_array($disk) || ($disk['driver'] ?? null) !== 'local' || ! is_string($disk['root'] ?? null)) {
                throw new BackupFailure('Private media backup requires a configured local media disk.');
            }
            $directory = $this->option('directory') ?? dirname(base_path()).'/private/lessons-backups';
            $bundle = $backup->create(DB::connection(), $directory, base_path(), $disk['root'], $timeout);
            $this->info('Private database and media backup created: '.$bundle);
            $this->info('Manifest SHA256: '.hash_file('sha256', $bundle.'/manifest.json'));

            return self::SUCCESS;
        } catch (BackupFailure $failure) {
            $this->error('Backup failed: '.$failure->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Backup failed. Check private configuration, schema, permissions and disk access.');

            return self::FAILURE;
        }
    }
}
