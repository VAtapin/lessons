<?php

declare(strict_types=1);

use App\Application\Operations\OperationsLock;
use App\Application\Operations\PrivateBackupFiles;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    $lock = new OperationsLock(new PrivateBackupFiles, dirname(__DIR__).'/storage/framework/cache/operations');
    fwrite(STDOUT, $lock->path()."\n");
} catch (Throwable) {
    fwrite(STDERR, "Cannot prepare the private shared operations lock.\n");
    exit(1);
}
