<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Tests\Support\RuntimeConcurrencyGuard;

/** Never resolve a database connection before checking the effective test configuration. */
function loadDatabase(Application $app): void
{
    if (getenv('APP_ENV') !== 'testing' || PHP_OS_FAMILY === 'Windows' || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5
        || $app->configurationIsCached() || config('operations.retention.enabled') !== false
        || config('session.driver') !== 'file' || config('cache.default') !== 'file'
        || config('session.secure') === true || ! in_array(config('session.domain'), [null, ''], true)) {
        throw new RuntimeException('Load runner requires uncached Linux testing, file sessions/cache and disabled retention.');
    }
    $database = RuntimeConcurrencyGuard::database($app);
    $version = $database->selectOne('SELECT VERSION() AS version')->version;
    if (! preg_match('/\A10\.6\..*MariaDB/', $version)) {
        throw new RuntimeException('Load runner requires MariaDB 10.6.');
    }
}
