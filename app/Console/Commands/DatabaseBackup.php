<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\DatabaseBackup as Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseBackup extends Command
{
    protected $signature = 'lessons:database-backup {--directory= : Private absolute directory outside the application} {--timeout=300 : Dump timeout in seconds (1-3600)}';

    protected $description = 'Create a private MySQL/MariaDB schema and data backup before migrations';

    public function handle(Backup $backup): int
    {
        try {
            $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
            if ($timeout === false) {
                throw new BackupFailure('Backup timeout must be an integer from 1 to 3600 seconds.');
            }
            $directory = $this->option('directory') ?? dirname(base_path()).'/private/lessons-backups';
            $file = $backup->create(DB::connection()->getConfig(), $directory, base_path(), $timeout);
            $this->info('Private database backup created: '.$file);
            $this->info('SHA256: '.hash_file('sha256', $file));

            return self::SUCCESS;
        } catch (BackupFailure $failure) {
            $this->error('Database backup failed: '.$failure->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            // Do not expose process diagnostics, credentials, SQL, or data in logs.
            $this->error('Database backup failed. Check private configuration, permissions, dump access and timeout.');

            return self::FAILURE;
        }
    }
}
