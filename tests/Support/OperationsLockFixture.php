<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Operations\OperationsLock;
use App\Application\Operations\PrivateBackupFiles;

trait OperationsLockFixture
{
    private string $operationsDirectory;

    private function prepareOperationsLock(): void
    {
        $this->operationsDirectory = str_replace('\\', '/', sys_get_temp_dir()).'/lessons-job-lock-'.bin2hex(random_bytes(8));
        mkdir($this->operationsDirectory, 0700);
        $this->app->instance(OperationsLock::class, new OperationsLock(new PrivateBackupFiles, $this->operationsDirectory));
    }

    private function removeOperationsLock(): void
    {
        if (is_file($this->operationsDirectory.'/lock')) {
            unlink($this->operationsDirectory.'/lock');
        }
        rmdir($this->operationsDirectory);
    }
}
