<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use RuntimeException;

final class RuntimeConcurrencyGuard
{
    public static function database(Application $app): Connection
    {
        if (! $app->environment('testing') || $app['config']->get('app.env') !== 'testing') {
            throw new RuntimeException('Concurrency tests require APP_ENV=testing.');
        }
        // Laravel resolves a connection lazily: inspect effective URL/config before any network access.
        $connection = $app['db']->connection();
        $config = $connection->getConfig();
        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)
            || ($config['database'] ?? null) !== 'lessons_test'
            || ! in_array($config['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            || ($config['unix_socket'] ?? '') !== '' || ($config['prefix'] ?? '') !== ''
            || ! empty($config['url']) || isset($config['read']) || isset($config['write'])) {
            throw new RuntimeException('Concurrency tests require the dedicated loopback MySQL/MariaDB lessons_test database without URL, socket, prefix or replicas.');
        }
        $database = $connection->selectOne('SELECT DATABASE() AS database_name');
        if ($database->database_name !== 'lessons_test') {
            throw new RuntimeException('The actual concurrency database is not lessons_test.');
        }

        return $connection;
    }

    public static function directory(string $runId): string
    {
        if (! preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/', $runId)) {
            throw new RuntimeException('Invalid concurrency fixture identifier.');
        }

        return str_replace('\\', '/', (string) realpath(sys_get_temp_dir())).'/lessons-concurrency-'.$runId;
    }

    public static function privateDirectory(string $runId): string
    {
        $directory = self::directory($runId);
        if (! is_dir($directory) || is_link($directory) || realpath($directory) !== $directory
            || (PHP_OS_FAMILY !== 'Windows' && (fileperms($directory) & 0777) !== 0700)) {
            throw new RuntimeException('Concurrency barrier must be a private canonical fixture directory.');
        }

        return $directory;
    }
}
