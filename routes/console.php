<?php

use App\Jobs\ScheduledBackup;
use App\Jobs\ScheduledRetention;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$backupTime = config('operations.backup.time');
if (config('operations.backup.enabled') === true
    && is_string($backupTime) && preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $backupTime)) {
    Schedule::job(new ScheduledBackup, 'backups', 'operations-backups')
        ->dailyAt($backupTime)->timezone('UTC')->name('lessons-backup')->withoutOverlapping(30);
}

$batch = config('operations.retention.batch');
$time = config('operations.retention.time');
if (config('operations.retention.enabled') === true && config('operations.retention.restore_verified') === true
    && is_int($batch) && $batch >= 1 && $batch <= 1000
    && is_string($time) && preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $time)) {
    Schedule::job(new ScheduledRetention($batch, config('operations.retention.dry_run') !== false), 'retention', 'database')
        ->dailyAt($time)->timezone('UTC')->name('lessons-retention')->withoutOverlapping(30);
}
