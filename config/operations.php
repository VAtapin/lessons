<?php

return [
    'backup' => [
        'enabled' => env('LESSONS_BACKUP_ENABLED', false),
        'time' => env('LESSONS_BACKUP_TIME', '02:30'),
        'directory' => env('LESSONS_BACKUP_DIRECTORY', dirname(base_path()).'/private/lessons-backups'),
    ],
    'retention' => [
        'enabled' => env('LESSONS_RETENTION_ENABLED', false),
        // Operator acceptance of an actual SQL/media restore, never set by a checksum alone.
        'restore_verified' => env('LESSONS_RETENTION_RESTORE_VERIFIED', false),
        'dry_run' => env('LESSONS_RETENTION_DRY_RUN', true),
        'batch' => (int) env('LESSONS_RETENTION_BATCH', 100),
        'time' => env('LESSONS_RETENTION_TIME', '03:15'),
    ],
];
