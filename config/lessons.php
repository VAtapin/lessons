<?php

return [
    // Initial UI dictionaries; lesson content declares its own language set.
    'ui_locales' => ['ru', 'de'],

    'media' => [
        'disk' => 'media',
        'max_file_bytes' => (int) env('LESSONS_MEDIA_MAX_FILE_BYTES', 20 * 1024 * 1024),
        'guest_quota_bytes' => (int) env('LESSONS_MEDIA_GUEST_QUOTA_BYTES', 100 * 1024 * 1024),
        'account_quota_bytes' => (int) env('LESSONS_MEDIA_ACCOUNT_QUOTA_BYTES', 1024 * 1024 * 1024),
        'max_pixels' => (int) env('LESSONS_MEDIA_MAX_PIXELS', 32 * 1024 * 1024),
        'max_dimension' => (int) env('LESSONS_MEDIA_MAX_DIMENSION', 8192),
    ],

    'runtime' => [
        'activity_write_seconds' => 5,
        'connected_seconds' => 20,
        'wave_seconds' => 6,
        'timer_max_seconds' => 7200,
    ],
];
